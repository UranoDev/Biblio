<?php

namespace App\Quotes;

use App\Enums\ShareFormat;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The renderer of ADR-009: html2img draws the card and we keep the file.
 *
 * The service answers with an address and not with the image, so this makes two
 * requests: one to draw it and one to fetch what it drew. That second one is
 * the whole point of the seam — what gets stored is ours, on our disk, and the
 * day the service closes or stops being worth it the images already made keep
 * working.
 *
 * The key lives in the environment, like every other one.
 */
final readonly class Html2Img implements ImageRenderer
{
    /**
     * The statuses that mean «not now»: the request that never landed, the
     * service falling over, and the CDNs in front of it saying the same thing.
     * 402 is not among them — that one is the credits, and it has its own way
     * out.
     *
     * @var list<int>
     */
    private const TRANSIENT = [408, 425, 429, 500, 502, 503, 504, 520, 522, 524];

    public function __construct(
        private string $url,
        private ?string $key,
        private int $timeout,
    ) {}

    /** Nothing is asked of the service without a key; it lives in the environment. */
    public function available(): bool
    {
        return $this->key !== null && trim($this->key) !== '';
    }

    public function render(string $html, ShareFormat $format): string
    {
        if (! $this->available()) {
            throw RenderFailed::withoutAKey();
        }

        $drawn = $this->ask($html, $format);

        $url = $drawn['url'] ?? null;

        if (! is_string($url) || trim($url) === '') {
            throw RenderFailed::forGood('The renderer answered without the address of an image.');
        }

        return $this->fetch($url);
    }

    /**
     * Asks the service to draw the page.
     *
     * @return array<string, mixed>
     */
    private function ask(string $html, ShareFormat $format): array
    {
        try {
            $response = Http::withHeader('X-API-Key', (string) $this->key)
                ->timeout($this->timeout)
                ->asJson()
                ->post($this->url, [
                    'html' => $html,
                    'width' => $format->width(),
                    'height' => $format->height(),
                    'format' => 'png',
                ]);
        } catch (ConnectionException $dropped) {
            throw RenderFailed::forNow($dropped->getMessage());
        }

        // 402, which is how html2img says the month ran out.
        if ($response->status() === 402) {
            throw OutOfCredits::said($this->reasonIn($response));
        }

        if (in_array($response->status(), self::TRANSIENT, true)) {
            throw RenderFailed::forNow("The renderer answered {$response->status()}.");
        }

        if (! $response->successful()) {
            throw RenderFailed::forGood(
                "The renderer answered {$response->status()}: {$this->reasonIn($response)}"
            );
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        return $body;
    }

    /**
     * Brings the image home. The address the service hands over is a file on
     * its CDN and it is not kept anywhere: what gets stored is these bytes.
     */
    private function fetch(string $url): string
    {
        try {
            $image = Http::timeout($this->timeout)->get($url);
        } catch (ConnectionException $dropped) {
            throw RenderFailed::forNow($dropped->getMessage());
        }

        if (! $image->successful()) {
            throw RenderFailed::forNow(
                "The image the renderer drew answered {$image->status()}."
            );
        }

        $bytes = $image->body();

        if ($bytes === '') {
            throw RenderFailed::forNow('The image the renderer drew came back empty.');
        }

        return $bytes;
    }

    /** What the service said about itself, when it said anything at all. */
    private function reasonIn(Response $response): string
    {
        $said = $response->json('message') ?? $response->json('error');

        return is_string($said) && trim($said) !== '' ? trim($said) : $response->body();
    }
}
