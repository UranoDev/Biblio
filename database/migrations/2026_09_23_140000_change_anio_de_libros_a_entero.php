<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El año del Libro deja de ser un YEAR de MySQL, que sólo guarda de 1901
     * en adelante. La Captura manual existe justamente para los libros viejos
     * —los que no traen código de barras—, y un libro de 1890 no entraba.
     */
    public function up(): void
    {
        Schema::table('libros', function (Blueprint $table) {
            $table->smallInteger('anio')->unsigned()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('libros', function (Blueprint $table) {
            $table->year('anio')->nullable()->change();
        });
    }
};
