<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The chosen provider and AiModel, a single row. API keys are not stored
        // here: they live in the environment (ADR-006).
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
