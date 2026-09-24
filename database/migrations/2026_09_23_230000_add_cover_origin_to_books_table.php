<?php

use App\Enums\CoverOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // Where the Cover came from (ADR-008). It is indexed because the
            // generated ones are a work queue that gets listed and counted, not
            // an occasional filter: they are the Books left to photograph.
            $table->string('cover_origin')
                ->default(CoverOrigin::Generated->value)
                ->after('cover_thumbnail_path')
                ->index();
        });

        // A Book that already had a stored Cover got it from a Source: the
        // manual upload arrived with this column.
        DB::table('books')
            ->whereNotNull('cover_path')
            ->update(['cover_origin' => CoverOrigin::Source->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['cover_origin']);
            $table->dropColumn('cover_origin');
        });
    }
};
