<?php

namespace Tests\Support;

use App\Enums\ShareFormat;
use App\Quotes\ImageRenderer;
use Throwable;

/**
 * The renderer during the tests.
 *
 * Nothing in the suite goes out to html2img and nothing in it spends a credit:
 * that is what the seam of ADR-009 is for, and this is the other side of it. It
 * keeps the HTML it was handed, so a test can ask what was really sent — the
 * typefaces inlined, the Cover inlined — and it can be told to answer with a
 * failure instead, which is how the exhausted credits get tested without
 * exhausting any.
 */
final class FakeImageRenderer implements ImageRenderer
{
    /** @var list<array{html: string, format: ShareFormat}> */
    public array $drawn = [];

    public function __construct(
        private readonly ?Throwable $failure = null,
        private readonly bool $available = true,
    ) {}

    /** A renderer that refuses every render with this failure. */
    public static function failingWith(Throwable $failure): self
    {
        return new self(failure: $failure);
    }

    /** One with nothing to draw with: no key configured. */
    public static function unavailable(): self
    {
        return new self(available: false);
    }

    public function available(): bool
    {
        return $this->available;
    }

    public function render(string $html, ShareFormat $format): string
    {
        $this->drawn[] = ['html' => $html, 'format' => $format];

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        return "png:{$format->value}:{$format->width()}x{$format->height()}";
    }

    /** The HTML of the last render, for the test that reads what was sent. */
    public function lastHtml(): string
    {
        return $this->drawn === [] ? '' : $this->drawn[array_key_last($this->drawn)]['html'];
    }

    /** @return list<ShareFormat> */
    public function formats(): array
    {
        return array_map(fn (array $render): ShareFormat => $render['format'], $this->drawn);
    }
}
