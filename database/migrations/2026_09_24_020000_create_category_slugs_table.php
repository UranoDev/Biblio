<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The slugs a Category used to have.
     *
     * A Category address is shared: it goes into a message, into a bookmark,
     * into whatever indexed it. Renaming «Ciencia ficcion» to «Ciencia ficción»
     * moves the slug, and without this table every address that was already out
     * there answers a 404. So the old slug is kept, pointing at the Category
     * that has it now, and the Showcase redirects to the current one (BIB-17).
     *
     * A merged Category leaves its slug here too: the address of what was
     * absorbed lands on what absorbed it.
     */
    public function up(): void
    {
        Schema::create('category_slugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_slugs');
    }
};
