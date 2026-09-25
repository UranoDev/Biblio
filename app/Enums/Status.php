<?php

namespace App\Enums;

/**
 * The five possible situations a Book can be in.
 *
 * Wanted and Owned are shelf situations; Reading, Read and Owned are the
 * reading states of an Owned book. The four states are exclusive and stored
 * in one column — BIB-59 explains why a fifth flag was dropped in favour of
 * this unified enum.
 */
enum Status: string
{
    case Owned = 'owned';
    case Wanted = 'wanted';
    case Reading = 'reading';
    case Read = 'read';

    /**
     * The reading filter token for «¿lo leí?» — both dated and undated.
     * Coincides with Read->value on purpose: the filter and the state are the
     * same question when the date is not what is being asked.
     */
    public const ANY_READ = 'read';

    /** Sub-filter token for Read books that carry no date. */
    public const READ_UNDATED = 'read_undated';

    /** Sub-filter token for Read books that carry a date. */
    public const READ_DATED = 'read_dated';

    /** Whether the Book lives on the Shelf (bought, or being read, or read). */
    public function isOnShelf(): bool
    {
        return $this !== self::Wanted;
    }

    /** Whether the Book was finished — not being read right now. */
    public function isRead(): bool
    {
        return $this === self::Read;
    }

    /** Whether the Book is currently being read. */
    public function isReading(): bool
    {
        return $this === self::Reading;
    }

    /** Label for the Shelf tabs and the saveShelving notice. */
    public function label(): string
    {
        return match ($this) {
            self::Owned   => 'Acervo',
            self::Wanted  => 'Por comprar',
            self::Reading => 'Lo estoy leyendo',
            self::Read    => 'Leído',
        };
    }

    /**
     * The state as the mark above the title says it, with the year when known.
     *
     * Given no year — because there is none, or because the surface does not
     * get to know it — Read says «Leído» (which is the same answer ReadUndated
     * and ReadDated gave before the unification).
     */
    public function markLabel(?int $year = null): string
    {
        return match (true) {
            $this === self::Wanted  => 'Por comprar',
            $this === self::Owned   => 'Por leer',
            $this === self::Reading => 'Lo estoy leyendo',
            $year === null          => 'Leído',
            default                 => 'Leído, '.$year,
        };
    }

    /**
     * The Reading as the Showcase says it — without the year, which does not
     * leave the back office (ADR-003).
     */
    public function publicLabel(): string
    {
        return $this->markLabel();
    }

    /**
     * Options for the reading filter on the Shelf, from the widest answer to
     * the narrowest.
     *
     * @return array<string, string>
     */
    public static function readingFilterOptions(): array
    {
        return [
            self::Owned->value   => 'Por leer',
            self::Reading->value => 'Lo estoy leyendo',
            self::ANY_READ       => 'Leído',
            self::READ_UNDATED   => 'Leído, sin fecha',
            self::READ_DATED     => 'Leído, con fecha',
        ];
    }
}
