<?php

namespace App\Fuentes;

use App\Dominio\Isbn;

/**
 * Consulta las Fuentes en orden y devuelve la primera Ficha que aparezca.
 *
 * El orden vive en la configuración, no aquí ni en las pantallas: es lo que
 * permite agregar, quitar o reordenar catálogos sin tocar nada más (ADR-002).
 */
final class ResolvedorDeFuentes
{
    /** @param  list<Fuente>  $fuentes  en el orden en que se les pregunta */
    public function __construct(private readonly array $fuentes) {}

    public function porIsbn(Isbn $isbn): Respuesta
    {
        $sinRespuesta = [];

        foreach ($this->fuentes as $fuente) {
            $respuesta = $fuente->porIsbn($isbn);

            if ($respuesta->hayFicha()) {
                return $respuesta;
            }

            if (! $respuesta->respondio()) {
                $sinRespuesta[] = $fuente->identificador()->etiqueta().': '.$respuesta->motivo;
            }
        }

        // Basta con que una Fuente no haya podido contestar para que el
        // resultado no sea «sin Ficha»: mandar esto a Captura manual
        // registraría a mano un Libro que la Fuente sí tenía.
        return $sinRespuesta === []
            ? Respuesta::sinFicha()
            : Respuesta::noDisponible(implode(' · ', $sinRespuesta));
    }
}
