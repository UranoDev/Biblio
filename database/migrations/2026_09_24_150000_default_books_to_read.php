<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un Libro nace Leído, sin fecha (BIB-58).
 *
 * La captura registra libros que ya se tienen en la mano, y la inmensa mayoría
 * ya se leyeron antes de escanearlos: "No leído" como default forzaba a
 * marcar a mano el caso común. El día en que sí se leyó nunca se supo con
 * exactitud, así que no se inventa uno: se deja `read_on` en null, que es el
 * estado "Leído, sin fecha" de `ReadingStatus`.
 *
 * Los que ya estaban registrados como "No leído" se pasan también, por la
 * misma razón: la mayoría sí se había leído y el flag solo decía que nadie lo
 * había marcado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->boolean('is_read')->default(true)->change();
        });

        DB::table('books')->where('is_read', false)->update(['is_read' => true]);
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->boolean('is_read')->default(false)->change();
        });
    }
};
