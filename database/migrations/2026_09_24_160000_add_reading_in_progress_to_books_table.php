<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El cuarto estado de la lectura: «Lo estoy leyendo» (BIB-59).
 *
 * Los otros tres se deducen de `is_read` y `read_on`, pero el libro en curso
 * no se deduce de nada: no está leído y tampoco es de los que no se ha
 * empezado. Necesita señal propia, y por eso es una columna y no una lectura
 * distinta de las dos que ya había.
 *
 * Es la que manda: con `is_reading` en 1 el libro está en curso sin importar
 * qué digan las otras dos, así que los cuatro estados siguen siendo
 * excluyentes entre sí. Ningún libro ya registrado nace en curso — eso se
 * marca a mano, libro por libro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->boolean('is_reading')->default(false)->after('read_on');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropColumn('is_reading');
        });
    }
};
