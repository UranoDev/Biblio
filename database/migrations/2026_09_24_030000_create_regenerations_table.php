<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per mass regeneration launched from the Settings screen (BIB-21).
 *
 * The progress itself lives in `job_batches`, which is where the queue keeps
 * it; this table only says which batch was the regeneration and what it was
 * asked for. That is what makes the progress survive reloading the page: the
 * screen finds it here instead of holding it in the browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regenerations', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->nullable();
            $table->string('target');
            $table->boolean('only_previous')->default(false);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('kept')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regenerations');
    }
};
