<?php

namespace App\Quotes;

use App\Enums\QuoteScale;
use App\Models\Quote;

/**
 * The Quote as its card draws it: the text without the quote marks somebody
 * typed around it, and the size it is set in.
 *
 * Both answers live here and not in the Blade because both are decisions with
 * a reason, and a decision written as a ternary inside a view is a decision
 * nobody can test. The view puts the class names on the element; this says
 * which one.
 */
final class QuoteCard
{
    /**
     * How many characters of the mono face fit on one line of the card.
     *
     * It is an estimate, and deliberately one: the real number depends on the
     * width the card is given, and what this is for is choosing between three
     * sizes, not laying out the paragraph. Measured at the size of the
     * direction over the column the card leaves for the text.
     */
    private const COLUMN = 46;

    /** Up to this many lines the Quote is short enough to be set larger. */
    private const SHORT = 2;

    /** From this many lines on it steps down instead of the card running long. */
    private const LONG = 9;

    /**
     * The quote marks a Quote may have been typed with. They are the ones that
     * open and close a quotation in Spanish and in English; the apostrophe is
     * not here on purpose, because a word can legitimately end in one.
     *
     * @var list<string>
     */
    private const MARKS = ['"', '“', '”', '«', '»'];

    private function __construct(private readonly string $text) {}

    public static function for(Quote $quote): self
    {
        return self::of($quote->text);
    }

    /** The card of a text that is not a stored Quote yet. */
    public static function of(string $text): self
    {
        return new self(trim($text));
    }

    /**
     * The Quote as the card sets it, which is without the quote marks it may
     * carry: the card draws its own, and a Quote typed with them would come out
     * inside two pairs.
     *
     * Only the ones at the very ends go. The ones inside are the Book quoting
     * somebody else, and those belong to the text.
     */
    public function text(): string
    {
        $text = $this->text;

        while ($text !== '' && in_array(mb_substr($text, 0, 1), self::MARKS, true)) {
            $text = ltrim(mb_substr($text, 1));
        }

        while ($text !== '' && in_array(mb_substr($text, -1), self::MARKS, true)) {
            $text = rtrim(mb_substr($text, 0, mb_strlen($text) - 1));
        }

        return $text;
    }

    /**
     * Which of the three sizes the Quote is set in.
     *
     * The long one steps down and is never cut: cutting the Quote is cutting
     * the one thing the card exists to show, and the card is also what gets
     * shared as an image, where a «leer completa» leads nowhere.
     */
    public function scale(): QuoteScale
    {
        $lines = $this->lines();

        return match (true) {
            $lines <= self::SHORT => QuoteScale::Large,
            $lines >= self::LONG => QuoteScale::Small,
            default => QuoteScale::Regular,
        };
    }

    /**
     * How many lines the Quote is going to take, counting the ones it breaks
     * into by itself.
     *
     * The line breaks are counted and not squashed: a Quote of verse or of
     * dialogue is eight short lines tall even though its characters would fit
     * in three, and measuring it by length alone sets a poem in the size of an
     * epigram.
     */
    public function lines(): int
    {
        $lines = 0;

        foreach (explode("\n", $this->text()) as $line) {
            $lines += max(1, (int) ceil(mb_strlen(trim($line)) / self::COLUMN));
        }

        return $lines;
    }
}
