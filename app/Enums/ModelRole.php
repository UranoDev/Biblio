<?php

namespace App\Enums;

/**
 * What an AiModel is the best one at, among the ones its Provider names.
 *
 * The SDK does not list every model a Provider has: it names three of them, and
 * those are the ones the Settings screen offers by name. Any other model is
 * typed in by hand.
 */
enum ModelRole: string
{
    case Default = 'default';
    case Cheapest = 'cheapest';
    case Smartest = 'smartest';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'el que trae por omisión',
            self::Cheapest => 'el más económico',
            self::Smartest => 'el más capaz',
        };
    }
}
