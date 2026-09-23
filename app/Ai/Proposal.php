<?php

namespace App\Ai;

use Illuminate\Support\Str;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * What the AI proposed for one Book: its Categories and, when it was asked for
 * it, its Description, together with which Provider and AiModel answered.
 *
 * Keeping who answered is what later makes it precise to regenerate only what
 * was left on the old AiModel, instead of paying for all two hundred Books
 * again (ADR-006).
 */
final readonly class Proposal
{
    /**
     * @param  list<string>  $categories
     */
    public function __construct(
        public array $categories,
        public ?string $description,
        public string $provider,
        public string $aiModel,
    ) {}

    /**
     * Reads the structured answer.
     *
     * Returns null when it carries no usable Category or when it is not known
     * which AiModel answered: half an answer is not stored, so the Book stays
     * as it was and the generation can be queued again.
     */
    public static function from(AgentResponse $response): ?self
    {
        if (! $response instanceof StructuredAgentResponse) {
            return null;
        }

        $categories = self::categoriesIn($response->structured['categories'] ?? []);

        $provider = $response->meta->provider;
        $aiModel = $response->meta->model;

        if ($categories === [] || $provider === null || $aiModel === null) {
            return null;
        }

        return new self(
            $categories,
            self::descriptionIn($response->structured['description'] ?? null),
            $provider,
            $aiModel,
        );
    }

    /**
     * The names that came back, cleaned up and without repeats.
     *
     * The first letter is capitalised here and nowhere else: the same Category
     * arriving as "novela mexicana" one day and "Novela mexicana" the next is
     * the fragmentation ADR-006 is about.
     *
     * @return list<string>
     */
    private static function categoriesIn(mixed $categories): array
    {
        if (! is_array($categories)) {
            return [];
        }

        $names = [];

        foreach ($categories as $name) {
            if (! is_string($name)) {
                continue;
            }

            $name = Str::squish($name);

            if ($name !== '') {
                $names[] = Str::ucfirst($name);
            }
        }

        return array_values(array_unique($names));
    }

    private static function descriptionIn(mixed $description): ?string
    {
        if (! is_string($description)) {
            return null;
        }

        $description = trim($description);

        return $description === '' ? null : $description;
    }
}
