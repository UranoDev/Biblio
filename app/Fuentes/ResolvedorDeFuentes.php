<?php

namespace App\Fuentes;

use App\Dominio\Isbn;

/**
 * Consulta las Fuentes en orden y devuelve lo primero que aparezca.
 *
 * El orden vive en la configuración, no aquí ni en las pantallas: es lo que
 * permite agregar, quitar o reordenar catálogos sin tocar nada más (ADR-002).
 * Hay un orden por operación, porque no responden igual de bien a las dos.
 */
final class ResolvedorDeFuentes
{
    /** @var list<Fuente> */
    private readonly array $porTitulo;

    /**
     * @param  list<Fuente>  $fuentes  en el orden en que se les pregunta un ISBN
     * @param  list<Fuente>|null  $porTitulo  el orden para el Alta por título; el mismo
     *                                        de arriba cuando no se da otro
     */
    public function __construct(
        private readonly array $fuentes,
        ?array $porTitulo = null,
    ) {
        $this->porTitulo = $porTitulo ?? $fuentes;
    }

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

    /**
     * Los Candidatos de la primera Fuente que tenga alguno.
     *
     * No se juntan los de todas: dos catálogos devuelven las mismas ediciones
     * con datos distintos, y una lista con la misma edición repetida es
     * exactamente lo que el Alta por título no puede tener — la pantalla
     * existe para distinguir ediciones entre sí.
     */
    public function porTitulo(string $titulo, ?string $autor = null): Candidatos
    {
        $sinRespuesta = [];

        foreach ($this->porTitulo as $fuente) {
            $candidatos = $fuente->porTitulo($titulo, $autor);

            if ($candidatos->hayCandidatos()) {
                return $candidatos;
            }

            if (! $candidatos->respondio()) {
                $sinRespuesta[] = $fuente->identificador()->etiqueta().': '.$candidatos->motivo;
            }
        }

        // Mismo criterio que arriba, y aquí importa más: la búsqueda por
        // título es la que primero agota una cuota, así que el 429 es el caso
        // normal y no el raro.
        return $sinRespuesta === []
            ? Candidatos::ninguno()
            : Candidatos::noDisponible(implode(' · ', $sinRespuesta));
    }
}
