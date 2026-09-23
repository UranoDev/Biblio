<?php

namespace App\Fuentes;

use App\Dominio\Isbn;
use App\Enums;

/**
 * El catálogo público al que se le consulta un ISBN para armar la Ficha.
 *
 * Éste es el seam del ADR-002: hay una implementación por catálogo y un
 * resolvedor que las consulta en orden. Cambiar el orden, agregar un catálogo
 * o contratar uno de pago no toca ninguna pantalla.
 */
interface Fuente
{
    /** Cuál Fuente es, para dejar registrado quién respondió por cada Libro. */
    public function identificador(): Enums\Fuente;

    public function porIsbn(Isbn $isbn): Respuesta;
}
