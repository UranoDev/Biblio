<?php

use App\Enums\Situacion;
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
        Schema::create('libros', function (Blueprint $table) {
            $table->id();

            // El ISBN identifica la edición y es único en el acervo (ADR-005).
            // Es nulo únicamente cuando no hay ninguno que guardar: los Libros
            // viejos sin código de barras entran por Captura manual.
            $table->string('isbn', 17)->nullable()->unique();

            // La Ficha, tal como la trae la Fuente.
            $table->string('titulo');
            $table->json('autores');
            $table->string('editorial')->nullable();
            $table->year('anio')->nullable();
            $table->string('edicion')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('descripcion_procedencia')->nullable();
            $table->string('fuente')->nullable();
            $table->string('portada_ruta')->nullable();

            $table->string('situacion')->default(Situacion::Acervo->value)->index();

            // La Lectura son tres estados: sin marca, con marca y sin fecha, y
            // con marca y fecha.
            $table->boolean('leido')->default(false);
            $table->date('fecha_lectura')->nullable();

            $table->text('apunte')->nullable();

            $table->string('prestado_a')->nullable();
            $table->date('prestado_el')->nullable();

            // Un Libro nace visible; Oculto es la excepción (ADR-003).
            $table->boolean('oculto')->default(false)->index();

            // Con qué Modelo se generó lo que generó la IA, para saber después
            // qué quedó con el Modelo viejo (ADR-006).
            $table->string('categorias_proveedor')->nullable();
            $table->string('categorias_modelo')->nullable();
            $table->string('descripcion_proveedor')->nullable();
            $table->string('descripcion_modelo')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('libros');
    }
};
