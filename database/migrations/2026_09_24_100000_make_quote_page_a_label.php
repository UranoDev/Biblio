<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The page of a Quote stops being a number and becomes the label the Book
     * prints, so that the front matter can be written down as `xiv` (BIB-42).
     *
     * The order it used to get from being an integer now comes from two derived
     * columns, and the Quotes already written keep their page: an arabic one,
     * worth what it always was.
     */
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->boolean('page_is_front_matter')->default(false)->after('page');
            $table->unsignedInteger('page_number')->nullable()->after('page_is_front_matter');
        });

        // Whatever is stored is an integer, so it is already its own value.
        DB::table('quotes')->whereNotNull('page')->update(['page_number' => DB::raw('page')]);

        Schema::table('quotes', function (Blueprint $table) {
            $table->string('page', 16)->nullable()->change();
        });
    }

    public function down(): void
    {
        // A page in roman numerals has no integer to go back to, so it loses
        // the page rather than the Quote.
        DB::table('quotes')->where('page_is_front_matter', true)->update(['page' => null]);

        Schema::table('quotes', function (Blueprint $table) {
            $table->unsignedInteger('page')->nullable()->change();
            $table->dropColumn(['page_is_front_matter', 'page_number']);
        });
    }
};
