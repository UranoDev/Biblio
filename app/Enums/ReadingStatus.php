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

    /**
     * The Reading as the Showcase says it.
     *
     * Out there the read date does not exist -- it is one of the things that
     * never leaves the back office (ADR-003) -- so the two read states say the
     * same thing. Announcing «leído, sin fecha» to a visitor names a field they
     * are not being shown.
     */
    public function publicLabel(): string
    {
        return $this->isRead() ? 'Leído' : 'No leído';
    }

    public function isRead(): bool
    {
        return $this !== self::Unread;
    }
}
