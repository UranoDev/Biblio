<?php

namespace App\Captura;

use App\Dominio\Isbn;
use App\Enums\Situacion;

/**
 * Los datos de un Libro escritos a mano, porque ninguna Fuente lo conoce.
 *
 * Sólo el título es obligatorio: de un libro viejo sin código de barras puede
 * no saberse el año ni la edición, y un dato que no se sabe es preferible en
 * blanco a inventado.
 */
final readonly class LibroCapturado
{
    /**
     * @param  list<string>  $autores  en el orden en que se acreditan en la portada
     */
    public function __construct(
        public string $titulo,
        public array $autores = [],
        public ?string $editorial = null,
        public ?int $anio = null,
        public ?string $edicion = null,
        public ?Isbn $isbn = null,
        public Situacion $situacion = Situacion::Acervo,
    ) {}
}
