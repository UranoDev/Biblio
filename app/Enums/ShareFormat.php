<?php

namespace App\Enums;

/**
 * The two shapes the card of a Quote gets rasterized into (ADR-009).
 *
 * They are two because the networks are two kinds. X, Facebook and WhatsApp
 * read `og:image` and draw the card themselves, and they draw it wide.
 * Instagram reads no tags at all: there the image gets uploaded by hand, and it
 * goes up in the proportion of a story.
 *
 * The value travels in the address of the image, so it is also what keeps the
 * two of them at two different URLs.
 */
enum ShareFormat: string
{
    case Card = 'card';

    case Story = 'story';

    /** The width, in pixels, the renderer is asked for. */
    public function width(): int
    {
        return match ($this) {
            self::Card => 1200,
            self::Story => 1080,
        };
    }

    public function height(): int
    {
        return match ($this) {
            self::Card => 630,
            self::Story => 1350,
        };
    }

    /** The column of the Quote that holds the file of this format. */
    public function column(): string
    {
        return match ($this) {
            self::Card => 'card_image_path',
            self::Story => 'story_image_path',
        };
    }

    /**
     * The one a network takes on its own when the link is pasted.
     */
    public static function shared(): self
    {
        return self::Card;
    }

    /**
     * The one that gets downloaded and uploaded by hand, which is the vertical
     * one: Instagram is the reason this format exists.
     */
    public static function downloaded(): self
    {
        return self::Story;
    }
}
