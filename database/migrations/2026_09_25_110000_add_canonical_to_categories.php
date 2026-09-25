<?php

use App\Ai\CanonicalCategories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_canonical')->default(false)->after('slug');
            $table->foreignId('suggested_by_book_id')
                ->nullable()
                ->after('is_canonical')
                ->constrained('books')
                ->nullOnDelete();
        });

        // Seed the 30 canonical categories: mark existing ones as canonical
        // and create any that are not yet in the table.
        foreach (CanonicalCategories::LIST as $name) {
            $slug = Str::slug($name);

            DB::table('categories')->upsert(
                [['name' => $name, 'slug' => $slug, 'is_canonical' => true, 'created_at' => now(), 'updated_at' => now()]],
                ['slug'],
                ['is_canonical'],
            );
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suggested_by_book_id');
            $table->dropColumn('is_canonical');
        });
    }
};
