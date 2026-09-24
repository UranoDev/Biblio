<?php

namespace App\Enums;

/**
 * The three Reading states of a Book.
 *
 * It is not stored: it is derived from the read flag and the read date. Read
 * without a date is a state of its own — a Book read years ago, without knowing
 * when, is still read.
 */
enum ReadingStatus: string
{
    case Unread = 'unread';
    case ReadUndated = 'read_undated';
    case ReadDated = 'read_dated';

    /**
     * The filter value that takes in both read states at once.
     *
     * It is not a fourth state and it is not stored: it is the question «¿lo
     * leí?», which is the one the Dashboard counts and the one somebody asks
     * when the date is not what they are after. The three states stay in the
     * filter underneath it, because telling them apart is the other question.
     */
    public const ANY_READ = 'read';

    /**
     * What the filter of the Shelf offers, from the widest answer to the
     * narrowest.
     *
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        return [
            self::Unread->value => self::Unread->label(),
            self::ANY_READ => 'Leído',
            self::ReadUndated->value => self::ReadUndated->label(),
            self::ReadDated->value => self::ReadDated->label(),
        ];
    }

    /**
     * The state as the filter of the Shelf names it, where the three have to
     * tell themselves apart from one another and nothing else is on the line.
     */
    public function label(): string
    {
        return match ($this) {
            self::Unread => 'No leído',
            self::ReadUndated => 'Leído, sin fecha',
            self::ReadDated => 'Leído, con fecha',
        };
    }

    /**
     * The state as the mark above the title says it, with the year of the
     * Reading riding in the same line.
     *
     * The year has no field of its own with its own label underneath: this line
     * answers «¿cuándo?», and the year is that answer, so it goes where the
     * answer already is. Given no year — because there is none, or because the
     * surface does not get to know it — the two read states read alike, which
     * is the whole distance between this line and the filter above.
     */
    public function markLabel(?int $year = null): string
    {
        return match (true) {
            $this === self::Unread => 'Por leer',
            $year === null => 'Leído',
            default => 'Leído, '.$year,
        };
    }

    /**
     * The Reading as the Showcase says it.
     *
     * Out there the read date does not exist -- it is one of the things that
     * never leaves the back office (ADR-003) -- so the year is not handed over
     * and the two read states say the same thing.
     */
    public function publicLabel(): string
    {
        return $this->markLabel();
    }

    public function isRead(): bool
    {
        return $this !== self::Unread;
    }
}
