<?php

namespace App\Enums;

/**
 * Whether the Description that is stored reads as Spanish or as something else
 * (BIB-45).
 *
 * It is not a catalogue of languages and it is not trying to be one: the only
 * question the system asks about a Description is whether it is in the language
 * of the interface. What is not, gets translated; what is, is left alone and
 * does not cost a request.
 */
enum DescriptionLanguage: string
{
    case Spanish = 'es';

    /**
     * Not Spanish. Which language it actually is does not get stored, because
     * nothing is ever decided from it: the translation is asked for into
     * Spanish and the model reads the original itself.
     */
    case Other = 'other';

    public function isSpanish(): bool
    {
        return $this === self::Spanish;
    }
}
