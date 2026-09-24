<?php

namespace App\Search;

use Illuminate\Support\Str;

/**
 * A text as the Search compares it: in lowercase and without its accents, so
 * that «cita» and «citá» are the same word.
 *
 * It exists because the two engines do not agree. MySQL, where this runs,
 * compares `á` and `a` as the same letter by collation; SQLite, where the tests
 * run, does not. A Search that behaved one way on the machine and another way
 * in the suite would be a Search nobody can trust, so neither one is asked:
 * the comparison happens over text that was already folded before it was
 * stored.
 */
final class FoldedText
{
    /**
     * The accented letters and the letter each one is compared as.
     *
     * The `ñ` is missing on purpose: in Spanish it is a letter of its own and
     * not an `n` wearing something, so «año» and «ano» stay two different
     * words. The `ü` is here, because the diaeresis is a spelling mark and
     * «pinguino» has to find «pingüino».
     *
     * @var array<string, string>
     */
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ç' => 'c',
    ];

    /** The text folded whole, which is what gets stored and what gets asked. */
    public static function of(string $text): string
    {
        return implode('', self::characters($text));
    }

    /**
     * The same folding, one character at a time and in the order they came.
     *
     * The Snippet needs it in this shape: it looks for the word in the folded
     * text and then cuts the original at the position it found, and that only
     * works while one character in is one character out.
     *
     * @return list<string>
     */
    public static function characters(string $text): array
    {
        return array_map(self::character(...), mb_str_split($text));
    }

    /**
     * The words that were typed, folded and without the blanks between them.
     *
     * @return list<string>
     */
    public static function words(string $term): array
    {
        $split = preg_split('/\s+/u', self::of(trim($term)));

        return array_values(array_filter(
            $split === false ? [] : $split,
            static fn (string $word): bool => $word !== '',
        ));
    }

    /**
     * A word as the pattern that finds it anywhere inside a text.
     *
     * The escape character is `=` and not the usual backslash: a backslash
     * inside a bound pattern does not mean the same thing to MySQL as to
     * SQLite, and with `=` both engines read the pattern the same way. A `%`
     * typed into the box is a percent sign and not «everything».
     */
    public static function pattern(string $word): string
    {
        return '%'.str_replace(['=', '%', '_'], ['==', '=%', '=_'], $word).'%';
    }

    private static function character(string $character): string
    {
        $lower = Str::lower($character);
        $folded = self::ACCENTS[$lower] ?? $lower;

        // One character in, one character out. A letter that lowercased into
        // two would move everything after it, and the Snippet would cut the
        // original text at the wrong place.
        return mb_strlen($folded) === 1 ? $folded : $character;
    }
}
