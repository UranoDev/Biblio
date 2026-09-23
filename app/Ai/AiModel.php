<?php

namespace App\Ai;

use App\Models\AiSetting;

/**
 * Which AI writes the Categories and the Descriptions: the Provider and the
 * name of the model, as they were chosen on the Settings screen.
 *
 * It is read from `ai_settings` and never from the environment (ADR-006), so
 * that changing it is an ordinary operation and not a deploy. The API keys do
 * stay in the environment, and this object never carries one.
 */
final readonly class AiModel
{
    public function __construct(
        public string $provider,
        public string $name,
    ) {}

    /**
     * The chosen AiModel, or null while nobody has chosen one. Null is a normal
     * state: generation is off and registering a Book keeps working.
     */
    public static function chosen(): ?self
    {
        $setting = AiSetting::chosen();

        return $setting instanceof AiSetting
            ? new self($setting->provider, $setting->model)
            : null;
    }
}
