<?php

namespace App\Search;

use Illuminate\Support\Str;

/**
 * The piece of a text around what was searched, cut into Fragments so that the
 * words that matched can be drawn apart from the ones around them.
 *
 * A result that showed the word alone would not say anything —the whole point
 * of remembering a phrase and not the book is reading the phrase back— and one
 * that showed a long Quote whole would bury it. So the piece is cut around the
 * first match, and what is left out is marked with an ellipsis.
 *
 * The line breaks of the Quote do not survive here, and that is the one place
 * in the system where they do not: this is a fragment of the Quote and not the
 * Quote, and the sheet of the Book is where it is read as it was typed.
 */
final class Snippet
{
    /** How many characters the piece carries, at most. */
    private const LENGTH = 220;

    /** How much of that goes before the first word that matched. */
    private const LEAD = 70;

    /**
     * @param  list<string>  $words  the words to look for, already folded
     */
    private function __construct(
        private readonly string $text,
        private readonly array $words,
    ) {}

    /**
     * @param  list<string>  $words  the words to look for, already folded
     */
    public static function of(string $text, array $words): self
    {
        return new self(Str::squish($text), $words);
    }

    /**
     * The piece, in the order it reads.
     *
     * @return list<Fragment>
     */
    public function fragments(): array
    {
        $characters = mb_str_split($this->text);
        $total = count($characters);

        $matches = $this->matches();
        [$from, $to] = $this->window($matches, $total);

        $fragments = [];
        $cursor = $from;

        foreach ($matches as [$at, $length]) {
            $end = $at + $length;

            if ($end <= $from || $at >= $to) {
                continue;
            }

            $at = max($at, $from);
            $end = min($end, $to);

            if ($at > $cursor) {
                $fragments[] = new Fragment(self::cut($characters, $cursor, $at), false);
            }

            $fragments[] = new Fragment(self::cut($characters, $at, $end), true);
            $cursor = $end;
        }

        if ($cursor < $to) {
            $fragments[] = new Fragment(self::cut($characters, $cursor, $to), false);
        }

        if ($from > 0) {
            array_unshift($fragments, new Fragment('…', false));
        }

        if ($to < $total) {
            $fragments[] = new Fragment('…', false);
        }

        return $fragments;
    }

    /**
     * Where each word was found, by character and not by byte, with the ones
     * that overlap joined into one.
     *
     * Two words that share letters —«cita» and «citas»— land on the same run of
     * text, and marking it twice would nest one mark inside the other.
     *
     * @return list<array{int, int}>
     */
    private function matches(): array
    {
        $folded = implode('', FoldedText::characters($this->text));

        $found = [];

        foreach ($this->words as $word) {
            if ($word === '') {
                continue;
            }

            $length = mb_strlen($word);
            $offset = 0;

            while (($at = mb_strpos($folded, $word, $offset)) !== false) {
                $found[] = [$at, $length];
                $offset = $at + 1;
            }
        }

        usort($found, static fn (array $one, array $other): int => $one[0] <=> $other[0]);

        $merged = [];

        foreach ($found as [$at, $length]) {
            $last = count($merged) - 1;

            if ($last >= 0 && $at < $merged[$last][0] + $merged[$last][1]) {
                [$from, $reach] = $merged[$last];

                $merged[$last] = [$from, max($reach, $at + $length - $from)];

                continue;
            }

            $merged[] = [$at, $length];
        }

        return $merged;
    }

    /**
     * Which stretch of the text the piece shows: the one around the first
     * match, or the beginning when nothing matched.
     *
     * @param  list<array{int, int}>  $matches
     * @return array{int, int}
     */
    private function window(array $matches, int $total): array
    {
        if ($total <= self::LENGTH) {
            return [0, $total];
        }

        $first = $matches === [] ? 0 : $matches[0][0];

        $to = min($total, max(0, $first - self::LEAD) + self::LENGTH);

        return [max(0, $to - self::LENGTH), $to];
    }

    /**
     * @param  list<string>  $characters
     */
    private static function cut(array $characters, int $from, int $to): string
    {
        return implode('', array_slice($characters, $from, $to - $from));
    }
}
