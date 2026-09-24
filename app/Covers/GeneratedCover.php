<?php

namespace App\Covers;

use App\Models\Book;

/**
 * The Cover of a Book no Source gave one for: a typographic piece with the
 * title and the Authors over a flat field of colour (ADR-008).
 *
 * It is a Cover in its own right and not a hole with a nice frame: it takes the
 * same place in the grid, in the sheet and in the Showcase. It never gets
 * confused with a real one either, and that is on purpose twice over — it is
 * typographic and not photographic, so the eye tells them apart, and it carries
 * `CoverOrigin::Generated`, so the data does too.
 *
 * Nothing here touches the disk. The piece is laid out in the proportion of a
 * book and drawn as an SVG, so the same measurements serve the grid of the
 * Shelf, the sheet and the card of a Quote without a file per size.
 */
final class GeneratedCover
{
    /** The proportion of a book, and the units every measurement below is in. */
    public const WIDTH = 400;

    public const HEIGHT = 600;

    /** The side margin, which is also where the text column starts. */
    public const MARGIN = 40;

    /** The baseline of the last line of text, measured from the top. */
    private const BOTTOM = 544;

    /** What separates the title block from the Authors block. */
    private const GAP = 44;

    private const AUTHORS_SIZE = 15;

    private const AUTHORS_LINE_HEIGHT = 22;

    private const AUTHORS_TRACKING = 0.2;

    private const AUTHORS_MAX_LINES = 2;

    /**
     * The sizes the title is tried at, from the one it reads best in, each with
     * how many lines it is allowed at that size. A title that does not fit in
     * the last one is cut, never spilled.
     *
     * @var array<int, array{int, int}>
     */
    private const TITLE_STEPS = [[38, 4], [32, 5], [27, 6]];

    private const TITLE_LINE_HEIGHT = 1.22;

    /** The same tracking the title of a Book carries everywhere else. */
    private const TITLE_TRACKING = -0.01;

    /** The characters that take up clearly less room than the average. */
    private const NARROW = " .,;:!|'’·-–()[]/iíïjltfIÍ";

    /** And the ones that take up clearly more. */
    private const WIDE = 'mwMWQ@—…';

    /**
     * The field colours, taken from the visual direction and not from a random
     * hue function: they have to sit next to the real Covers that do arrive.
     *
     * They are the cloth and board tones of the Marginalia palette — every one
     * of them pushed warm, like the rest of its neutrals — and all of them
     * light enough for the Ink to read over them.
     * tests/Unit/Covers/GeneratedCoverTest.php measures both against the tokens
     * of the stylesheet.
     *
     * @var list<string>
     */
    private const FIELDS = [
        '#c8b99c',
        '#d2c2a7',
        '#bfa88f',
        '#bdb9a6',
        '#d3b9a3',
        '#b9a492',
        '#cabbab',
        '#ad9c86',
        '#c9ae8c',
        '#b8ae9a',
    ];

    /** @var list<string>|null */
    private ?array $titleLines = null;

    private int $titleSize = 0;

    /** @param  list<string>  $authors */
    private function __construct(
        private readonly string $title,
        private readonly array $authors,
        private readonly string $seed,
    ) {}

    /**
     * The piece of a Book. The colour is derived from the ISBN, and from the
     * title when there is none: a Book out of ManualEntry has no barcode and
     * still has to keep its colour across reloads.
     */
    public static function for(Book $book): self
    {
        return self::of($book->title, $book->authors, $book->isbn);
    }

    /**
     * The piece of something that is not a Book yet, such as an edition offered
     * in a TitleLookup.
     *
     * @param  list<string>  $authors
     */
    public static function of(string $title, array $authors = [], ?string $seed = null): self
    {
        $title = trim($title);

        $credited = array_values(array_filter(
            array_map(fn (string $author): string => trim($author), $authors),
            fn (string $author): bool => $author !== '',
        ));

        return new self(
            $title === '' ? 'Sin título' : $title,
            $credited,
            $seed === null || trim($seed) === '' ? $title : trim($seed),
        );
    }

    /**
     * The colour of the field, the same one for this Book on every run.
     *
     * It is a hash and not a random number on purpose: a grid of two hundred
     * Covers that changes colours on every reload reads as broken, not as
     * varied.
     */
    public function field(): string
    {
        $number = (int) hexdec(substr(hash('sha256', $this->seed), 0, 8));

        return self::FIELDS[$number % count(self::FIELDS)];
    }

    /**
     * Every colour a field can be, which is what the tests measure.
     *
     * @return list<string>
     */
    public static function fields(): array
    {
        return self::FIELDS;
    }

    /**
     * The title, already broken into the lines it is drawn on.
     *
     * @return list<string>
     */
    public function titleLines(): array
    {
        $this->layOutTheTitle();

        return $this->titleLines ?? [];
    }

    /** The size the title ended up being drawn at. */
    public function titleSize(): int
    {
        $this->layOutTheTitle();

        return $this->titleSize;
    }

    /**
     * The Authors, broken into the lines they are drawn on, in the uppercase
     * the direction sets for them. The case is applied here and not on the
     * screen because an uppercase letter is wider, and measuring one string to
     * then draw another is how a line ends up sticking out.
     *
     * Empty when the Book has no Authors, which happens in ManualEntry and
     * leaves the title alone on the piece.
     *
     * @return list<string>
     */
    public function authorLines(): array
    {
        if ($this->authors === []) {
            return [];
        }

        return $this->cut(
            $this->wrap(mb_strtoupper(implode(', ', $this->authors)), self::AUTHORS_SIZE, self::AUTHORS_TRACKING),
            self::AUTHORS_MAX_LINES,
            self::AUTHORS_SIZE,
            self::AUTHORS_TRACKING,
        );
    }

    /** The baseline of each line of the title, measured from the top. */
    public function titleBaseline(int $line): float
    {
        $step = round($this->titleSize() * self::TITLE_LINE_HEIGHT, 2);
        $last = count($this->titleLines()) - 1;

        return round($this->titleBottom() - ($last - $line) * $step, 2);
    }

    /** The baseline of each line of the Authors. */
    public function authorBaseline(int $line): float
    {
        $last = count($this->authorLines()) - 1;

        return self::BOTTOM - ($last - $line) * self::AUTHORS_LINE_HEIGHT;
    }

    /**
     * The hairline over the title. It is what keeps the piece from reading as a
     * caption floating on a rectangle, and it is the one thing on it that is
     * not type.
     */
    public function ruleBaseline(): float
    {
        return round($this->titleBaseline(0) - $this->titleSize() - 30, 2);
    }

    public function authorsSize(): int
    {
        return self::AUTHORS_SIZE;
    }

    /** The tracking of the title, in units: SVG does not take ems here. */
    public function titleTracking(): float
    {
        return round(self::TITLE_TRACKING * $this->titleSize(), 2);
    }

    /** The tracking of the Authors, in units and not in ems: SVG wants it so. */
    public function authorsTracking(): float
    {
        return round(self::AUTHORS_TRACKING * self::AUTHORS_SIZE, 2);
    }

    /**
     * How wide the widest line of the piece comes out, in its own units.
     *
     * It is public because it is what says whether the piece holds together: a
     * line wider than the column is a title spilling over the margin, and there
     * is no way to see that from the outside otherwise.
     */
    public function widestLine(): float
    {
        $widest = 0.0;

        foreach ($this->titleLines() as $line) {
            $widest = max($widest, $this->widthOf($line, $this->titleSize()));
        }

        foreach ($this->authorLines() as $line) {
            $widest = max($widest, $this->widthOf($line, self::AUTHORS_SIZE, self::AUTHORS_TRACKING));
        }

        return round($widest, 2);
    }

    /** The width of the text column: the piece minus its two margins. */
    public static function column(): int
    {
        return self::WIDTH - 2 * self::MARGIN;
    }

    /** What a screen reader is told the piece is. */
    public function label(): string
    {
        return $this->authors === []
            ? "Portada de {$this->title}"
            : "Portada de {$this->title}, de ".implode(', ', $this->authors);
    }

    /** The baseline of the last line of the title. */
    private function titleBottom(): float
    {
        if ($this->authorLines() === []) {
            return self::BOTTOM;
        }

        return $this->authorBaseline(0) - self::GAP;
    }

    /**
     * Picks the size the title is drawn at: the first one it fits in whole, and
     * the smallest one cut short when it fits in none.
     */
    private function layOutTheTitle(): void
    {
        if ($this->titleLines !== null) {
            return;
        }

        foreach (self::TITLE_STEPS as [$size, $maximum]) {
            $lines = $this->wrap($this->title, $size);

            if (count($lines) <= $maximum) {
                $this->titleSize = $size;
                $this->titleLines = $lines;

                return;
            }
        }

        [$size, $maximum] = self::TITLE_STEPS[count(self::TITLE_STEPS) - 1];

        $this->titleSize = $size;
        $this->titleLines = $this->cut($this->wrap($this->title, $size), $maximum, $size);
    }

    /**
     * Breaks the text into lines that fit the column. A word longer than the
     * whole column —they exist; a URL inside a title is one— gets split instead
     * of left sticking out.
     *
     * @return list<string>
     */
    private function wrap(string $text, int $size, float $tracking = 0.0): array
    {
        $column = self::column();

        /** @var list<string> $lines */
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            if ($word === '') {
                continue;
            }

            $attempt = $current === '' ? $word : "{$current} {$word}";

            if ($this->widthOf($attempt, $size, $tracking) <= $column) {
                $current = $attempt;

                continue;
            }

            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }

            while ($this->widthOf($word, $size, $tracking) > $column) {
                $piece = '';

                foreach (mb_str_split($word) as $character) {
                    if ($this->widthOf($piece.$character, $size, $tracking) > $column) {
                        break;
                    }

                    $piece .= $character;
                }

                if ($piece === '') {
                    break;
                }

                $lines[] = $piece;
                $word = mb_substr($word, mb_strlen($piece));
            }

            $current = $word;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines === [] ? [$text] : $lines;
    }

    /**
     * Leaves the text at the allowed number of lines, with the last one ending
     * in an ellipsis so it reads as cut and not as finished.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function cut(array $lines, int $maximum, int $size, float $tracking = 0.0): array
    {
        if (count($lines) <= $maximum) {
            return $lines;
        }

        $lines = array_slice($lines, 0, $maximum);
        $last = (string) array_pop($lines);
        $column = self::column();

        while ($last !== '' && $this->widthOf($last.'…', $size, $tracking) > $column) {
            $last = rtrim(mb_substr($last, 0, mb_strlen($last) - 1));
        }

        $lines[] = $last.'…';

        return $lines;
    }

    /**
     * How wide the text is going to come out, in the same units as the piece.
     *
     * It is an estimate, and deliberately one: measuring it for real means
     * parsing the font file, and what this is for is deciding where to break a
     * line. The estimate errs wide, so a line that was going to graze the
     * margin breaks one word earlier instead of sticking out.
     */
    private function widthOf(string $text, int $size, float $tracking = 0.0): float
    {
        $width = 0.0;

        foreach (mb_str_split($text) as $character) {
            $width += $this->factorOf($character) + $tracking;
        }

        return $width * $size;
    }

    /** How wide one character is, as a share of the font size. */
    private function factorOf(string $character): float
    {
        if (mb_strpos(self::NARROW, $character) !== false) {
            return 0.34;
        }

        if (mb_strpos(self::WIDE, $character) !== false) {
            return 0.92;
        }

        return mb_strtolower($character) === $character ? 0.56 : 0.70;
    }
}
