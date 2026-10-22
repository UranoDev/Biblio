<?php

use App\Drive\DriveFile;
use App\Drive\DriveUnavailable;
use App\Drive\GoogleDrive;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    configureDrive();
});

it('cambia una llave de service account por un token firmado', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'www.googleapis.com/drive/v3/files*' => Http::response(['files' => []]),
    ]);

    app(GoogleDrive::class)->images('inbox');

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), 'oauth2.googleapis.com')) {
            return false;
        }

        [$header, $claims, $signature] = explode('.', $request['assertion']);
        $claims = json_decode(base64_decode(strtr($claims, '-_', '+/')), true);
        $public = openssl_pkey_get_details(openssl_pkey_get_private(file_get_contents(base_path('tests/Fixtures/drive/test-key.pem'))))['key'];

        return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
            && $claims['iss'] === 'mibiblio@test.iam.gserviceaccount.com'
            && $claims['scope'] === 'https://www.googleapis.com/auth/drive'
            && openssl_verify("{$header}.".explode('.', $request['assertion'])[1], base64_decode(strtr($signature, '-_', '+/')), $public, OPENSSL_ALGO_SHA256) === 1;
    });

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/drive/v3/files')
        && $request->hasHeader('Authorization', 'Bearer token-1'));
});

it('guarda el token y no lo vuelve a pedir en cada llamada', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'www.googleapis.com/drive/v3/files*' => Http::response(['files' => []]),
    ]);

    app(GoogleDrive::class)->images('inbox');
    app(GoogleDrive::class)->images('inbox');

    Http::assertSentCount(3);
});

it('lista las imágenes de la carpeta, página por página', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'www.googleapis.com/drive/v3/files*' => Http::sequence()
            ->push(['nextPageToken' => 'next', 'files' => [
                ['id' => 'a', 'name' => 'a.jpg', 'mimeType' => 'image/jpeg', 'size' => '1000'],
            ]])
            ->push(['files' => [
                ['id' => 'b', 'name' => 'b.heic', 'mimeType' => 'image/heic', 'size' => '9000', 'thumbnailLink' => 'https://lh3.googleusercontent.com/x=s220'],
            ]]),
    ]);

    $files = app(GoogleDrive::class)->images('inbox');

    expect(array_map(fn (DriveFile $file) => $file->id, $files))->toBe(['a', 'b'])
        ->and($files[0]->size)->toBe(1000)
        ->and($files[1]->thumbnailLink)->toBe('https://lh3.googleusercontent.com/x=s220');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/drive/v3/files')
        && str_contains(urldecode($request->url()), "'inbox' in parents and trashed = false"));
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'pageToken=next'));
});

it('pide la miniatura del tamaño configurado', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'lh3.googleusercontent.com/*' => Http::response('jpeg'),
    ]);

    $file = new DriveFile('b', 'b.heic', 'image/heic', 9000, 'https://lh3.googleusercontent.com/x=s220');

    expect(app(GoogleDrive::class)->thumbnail($file, 1600))->toBe('jpeg');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://lh3.googleusercontent.com/x=s1600');
});

it('mueve el archivo de una carpeta a otra', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'www.googleapis.com/drive/v3/files/*' => Http::response(['id' => 'a']),
    ]);

    app(GoogleDrive::class)->move('a', 'inbox', 'done');

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/files/a?')
        && str_contains($request->url(), 'addParents=done')
        && str_contains($request->url(), 'removeParents=inbox'));
});

it('avisa cuando Drive contesta con un error', function () {
    Http::fake([
        'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token-1', 'expires_in' => 3600]),
        'www.googleapis.com/drive/v3/files*' => Http::response(['error' => 'notFound'], 404),
    ]);

    app(GoogleDrive::class)->images('inbox');
})->throws(DriveUnavailable::class, 'Drive answered 404');

it('avisa cuando no puede leer la llave', function () {
    config(['drive.credentials' => base_path('no-such-key.json')]);
    app()->forgetInstance(GoogleDrive::class);

    app(GoogleDrive::class)->images('inbox');
})->throws(DriveUnavailable::class, 'cannot be read');

it('sabe que no está configurado mientras falte una carpeta', function () {
    config(['drive.processed_folder' => null]);

    expect(GoogleDrive::isConfigured())->toBeFalse();
});
