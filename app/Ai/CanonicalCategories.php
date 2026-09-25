<?php

namespace App\Ai;

/**
 * The fixed vocabulary the AI must choose from when cataloguing a Book (BIB-68).
 *
 * Keeping this list here — and not in a config file or the database — means
 * that any change to it is a code change, reviewed and versioned, not a silent
 * database edit that nobody notices until the Showcase starts showing something
 * odd.
 */
final class CanonicalCategories
{
    /** @var list<string> */
    public const LIST = [
        'Novela',
        'Cuento',
        'Poesía',
        'Biografía y memorias',
        'Historia',
        'Filosofía',
        'Teología y Biblia',
        'Religión y espiritualidad',
        'Psicología',
        'Autoayuda y desarrollo personal',
        'Educación',
        'Sociología y política',
        'Economía',
        'Finanzas personales',
        'Emprendimiento',
        'Administración y liderazgo',
        'Marketing y ventas',
        'Programación',
        'Ingeniería de software',
        'Ciencia de datos',
        'Tecnología',
        'Diseño',
        'Ciencia',
        'Matemáticas',
        'Medicina y salud',
        'Derecho',
        'Arte y cultura',
        'Idiomas',
        'Viajes y geografía',
        'Humor',
    ];
}
