<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the download that gave up did so because the image was not a
     * Cover.
     *
     * The two ways a download gives up (BIB-52) do not end the same: a 4xx can
     * be a file that comes back, but an image the store refused —a marker, an
     * inner page, a flat colour— is the same image every time the same address
     * is asked. Retrying that one only gets it refused again, so the sheet
     * stops offering it.
     *
     * The Books already marked are filled in from the reason they carry: the
     * 4xx ones are the only ones whose reason was written by `giveUp()`.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->boolean('cover_refused')->default(false)->after('cover_failure');
        });

        DB::table('books')
            ->whereNotNull('cover_failed_at')
            ->where(fn ($query) => $query
                ->whereNull('cover_failure')
                ->orWhere('cover_failure', 'not like', 'La Fuente contestó%'))
            ->update(['cover_refused' => true]);
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('cover_refused');
        });
    }
};
