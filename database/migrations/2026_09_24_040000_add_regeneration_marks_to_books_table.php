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
            // Since when the Book is waiting its turn in the queue (BIB-22).
            // It cannot be deduced from what it already has: a Book whose
            // Categories were written months ago and that was just queued again
            // looks, column by column, exactly like one nobody asked about.
            $table->timestamp('generation_queued_at')->nullable()->after('description_ai_model');

            // Since when the Categories of the Book were corrected by hand.
            // What it buys is the warning before a regeneration replaces them:
            // the AI overwrites its own work without asking, and mine only
            // after a yes.
            $table->timestamp('categories_edited_at')->nullable()->after('categories_ai_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['generation_queued_at', 'categories_edited_at']);
        });
    }
};
