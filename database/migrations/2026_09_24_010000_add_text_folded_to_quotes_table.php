<?php

use App\Search\FoldedText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The column the Search of Quotes reads: the same text, folded (BIB-16).
 *
 * It is written beside the Quote and not worked out at query time because the
 * two engines do not fold the same way -- MySQL by collation, SQLite not at
 * all -- and because a function over the column is a function the database
 * runs once per row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->text('text_folded')->nullable()->after('text');
        });

        // The Quotes that were already written down. From here on the model
        // fills the column by itself, but these were stored before it existed
        // and a Search that could not find them would look like they are gone.
        DB::table('quotes')->orderBy('id')->chunk(500, function ($quotes): void {
            foreach ($quotes as $quote) {
                DB::table('quotes')
                    ->where('id', $quote->id)
                    ->update(['text_folded' => FoldedText::of($quote->text)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('text_folded');
        });
    }
};
