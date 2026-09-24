<?php

namespace App\Enums;

/**
 * What a regeneration asks the AI for again: the Categories of a Book, its
 * Description, or both (ADR-006, ADR-007).
 *
 * The three are not interchangeable. Categories can always be written again;
 * a Description only when the AI wrote the one there is, because the one from
 * the Source and mine are never overwritten.
 */
enum RegenerationTarget: string
{
    case Categories = 'categories';

    case Descriptions = 'descriptions';

    case Both = 'both';

    public function includesCategories(): bool
    {
        return $this !== self::Descriptions;
    }

    public function includesDescriptions(): bool
    {
        return $this !== self::Categories;
    }

    public function label(): string
    {
        return match ($this) {
            self::Categories => 'Sólo las categorías',
            self::Descriptions => 'Sólo las descripciones',
            self::Both => 'Las categorías y las descripciones',
        };
    }

    /**
     * The same option offered on one Book, where the Description is one and not
     * two hundred (BIB-22).
     */
    public function forOneBook(): string
    {
        return match ($this) {
            self::Categories => 'Sólo las categorías',
            self::Descriptions => 'Sólo la descripción',
            self::Both => 'Las categorías y la descripción',
        };
    }

    /**
     * What one Book is waiting for, in a clause that goes after «volvió a la
     * cola:».
     */
    public function queuedOnOneBook(): string
    {
        return match ($this) {
            self::Categories => 'sus categorías se van a escribir otra vez',
            self::Descriptions => 'su descripción se va a escribir otra vez',
            self::Both => 'sus categorías y su descripción se van a escribir otra vez',
        };
    }

    /**
     * How the running regeneration says what it is asking for, in a sentence
     * that goes after «Se están pidiendo».
     */
    public function running(): string
    {
        return match ($this) {
            self::Categories => 'las categorías',
            self::Descriptions => 'las descripciones',
            self::Both => 'las categorías y las descripciones',
        };
    }
}
