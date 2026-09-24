<?php

namespace App\Quotes;

use Stringable;

/**
 * The page of a Quote as the Book prints it, and the two things its order is
 * made of.
 *
 * A page in roman numerals is not another way of writing the same number: it is
 * the front matter. Page `xiv` does not go between 13 and 15 — it goes before
 * page 1. So the label is what gets stored and shown, and the order comes out
 * of whether it is front matter and what it is worth.
 */
final readonly class PageLabel implements Stringable
{
    /** Arabic numerals stop where a book does; nothing is printed past this. */
    private const HIGHEST = 100000;

    /** A roman numeral written the way roman numerals are written, and no other way. */
    private const ROMAN = '/^m{0,3}(cm|cd|d?c{0,3})(xc|xl|l?x{0,3})(ix|iv|v?i{0,3})$/';

    private function __construct(
        private string $label,
        private bool $frontMatter,
        private int $number,
    ) {}

    /**
     * Reads what was typed, or null when it is neither arabic nor roman.
     *
     * The blank space around it goes — that is typing, not the Book — and the
     * rest is kept exactly as it came: a Book that prints `XIV` in capitals
     * shows `XIV`.
     */
    public static function parse(string $value): ?self
    {
        $label = trim($value);

        if ($label === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $label) === 1) {
            $number = (int) $label;

            if ($number < 1 || $number > self::HIGHEST) {
                return null;
            }

            return new self($label, false, $number);
        }

        if (preg_match(self::ROMAN, mb_strtolower($label)) === 1) {
            return new self($label, true, self::romanValue(mb_strtolower($label)));
        }

        return null;
    }

    public static function isValid(string $value): bool
    {
        return self::parse($value) !== null;
    }

    /** The label as the Book prints it, which is what gets stored and shown. */
    public function label(): string
    {
        return $this->label;
    }

    /** Whether it is a page of the front matter, which goes before page 1. */
    public function isFrontMatter(): bool
    {
        return $this->frontMatter;
    }

    /** What the label is worth, which orders the pages of its own kind. */
    public function number(): int
    {
        return $this->number;
    }

    public function __toString(): string
    {
        return $this->label;
    }

    /**
     * What a roman numeral adds up to, read from the right: a letter worth less
     * than the one after it is subtracted.
     */
    private static function romanValue(string $roman): int
    {
        $values = ['i' => 1, 'v' => 5, 'x' => 10, 'l' => 50, 'c' => 100, 'd' => 500, 'm' => 1000];

        $total = 0;
        $highest = 0;

        foreach (array_reverse(mb_str_split($roman)) as $letter) {
            $value = $values[$letter];

            if ($value < $highest) {
                $total -= $value;

                continue;
            }

            $total += $value;
            $highest = $value;
        }

        return $total;
    }
}
