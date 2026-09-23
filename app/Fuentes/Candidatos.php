<?php

namespace App\Fuentes;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Lo que contesta una Fuente —o el resolvedor— en un Alta por título.
 *
 * Son los mismos tres desenlaces que en la consulta por ISBN, y por la misma
 * razón: **una lista vacía y una Fuente que no pudo contestar no son lo
 * mismo**. La primera dice que no hay esa edición y ofrece Captura manual; la
 * segunda pide reintentar. Confundirlas manda a escribir a mano un Libro que
 * el catálogo sí tenía (ADR-002).
 *
 * @implements IteratorAggregate<int, Candidato>
 */
final readonly class Candidatos implements Countable, IteratorAggregate
{
    /** @param  list<Candidato>  $candidatos */
    private function __construct(
        public array $candidatos,
        private bool $respondio,
        public ?string $motivo,
    ) {}

    /**
     * `array_values` no sobra: quien filtra una lista de Candidatos deja
     * huecos en las claves, y de aquí para arriba se recorre por posición.
     *
     * @param  array<array-key, Candidato>  $candidatos
     */
    public static function con(array $candidatos): self
    {
        return new self(array_values($candidatos), true, null);
    }

    /** La Fuente contestó y no tiene ninguna edición de ese título. */
    public static function ninguno(): self
    {
        return new self([], true, null);
    }

    /** La Fuente no pudo contestar: cuota agotada, error de red o HTTP feo. */
    public static function noDisponible(string $motivo): self
    {
        return new self([], false, $motivo);
    }

    public function hayCandidatos(): bool
    {
        return $this->candidatos !== [];
    }

    /** Si la Fuente llegó a contestar, sea que tenga el título o no. */
    public function respondio(): bool
    {
        return $this->respondio;
    }

    /** Contestó que no tiene ese título: es el caso que ofrece Captura manual. */
    public function noConoceElTitulo(): bool
    {
        return $this->respondio && ! $this->hayCandidatos();
    }

    public function count(): int
    {
        return count($this->candidatos);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->candidatos);
    }
}
