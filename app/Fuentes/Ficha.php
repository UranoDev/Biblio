<?php

namespace App\Fuentes;

use App\Dominio\Isbn;
use App\Enums;

/**
 * Los datos bibliográficos que llegan de una Fuente a partir del ISBN.
 *
 * Su forma no depende de cuál catálogo respondió (ADR-002): agregar, quitar o
 * reordenar Fuentes no cambia nada de aquí para arriba. Queda registrado en
 * `fuente` quién contestó, que es lo que hace falta el día que un dato salga
 * flojo.
 */
final readonly class Ficha
{
    /**
     * @param  list<string>  $autores  en el orden en que los acredita la Fuente
     */
    public function __construct(
        public Isbn $isbn,
        public string $titulo,
        public array $autores,
        public ?string $editorial,
        public ?int $anio,
        public ?string $edicion,
        public ?string $portadaUrl,
        public ?string $descripcion,
        public Enums\Fuente $fuente,
    ) {}

    /**
     * Una Ficha sin Descripción es válida: el Libro se registra igual y queda
     * para que la IA la escriba (ADR-007).
     */
    public function tieneDescripcion(): bool
    {
        return $this->descripcion !== null && trim($this->descripcion) !== '';
    }

    /**
     * Con qué procedencia se guarda la Descripción de esta Ficha, o null
     * cuando no trajo ninguna (ADR-007).
     */
    public function procedenciaDeLaDescripcion(): ?Enums\Procedencia
    {
        return $this->tieneDescripcion() ? Enums\Procedencia::Fuente : null;
    }
}
