<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The provider and the AiModel chosen to write Categories and Descriptions.
 *
 * It is a single row: choosing another AiModel replaces the one there is. API
 * keys are not stored here nor in any other table — they live in the
 * environment (ADR-006).
 *
 * @property int $id
 * @property string $provider
 * @property string $model
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiSetting extends Model
{
    protected $table = 'ai_settings';

    protected $guarded = ['id'];

    /**
     * The chosen AiModel, or null while nobody has chosen one.
     */
    public static function chosen(): ?self
    {
        return static::query()->latest('id')->first();
    }

    /**
     * Store the provider and the AiModel, replacing the previous choice.
     */
    public static function choose(string $provider, string $model): self
    {
        $setting = static::chosen() ?? new self;

        $setting->provider = $provider;
        $setting->model = $model;
        $setting->save();

        return $setting;
    }
}
