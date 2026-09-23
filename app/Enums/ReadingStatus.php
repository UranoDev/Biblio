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

    public function label(): string
    {
        return match ($this) {
            self::Unread => 'No leído',
            self::ReadUndated => 'Leído, sin fecha',
            self::ReadDated => 'Leído',
        };
    }

    public function isRead(): bool
    {
        return $this !== self::Unread;
    }
}
