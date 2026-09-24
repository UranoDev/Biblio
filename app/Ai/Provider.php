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
 *
 * One of them runs on this machine instead of behind a service, and that one
 * has no key to wait for (BIB-27). What it carries instead is the URL its
 * server is reached at.
 */
final readonly class Provider
{
    /**
     * @param  string  $name  how it is called in `config/ai.php`, which is also what gets stored
     * @param  string|null  $keyVariable  the environment variable its key is read from, when it can be named
     * @param  bool  $local  whether it runs on this machine and authenticates nothing
     * @param  string|null  $url  where its server is reached, for the ones that run on this machine
     * @param  list<SuggestedAiModel>  $models
     */
    public function __construct(
        public string $name,
        public string $label,
        public ?string $keyVariable,
        public bool $hasKey,
        public array $models,
        public bool $local = false,
        public ?string $url = null,
    ) {}

    /**
     * Whether it can be chosen on the Settings screen.
     *
     * A Provider that runs on this machine authenticates nothing, so there is
     * no key it could be waiting for: it can always be chosen. Whether its
     * server is up at this moment is a separate question, and one that does not
     * settle anything — it can be turned on afterwards.
     */
    public function canBeChosen(): bool
    {
        return $this->local || $this->hasKey;
    }

    /** @return list<string> */
    public function modelNames(): array
    {
        return array_map(
            static fn (SuggestedAiModel $model): string => $model->name,
            $this->models,
        );
    }
}
