<?php

namespace App\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * The agent that proposes the Categories of a Book and, when no Source brought
 * one, writes its Description (ADR-006, ADR-007).
 *
 * Its output is structured: a list of Categories and, when it was asked for it,
 * a Description. Free text would have to be interpreted afterwards, and
 * interpreting is where a Category called "Novela (ficción literaria mexicana)"
 * comes from.
 *
 * The instructions are in English like the rest of the code —nobody using the
 * system reads them—, and what the agent answers is asked for in Spanish,
 * because that does get read: the Categories are how the Showcase is navigated
 * and the Description is published there.
 */
final class BookAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  bool  $withDescription  whether to also ask for the Description,
     *                                 which only happens when no Source brought one (ADR-007).
     */
    public function __construct(public bool $withDescription = false) {}

    public function instructions(): string
    {
        $instructions = [
            'You are cataloguing a personal library. Given the bibliographic record of one edition, propose the subjects it belongs to.',
            'Assign one to three categories from the provided list. Only propose a new category when the book genuinely does not fit any of them — that should be the exception, not the rule.',
            'Each category is a short noun phrase.',
            'Answer in Spanish as it is spoken in Mexico. The owner of the library reads it and it is published on a public page.',
        ];

        if ($this->withDescription) {
            $instructions[] = 'Write the description too: what the book is about, two or three sentences, in the tone of a back cover. Do not review it and do not invent a plot you are not sure of.';
        }

        return implode("\n\n", $instructions);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $fields = [
            'categories' => $schema->array()
                ->items($schema->string())
                ->min(1)
                ->max(3)
                ->description('The subjects the book belongs to, in Spanish, reusing the existing ones whenever they fit.')
                ->required(),
        ];

        if ($this->withDescription) {
            $fields['description'] = $schema->string()
                ->description('What the book is about, in Spanish, two or three sentences.')
                ->required();
        }

        return $fields;
    }
}
