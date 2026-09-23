<?php

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
        Schema::table('libros', function (Blueprint $table) {
            // De dónde se descarga la Portada. Se guarda aunque la descarga
            // haya fallado: sin la URL no hay con qué reintentar (ADR-004).
            $table->string('portada_origen_url', 2048)->nullable()->after('portada_ruta');

            // La versión reducida que usan la retícula del Acervo y la
            // Vitrina; la original queda para la ficha del Libro.
            $table->string('portada_miniatura_ruta')->nullable()->after('portada_origen_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('libros', function (Blueprint $table) {
            $table->dropColumn(['portada_origen_url', 'portada_miniatura_ruta']);
        });
    }
};
