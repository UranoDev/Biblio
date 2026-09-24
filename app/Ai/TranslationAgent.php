<?php

namespace App\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * The agent that puts into Spanish the Description a catalogue brought in
 * another language (BIB-45).
 *
 * It is a separate agent from the one that proposes Categories because it does
 * a different job with a different rule: that one writes what it knows about
 * the book, this one may not add a single thing that was not in front of it.
 * The description belongs to the publisher or to the catalogue — it says what
 * they said, in Spanish, and this is not the occasion to improve it or to
 * shorten it.
 *
 * What it is given besides the text are the Categories of the Book, because a
 * translation that does not know the trade gets the words wrong: «software
 * construction» is not «construcción de software» in the mouth of somebody who
 * programs, and a description of a book on sales, on theology or on finance
 * each has its own jargon.
 *
 * The instructions are in English like the rest of the code; what it answers is
 * Spanish, because that is what gets published.
 */
final class TranslationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return implode("\n\n", [
            'You translate the catalogue description of a book into Spanish as it is spoken in Mexico.',
            'Translate. Do not summarise, do not expand, do not comment, do not correct and do not add anything that was not in the original. Every claim in your answer must be a claim the original made.',
            'Use the vocabulary of the trade the book belongs to. The subjects of the book are given to you for exactly that: a translation that reads as if it had been written by somebody who works in that field is the point, and a literal word-for-word rendering is not.',
            'Keep the register and the length of the original, and keep its paragraph breaks.',
            'If a term is normally left in English by the people who work in that field, leave it in English.',
            'Answer only with the translation. Do not add a note about what you did.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'description' => $schema->string()
                ->description('The description translated into Mexican Spanish, with nothing added and nothing left out.')
                ->required(),
        ];
    }
}
