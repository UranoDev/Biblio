<?php

use App\Descriptions\TextLanguage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Description says what language it is in, and keeps the original when it
 * was translated (BIB-45).
 *
 * The language is derived from the text itself and written on every save, so
 * the column is not a second source of truth: it is the answer to a question
 * that cannot be asked in SQL, stored where the query that picks the Books for
 * a regeneration can read it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->string('description_language', 10)->nullable()->after('description_origin');

            // What the Source brought, kept word for word. A translation can be
            // made again; a text that was thrown away cannot.
            $table->text('description_original')->nullable()->after('description_language');
        });

        // The Books that are already registered: three of the seven in
        // production have their Description in English, and without this they
        // would never enter a run.
        DB::table('books')
            ->select('id', 'description')
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderBy('id')
            ->chunk(200, function ($books): void {
                foreach ($books as $book) {
                    DB::table('books')
                        ->where('id', $book->id)
                        ->update(['description_language' => TextLanguage::of($book->description)->value]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropColumn(['description_language', 'description_original']);
        });
    }
};
