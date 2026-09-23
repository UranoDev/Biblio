<?php

namespace App\Enums;

/**
 * The public catalog the Record came from, or ManualEntry when none of them
 * knew the Book (ADR-002).
 */
enum Source: string
{
    case OpenLibrary = 'open_library';
    case GoogleBooks = 'google_books';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::OpenLibrary => 'Open Library',
            self::GoogleBooks => 'Google Books',
            self::Manual => 'Captura manual',
        };
    }
}
