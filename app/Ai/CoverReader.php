<?php

namespace App\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * The agent that reads the photograph of a Cover and says what is printed on
 * it (BIB-73).
 *
 * It reads, it does not catalogue: what is not legible on the photograph comes
 * back empty, because a made-up publisher typed into the form is worse than a
 * blank one the owner fills in. The form is reviewed before registering, so an
 * empty field costs a few keystrokes and a wrong one costs a wrong Book.
 */
final class CoverReader implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return implode("\n\n", [
            'You are reading the photograph of the front cover of a book, to fill in a catalogue form.',
            'Write down only what is printed and legible on the cover. Never guess and never complete from memory: if something is not on the cover or cannot be read, leave it as an empty string.',
            'Keep the title and the names exactly as printed, with their accents, in the language they are printed in. Do not translate them. Put a subtitle after the title, separated by a colon, only when the cover prints one.',
            'The ISBN is only on the cover when its digits are printed there; most covers do not carry it.',
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()
                ->description('The title as printed on the cover, with its subtitle after a colon when there is one. Empty if not legible.')
                ->required(),
            'authors' => $schema->array()
                ->items($schema->string())
                ->description('The authors as printed on the cover, in the order they appear. Empty if none is legible.')
                ->required(),
            'publisher' => $schema->string()
                ->description('The publisher or imprint printed on the cover. Empty if there is none.')
                ->required(),
            'isbn' => $schema->string()
                ->description('The ISBN digits if they are printed on the cover. Empty otherwise.')
                ->required(),
        ];
    }
}
