<?php

namespace Tests\Feature\Domain;

use App\Models\AiSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_at_first_there_is_no_ai_model_chosen(): void
    {
        $this->assertNull(AiSetting::chosen());
    }

    public function test_it_stores_the_chosen_provider_and_ai_model(): void
    {
        AiSetting::choose('gemini', 'gemini-2.5-flash');

        $chosen = AiSetting::chosen();

        $this->assertNotNull($chosen);
        $this->assertSame('gemini', $chosen->provider);
        $this->assertSame('gemini-2.5-flash', $chosen->model);
    }

    public function test_choosing_another_ai_model_replaces_the_previous_one(): void
    {
        AiSetting::choose('gemini', 'gemini-2.5-flash');
        AiSetting::choose('deepseek', 'deepseek-chat');

        $chosen = AiSetting::chosen();

        $this->assertSame(1, AiSetting::count());
        $this->assertNotNull($chosen);
        $this->assertSame('deepseek', $chosen->provider);
        $this->assertSame('deepseek-chat', $chosen->model);
    }

    public function test_no_api_key_is_stored_in_the_database(): void
    {
        $tables = ['ai_settings', 'books', 'quotes', 'categories'];

        foreach ($tables as $table) {
            foreach (Schema::getColumnListing($table) as $column) {
                $this->assertFalse(
                    Str::contains($column, ['llave', 'key', 'api', 'token', 'secret'], ignoreCase: true),
                    "La columna [{$table}.{$column}] parece guardar una llave de API; las llaves viven en el entorno."
                );
            }
        }
    }
}
