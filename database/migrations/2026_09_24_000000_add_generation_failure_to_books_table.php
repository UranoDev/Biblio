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
            // When the queue gave up on what the AI writes for this Book, and
            // what the provider said last (BIB-33). Without this, a Book whose
            // generation is waiting its turn and one whose generation failed
            // look exactly alike: neither has Categories.
            $table->timestamp('generation_failed_at')->nullable()->after('description_ai_model');
            $table->string('generation_failure')->nullable()->after('generation_failed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['generation_failed_at', 'generation_failure']);
        });
    }
};
