<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the download of the Cover gave up, and why (BIB-52).
     *
     * `DownloadCover` gives up without raising anything —a 4xx, or an image the
     * store refuses— so the queue counts the job as done and `failed_jobs`
     * stays empty. All that was left was a `Log::warning()`, and a Book with no
     * image was indistinguishable in the database from one nobody ever asked a
     * Cover for.
     *
     * They are the same pair of marks the generation carries and the shareable
     * card carries, and they answer the same question here: a Book showing the
     * generated piece is one whose Source brought no image, or one whose
     * download was tried and failed.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->timestamp('cover_failed_at')->nullable()->after('cover_origin');
            $table->string('cover_failure')->nullable()->after('cover_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['cover_failed_at', 'cover_failure']);
        });
    }
};
