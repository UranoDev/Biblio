<?php

namespace App\Enums;

/**
 * El catálogo público del que vino la Ficha, o la Captura manual cuando
 * ninguno conocía el Libro (ADR-002).
 */
enum Fuente: string
{
    case OpenLibrary = 'open_library';
    case GoogleBooks = 'google_books';
    case Manual = 'manual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::OpenLibrary => 'Open Library',
            self::GoogleBooks => 'Google Books',
            self::Manual => 'Captura manual',
        };
    }
}
