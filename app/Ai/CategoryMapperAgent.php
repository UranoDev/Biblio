<?php

namespace App\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Asks the AI to map one non-canonical category to the best canonical match.
 *
 * Given the category name and a sample of its books, it returns the exact name
 * of the canonical category that fits best, or "ninguna" when none does. Used
 * by categories:auto-merge to clean up the pre-BIB-68 vocabulary.
 */
final class CategoryMapperAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return implode("\n\n", [
            'You are a librarian merging an ad-hoc book category into a canonical taxonomy.',
            'You will be given a category name and a list of books that belong to it.',
            'Return the EXACT name of the best-fitting canonical category from the list in the prompt.',
            'Return the string "ninguna" only when the books genuinely do not fit any category in the list.',
            'Never invent names. Use only the exact strings provided in the canonical list.',
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'canonical' => $schema->string()
                ->description(
                    'The exact name from the canonical list that best matches the given category and its books, '
                    .'or the literal string "ninguna" if none fits well.'
                )
                ->required(),
        ];
    }
}
