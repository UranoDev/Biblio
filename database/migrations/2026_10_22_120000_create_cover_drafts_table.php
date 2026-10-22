<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A photograph of a Cover that arrived from the Drive folder and waits
        // to be reviewed before its Book is registered (BIB-74). The Drive id
        // is unique: it is what keeps one photograph from becoming two drafts
        // when moving it out of the folder fails.
        Schema::create('cover_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('drive_file_id')->unique();
            $table->string('drive_file_name');
            $table->string('photo_path')->nullable();

            // What was read off the photograph, the way ManualEntry fills its form.
            $table->string('title')->nullable();
            $table->json('authors')->nullable();
            $table->string('publisher')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('isbn', 13)->nullable();
            $table->boolean('from_source')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->string('read_failure')->nullable();

            // Fetching or moving the photograph in Drive did not work.
            $table->timestamp('failed_at')->nullable();
            $table->string('failure')->nullable();

            $table->timestamps();
        });

        // When the folder was last looked at, and why the last look failed:
        // a single row, the way wishlist_settings keeps the Amazon sync.
        Schema::create('drive_imports', function (Blueprint $table) {
            $table->id();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_failure')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_imports');
        Schema::dropIfExists('cover_drafts');
    }
};
