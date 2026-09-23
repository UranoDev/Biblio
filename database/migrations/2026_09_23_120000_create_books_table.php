<?php

use App\Enums\Status;
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
        Schema::create('books', function (Blueprint $table) {
            $table->id();

            // The ISBN identifies the edition and is unique on the Shelf
            // (ADR-005). It is null only when there is none to store: old Books
            // without a barcode come in through ManualEntry.
            $table->string('isbn', 17)->nullable()->unique();

            // The Record, exactly as the Source brings it.
            $table->string('title');
            $table->json('authors');
            $table->string('publisher')->nullable();
            // Not a MySQL YEAR: that one only stores from 1901 onwards, and
            // ManualEntry exists precisely for old books —the ones without a
            // barcode—, where an 1890 book would not fit.
            $table->smallInteger('year')->unsigned()->nullable();
            $table->string('edition')->nullable();
            $table->text('description')->nullable();
            $table->string('description_origin')->nullable();
            $table->string('source')->nullable();
            $table->string('cover_path')->nullable();

            // Where the Cover is downloaded from. It is stored even if the
            // download failed: without the URL there is nothing to retry with
            // (ADR-004).
            $table->string('cover_source_url', 2048)->nullable();

            // The scaled-down version the Shelf and Showcase grids use; the
            // original is left for the Book sheet.
            $table->string('cover_thumbnail_path')->nullable();

            $table->string('status')->default(Status::Owned->value)->index();

            // Reading is three states: no flag, flag without a date, and flag
            // with a date.
            $table->boolean('is_read')->default(false);
            $table->date('read_on')->nullable();

            $table->text('note')->nullable();

            $table->string('lent_to')->nullable();
            $table->date('lent_on')->nullable();

            // A Book is born visible; Hidden is the exception (ADR-003).
            $table->boolean('is_hidden')->default(false)->index();

            // With which AiModel whatever the AI generated was generated, so it
            // is known afterwards what is left on the old AiModel (ADR-006).
            $table->string('categories_provider')->nullable();
            $table->string('categories_ai_model')->nullable();
            $table->string('description_provider')->nullable();
            $table->string('description_ai_model')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
