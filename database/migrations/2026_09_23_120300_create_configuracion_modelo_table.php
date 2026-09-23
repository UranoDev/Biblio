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
        // El proveedor y el Modelo elegidos, una sola fila. Las llaves de API
        // no se guardan aquí: viven en el entorno (ADR-006).
        Schema::create('configuracion_modelo', function (Blueprint $table) {
            $table->id();
            $table->string('proveedor');
            $table->string('modelo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracion_modelo');
    }
};
