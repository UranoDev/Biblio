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
        Schema::table('books', function (Blueprint $table) {
            // The provider and the AiModel the request travelled with, written
            // when the Book was queued and kept when the queue gave up on it
            // (BIB-46). Without them the sheet of a Book that failed last night
            // can only name the AiModel chosen today, which is not the one that
            // failed and sends whoever reads it to look at the wrong provider.
            $table->string('generation_provider')->nullable()->after('generation_failure');
            $table->string('generation_ai_model')->nullable()->after('generation_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['generation_provider', 'generation_ai_model']);
        });
    }
};
