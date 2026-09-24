<?php

namespace App\Ai;

use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;

/**
 * What the AI answered when it was asked to put a catalogue Description into
 * Spanish (BIB-45), together with which Provider and AiModel answered.
 *
 * Who translated it is kept for the same reason as who wrote a Description: it
 * is what later makes it precise to redo only what was left on the old AiModel,
 * instead of paying for all two hundred Books again (ADR-006).
 */
final readonly class Translation
{
    public function __construct(
        public string $description,
        public string $provider,
        public string $aiModel,
    ) {}

    /**
     * Reads the structured answer.
     *
     * Returns null when it carries no text or when it is not known which
     * AiModel answered: nothing gets written, the Description stays as the
     * catalogue brought it, and the translation can be asked for again.
     */
    public static function from(AgentResponse $response): ?self
    {
        if (! $response instanceof StructuredAgentResponse) {
            return null;
        }

        $description = $response->structured['description'] ?? null;

        if (! is_string($description) || trim($description) === '') {
            return null;
        }

        $provider = $response->meta->provider;
        $aiModel = $response->meta->model;

        if ($provider === null || $aiModel === null) {
            return null;
        }

        return new self(trim($description), $provider, $aiModel);
    }
}
