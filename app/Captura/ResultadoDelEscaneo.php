<?php

namespace App\Captura;

use App\Dominio\Isbn;
use App\Enums\DesenlaceDelEscaneo;
use App\Models\Libro;

/**
 * En qué terminó un Escaneo, con lo que la pantalla necesita para avisarlo sin
 * volver a consultar nada.
 *
 * Guarda tanto el ISBN normalizado como lo que llegó tecleado: cuando ninguna
 * Fuente conoce el ISBN, la Captura manual arranca con ese número ya puesto, y
 * cuando lo tecleado no es un ISBN, el aviso tiene que poder decir qué fue lo
 * que entró.
 */
final readonly class ResultadoDelEscaneo
{
    private function __construct(
        public DesenlaceDelEscaneo $desenlace,
        public string $tecleado,
        public ?Isbn $isbn = null,
        public ?Libro $libro = null,
        public ?string $motivo = null,
    ) {}

    public static function registrado(Libro $libro, Isbn $isbn, string $tecleado): self
    {
        return new self(DesenlaceDelEscaneo::Registrado, $tecleado, $isbn, $libro);
    }

    public static function yaEstaEnElAcervo(Libro $libro, Isbn $isbn, string $tecleado): self
    {
        return new self(DesenlaceDelEscaneo::YaEstaEnElAcervo, $tecleado, $isbn, $libro);
    }

    public static function pasoAlAcervo(Libro $libro, Isbn $isbn, string $tecleado): self
    {
        return new self(DesenlaceDelEscaneo::PasoAlAcervo, $tecleado, $isbn, $libro);
    }

    public static function isbnDesconocido(Isbn $isbn, string $tecleado): self
    {
        return new self(DesenlaceDelEscaneo::IsbnDesconocido, $tecleado, $isbn);
    }

    public static function fuenteNoDisponible(Isbn $isbn, string $tecleado, ?string $motivo): self
    {
        return new self(DesenlaceDelEscaneo::FuenteNoDisponible, $tecleado, $isbn, null, $motivo);
    }

    public static function isbnInvalido(string $tecleado): self
    {
        return new self(DesenlaceDelEscaneo::IsbnInvalido, $tecleado);
    }

    /** El paso de Por comprar al Acervo es el único que se deshace. */
    public function sePuedeDeshacer(): bool
    {
        return $this->desenlace === DesenlaceDelEscaneo::PasoAlAcervo;
    }

    /** Ninguna Fuente lo conoce: la vía que queda es escribirlo a mano. */
    public function ofreceCapturaManual(): bool
    {
        return $this->desenlace === DesenlaceDelEscaneo::IsbnDesconocido;
    }
}
