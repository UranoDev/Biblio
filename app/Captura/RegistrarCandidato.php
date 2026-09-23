<?php

namespace App\Captura;

use App\Enums\Situacion;
use App\Fuentes\Candidato;
use App\Models\Libro;

/**
 * Lo que pasa cuando se elige un Candidato en el Alta por título.
 *
 * Vive fuera de la pantalla por lo mismo que RegistrarEscaneo: un ISBN es
 * único en el acervo (ADR-005), y esa regla no cambia según por dónde entre el
 * Libro.
 *
 * Lo que sí cambia es qué hacer con el que ya estaba. El reescaneo lo pasa de
 * Por comprar al Acervo, porque tener el Libro en la mano es justamente la
 * señal de que ya se compró; aquí no hay Libro en la mano —se está eligiendo
 * una edición de una lista—, así que sólo se avisa cuál es el que ya está.
 */
final class RegistrarCandidato
{
    public function __construct(private readonly AltaDeLibro $alta) {}

    public function registrar(Candidato $candidato, Situacion $situacion = Situacion::PorComprar): ResultadoDelAlta
    {
        // Un Candidato sin ISBN no puede chocar con nada: la unicidad alcanza
        // a los que sí lo tienen.
        $registrado = $candidato->tieneIsbn()
            ? Libro::query()->where('isbn', $candidato->isbn?->valor())->first()
            : null;

        if ($registrado instanceof Libro) {
            return ResultadoDelAlta::yaEstaba($registrado);
        }

        return ResultadoDelAlta::registrado($this->alta->desdeElCandidato($candidato, $situacion));
    }
}
