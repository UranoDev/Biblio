<?php

namespace App\Ai;

use App\Enums\ModelRole;

/**
 * One of the AiModels a Provider names, with what it is named for.
 *
 * A Provider often points at the same model for more than one role —the default
 * of Gemini is also its smartest—, and on the screen that is one option with
 * both labels.
 */
final readonly class SuggestedAiModel
{
    /**
     * @param  list<ModelRole>  $roles
     */
    public function __construct(
        public string $name,
        public array $roles,
    ) {}

    /** What this model is named for, for the option on the screen. */
    public function rolesLabel(): string
    {
        return implode(' · ', array_map(
            static fn (ModelRole $role): string => $role->label(),
            $this->roles,
        ));
    }
}
