<?php

namespace App\Enums;

/**
 * How passing a barcode through the Scanner ended up.
 *
 * The first three are the rescan outcomes (ADR-005) and the last three are the
 * ways of registering nothing. "Unknown ISBN" and "source unavailable" are told
 * apart because they lead to different things: the first offers ManualEntry and
 * the second asks to scan again (ADR-002).
 */
enum ScanOutcome: string
{
    case Registered = 'registered';
    case AlreadyOnShelf = 'already_on_shelf';
    case MovedToShelf = 'moved_to_shelf';
    case UnknownIsbn = 'unknown_isbn';
    case SourceUnavailable = 'source_unavailable';
    case InvalidIsbn = 'invalid_isbn';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registrado',
            self::AlreadyOnShelf => 'Ya está en el Acervo',
            self::MovedToShelf => 'Pasó al Acervo',
            self::UnknownIsbn => 'Ninguna Fuente lo conoce',
            self::SourceUnavailable => 'La Fuente no pudo contestar',
            self::InvalidIsbn => 'No es un ISBN',
        };
    }

    /** Whether the Scan left a Book on the Shelf, be it new or already registered. */
    public function hasBook(): bool
    {
        return match ($this) {
            self::Registered, self::AlreadyOnShelf, self::MovedToShelf => true,
            default => false,
        };
    }
}
