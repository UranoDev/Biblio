<?php

namespace App\Ai;

/**
 * A Provider of AI as the Settings screen shows it: how it is called, whether
 * its API key is in the environment, and which AiModels it names.
 *
 * It never carries the key itself, not even shortened: the keys live in the
 * environment and are neither stored nor shown (ADR-006). What this object
 * carries is the *name of the variable*, which is what the screen says is
 * missing.
 */
final readonly class Provider
{
    /**
     * @param  string  $name  how it is called in `config/ai.php`, which is also what gets stored
     * @param  string|null  $keyVariable  the environment variable its key is read from, when it can be named
     * @param  list<SuggestedAiModel>  $models
     */
    public function __construct(
        public string $name,
        public string $label,
        public ?string $keyVariable,
        public bool $hasKey,
        public array $models,
    ) {}

    /** @return list<string> */
    public function modelNames(): array
    {
        return array_map(
            static fn (SuggestedAiModel $model): string => $model->name,
            $this->models,
        );
    }
}
