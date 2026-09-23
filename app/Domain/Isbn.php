<?php

namespace App\Domain;

use Stringable;

/**
 * The identifier printed in the barcode. It identifies the edition, not the
 * work: two editions of the same title are two ISBNs and therefore two Books.
 *
 * It is always stored normalized to thirteen digits, so that the ISBN-10 and
 * the ISBN-13 of the same Book are the same value and reach the same result.
 */
final readonly class Isbn implements Stringable
{
    private function __construct(private string $isbn13) {}

    /**
     * @throws InvalidIsbn if the text is not an ISBN or its check digit does not add up.
     */
    public static function from(string $value): self
    {
        $clean = self::normalize($value);

        if (preg_match('/^\d{9}[\dX]$/', $clean) === 1) {
            if (! self::checkDigit10Matches($clean)) {
                throw InvalidIsbn::forCheckDigit($value);
            }

            return new self(self::toThirteen(substr($clean, 0, 9)));
        }

        if (preg_match('/^\d{13}$/', $clean) === 1) {
            if (! self::checkDigit13Matches($clean)) {
                throw InvalidIsbn::forCheckDigit($value);
            }

            return new self($clean);
        }

        throw InvalidIsbn::forFormat($value);
    }

    public static function isValid(string $value): bool
    {
        try {
            self::from($value);

            return true;
        } catch (InvalidIsbn) {
            return false;
        }
    }

    /**
     * The ISBN normalized to thirteen digits, without hyphens. It is what gets
     * stored and what the Source is asked for.
     */
    public function value(): string
    {
        return $this->isbn13;
    }

    /**
     * The same ISBN written with ten digits, or null when there is none: only
     * the ones starting with 978 have a ten-digit equivalent.
     */
    public function asTen(): ?string
    {
        if (! str_starts_with($this->isbn13, '978')) {
            return null;
        }

        $nine = substr($this->isbn13, 3, 9);
        $sum = 0;

        foreach (str_split($nine) as $position => $digit) {
            $sum += (int) $digit * (10 - $position);
        }

        $check = (11 - $sum % 11) % 11;

        return $nine.($check === 10 ? 'X' : (string) $check);
    }

    public function equals(self $other): bool
    {
        return $this->isbn13 === $other->isbn13;
    }

    public function __toString(): string
    {
        return $this->isbn13;
    }

    /**
     * The Scanner sends the number exactly as it is printed: with hyphens,
     * with spaces and sometimes with a lowercase X.
     */
    private static function normalize(string $value): string
    {
        return strtoupper(str_replace([' ', '-', '‐', '–', '—'], '', trim($value)));
    }

    private static function checkDigit10Matches(string $isbn): bool
    {
        $sum = 0;

        foreach (str_split($isbn) as $position => $character) {
            $digit = $character === 'X' ? 10 : (int) $character;
            $sum += $digit * (10 - $position);
        }

        return $sum % 11 === 0;
    }

    private static function checkDigit13Matches(string $isbn): bool
    {
        return self::check13(substr($isbn, 0, 12)) === $isbn[12];
    }

    private static function toThirteen(string $nine): string
    {
        $twelve = '978'.$nine;

        return $twelve.self::check13($twelve);
    }

    private static function check13(string $twelve): string
    {
        $sum = 0;

        foreach (str_split($twelve) as $position => $digit) {
            $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - $sum % 10) % 10);
    }
}
