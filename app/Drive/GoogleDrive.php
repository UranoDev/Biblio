<?php

namespace App\Drive;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The Drive API, as a service account sees it (BIB-74).
 *
 * The three calls the import needs and nothing else: list the images of a
 * folder, fetch one, and move it. It signs its own token out of the JSON key
 * instead of pulling in the Google client library: the key and one signed
 * request are all the service account flow is, and the HTTP client is the one
 * the tests already fake.
 */
final class GoogleDrive
{
    private const API = 'https://www.googleapis.com/drive/v3';

    private const SCOPE = 'https://www.googleapis.com/auth/drive';

    private const TOKEN_CACHE = 'drive.access-token';

    public function __construct(
        private readonly ?string $credentialsPath,
        private readonly int $timeout = 30,
    ) {}

    /** Whether the key and both folders are set: without them nothing is imported. */
    public static function isConfigured(): bool
    {
        return filled(config('drive.credentials'))
            && filled(config('drive.folder'))
            && filled(config('drive.processed_folder'));
    }

    /**
     * The images in the folder, oldest first, so they become drafts in the
     * order they were uploaded.
     *
     * @return list<DriveFile>
     *
     * @throws DriveUnavailable
     */
    public function images(string $folderId): array
    {
        $files = [];
        $pageToken = null;

        do {
            $answer = $this->call(fn (PendingRequest $http) => $http->get(self::API.'/files', array_filter([
                'q' => "'{$folderId}' in parents and trashed = false and mimeType contains 'image/'",
                'fields' => 'nextPageToken, files(id, name, mimeType, size, thumbnailLink)',
                'orderBy' => 'createdTime',
                'pageSize' => 100,
                'pageToken' => $pageToken,
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ])))->json();

            foreach ($answer['files'] ?? [] as $file) {
                $files[] = DriveFile::fromApi($file);
            }

            $pageToken = $answer['nextPageToken'] ?? null;
        } while ($pageToken !== null);

        return $files;
    }

    /**
     * The file as it was uploaded.
     *
     * @throws DriveUnavailable
     */
    public function content(DriveFile $file): string
    {
        return $this->call(fn (PendingRequest $http) => $http->get(
            self::API.'/files/'.$file->id,
            ['alt' => 'media', 'supportsAllDrives' => 'true'],
        ))->body();
    }

    /**
     * The JPEG Drive renders of the file, with its longest side at `$size`.
     * Drive renders it a little after the upload, so a photograph uploaded a
     * moment ago may not have one yet.
     *
     * @throws DriveUnavailable
     */
    public function thumbnail(DriveFile $file, int $size): string
    {
        if ($file->thumbnailLink === null) {
            throw DriveUnavailable::because("Drive has no thumbnail of «{$file->name}» yet.");
        }

        // The link asks for a 220 pixel version; the suffix says the size.
        $url = preg_replace('/=s\d+$/', '', $file->thumbnailLink).'=s'.$size;

        return $this->call(fn (PendingRequest $http) => $http->get($url))->body();
    }

    /**
     * Takes the file out of one folder and into another.
     *
     * @throws DriveUnavailable
     */
    public function move(string $fileId, string $from, string $to): void
    {
        $this->call(fn (PendingRequest $http) => $http->patch(
            self::API.'/files/'.$fileId.'?'.http_build_query([
                'addParents' => $to,
                'removeParents' => $from,
                'fields' => 'id',
                'supportsAllDrives' => 'true',
            ]),
            [],
        ));
    }

    /**
     * @param  callable(PendingRequest): Response  $request
     *
     * @throws DriveUnavailable
     */
    private function call(callable $request): Response
    {
        try {
            return $request(Http::withToken($this->token())->timeout($this->timeout))->throw();
        } catch (RequestException $failure) {
            throw DriveUnavailable::because(
                'Drive answered '.$failure->response->status().': '.$failure->response->body(),
                $failure,
            );
        } catch (ConnectionException $failure) {
            throw DriveUnavailable::because('Drive did not answer: '.$failure->getMessage(), $failure);
        }
    }

    /**
     * An access token for the service account, kept until shortly before it
     * expires: a token lasts an hour, and the folder is looked at far more
     * often than that.
     *
     * @throws DriveUnavailable
     */
    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE);

        if (is_string($cached)) {
            return $cached;
        }

        $key = $this->key();
        $tokenUri = (string) ($key['token_uri'] ?? 'https://oauth2.googleapis.com/token');

        try {
            $answer = Http::asForm()->timeout($this->timeout)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->assertion($key, $tokenUri),
            ])->throw()->json();
        } catch (RequestException $failure) {
            throw DriveUnavailable::because(
                'Google refused the service account token ('.$failure->response->status().'): '.$failure->response->body(),
                $failure,
            );
        } catch (ConnectionException $failure) {
            throw DriveUnavailable::because('Google did not answer the token request: '.$failure->getMessage(), $failure);
        }

        $token = (string) ($answer['access_token'] ?? '');

        if ($token === '') {
            throw DriveUnavailable::because('Google answered the token request without a token.');
        }

        Cache::put(self::TOKEN_CACHE, $token, max(60, (int) ($answer['expires_in'] ?? 3600) - 300));

        return $token;
    }

    /**
     * The signed JWT the token is traded for (RFC 7523).
     *
     * @param  array<string, mixed>  $key
     *
     * @throws DriveUnavailable
     */
    private function assertion(array $key, string $tokenUri): string
    {
        $now = time();

        $unsigned = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.$this->base64Url((string) json_encode([
                'iss' => $key['client_email'],
                'scope' => self::SCOPE,
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

        $privateKey = openssl_pkey_get_private((string) $key['private_key']);

        if ($privateKey === false || ! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw DriveUnavailable::because('The private key of the service account could not sign the token request.');
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws DriveUnavailable
     */
    private function key(): array
    {
        $path = (string) $this->credentialsPath;

        if ($path === '' || ! is_readable($path)) {
            throw DriveUnavailable::because("The service account key at «{$path}» cannot be read.");
        }

        $key = json_decode((string) file_get_contents($path), true);

        if (! is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
            throw DriveUnavailable::because("«{$path}» is not the JSON key of a service account.");
        }

        return $key;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
