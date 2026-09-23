<?php

namespace App\Captura;

use App\Models\Libro;

/**
 * En qué terminó elegir un Candidato en el Alta por título.
 *
 * Son dos desenlaces y en los dos hay un Libro: el que acaba de nacer, o el
 * que ya tenía ese ISBN. Por eso el Libro no es opcional — la pantalla siempre
 * tiene a cuál apuntar.
 */
final readonly class ResultadoDelAlta
{
    private function __construct(
        public Libro $libro,
        public bool $yaEstaba,
    ) {}

    public static function registrado(Libro $libro): self
    {
        return new self($libro, false);
    }

    /** Ese ISBN ya estaba en el acervo: se avisa cuál es el Libro y no se duplica. */
    public static function yaEstaba(Libro $libro): self
    {
        return new self($libro, true);
    }
}
