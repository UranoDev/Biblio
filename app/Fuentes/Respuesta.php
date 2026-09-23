<?php

namespace App\Fuentes;

/**
 * Lo que contesta una Fuente —o el resolvedor— cuando se le pregunta un ISBN.
 *
 * Son tres desenlaces, y la diferencia entre los dos últimos es la razón de
 * ser de esta clase: «no lo conozco» manda a Captura manual, «no pude
 * contestar» pide reintentar. Confundirlos registra a mano un Libro que la
 * Fuente sí tenía, y quien captura no tiene forma de notarlo (ADR-002).
 */
final readonly class Respuesta
{
    private function __construct(
        public ?Ficha $ficha,
        private bool $respondio,
        public ?string $motivo,
    ) {}

    public static function conFicha(Ficha $ficha): self
    {
        return new self($ficha, true, null);
    }

    /** La Fuente contestó y no conoce ese ISBN. */
    public static function sinFicha(): self
    {
        return new self(null, true, null);
    }

    /** La Fuente no pudo contestar: cuota agotada, error de red o HTTP feo. */
    public static function noDisponible(string $motivo): self
    {
        return new self(null, false, $motivo);
    }

    public function hayFicha(): bool
    {
        return $this->ficha instanceof Ficha;
    }

    /** Si la Fuente llegó a contestar, sea que conozca el ISBN o no. */
    public function respondio(): bool
    {
        return $this->respondio;
    }

    /** Contestó que no lo conoce: es el caso que ofrece Captura manual. */
    public function noConoceElIsbn(): bool
    {
        return $this->respondio && ! $this->hayFicha();
    }
}
