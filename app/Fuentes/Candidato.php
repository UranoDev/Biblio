<?php

namespace App\Fuentes;

use App\Dominio\Isbn;
use App\Enums;

/**
 * Cada edición que la Fuente devuelve en un Alta por título.
 *
 * Trae Portada, autores, editorial y año porque sin eso no hay forma de
 * distinguir una edición de otra: un Libro es una edición (ADR-005), y un
 * título cualquiera tiene la de bolsillo, la de tapa dura, la del 98 y la del
 * 2015, todas con el mismo nombre en la lista.
 *
 * Un Candidato **sin ISBN es válido**: se puede dar de alta igual y el Libro
 * queda sin ISBN. La unicidad del acervo aplica a los que sí lo tienen.
 */
final readonly class Candidato
{
    /**
     * @param  list<string>  $autores  en el orden en que los acredita la Fuente
     * @param  string|null  $referencia  el identificador que el catálogo le da a esta
     *                                   edición. Es lo único que distingue dos Candidatos
     *                                   cuando ni el ISBN ni la editorial ni el año lo hacen.
     */
    public function __construct(
        public ?Isbn $isbn,
        public string $titulo,
        public array $autores,
        public ?string $editorial,
        public ?int $anio,
        public ?string $edicion,
        public ?string $portadaUrl,
        public ?string $descripcion,
        public Enums\Fuente $fuente,
        public ?string $referencia = null,
    ) {}

    public function tieneIsbn(): bool
    {
        return $this->isbn instanceof Isbn;
    }

    /**
     * Con qué procedencia se guarda la Descripción de este Candidato, o null
     * cuando no trajo ninguna (ADR-007). Es la misma regla que en la Ficha: lo
     * que llegó del catálogo se guarda como de la Fuente.
     */
    public function procedenciaDeLaDescripcion(): ?Enums\Procedencia
    {
        return $this->descripcion !== null && trim($this->descripcion) !== ''
            ? Enums\Procedencia::Fuente
            : null;
    }

    /**
     * Con qué se le apunta a este Candidato desde la pantalla que lo eligió.
     * Es estable entre una consulta y la siguiente mientras el catálogo no
     * cambie de identificadores.
     */
    public function clave(): string
    {
        return $this->fuente->value.':'.($this->referencia
            ?? $this->isbn?->valor()
            ?? md5($this->titulo.'|'.implode(',', $this->autores).'|'.$this->editorial.'|'.$this->anio));
    }

    /**
     * Los datos planos del Candidato, para que la pantalla que lo mostró pueda
     * recordarlo entre una consulta y la siguiente.
     *
     * Volver a preguntarle a la Fuente al momento de elegir no sirve: contesta
     * otra cosa, o no contesta, y lo que se registraría no sería la edición
     * que se vio en la lista.
     *
     * @return array<string, mixed>
     */
    public function comoArreglo(): array
    {
        return [
            'isbn' => $this->isbn?->valor(),
            'titulo' => $this->titulo,
            'autores' => $this->autores,
            'editorial' => $this->editorial,
            'anio' => $this->anio,
            'edicion' => $this->edicion,
            'portadaUrl' => $this->portadaUrl,
            'descripcion' => $this->descripcion,
            'fuente' => $this->fuente->value,
            'referencia' => $this->referencia,
        ];
    }

    /**
     * El Candidato que se guardó con `comoArreglo`.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function desdeArreglo(array $datos): self
    {
        $isbn = $datos['isbn'] ?? null;
        $autores = $datos['autores'] ?? [];

        return new self(
            isbn: is_string($isbn) && $isbn !== '' ? Isbn::desde($isbn) : null,
            titulo: (string) ($datos['titulo'] ?? ''),
            autores: is_array($autores) ? array_values(array_map(strval(...), $autores)) : [],
            editorial: self::textoONulo($datos['editorial'] ?? null),
            anio: isset($datos['anio']) ? (int) $datos['anio'] : null,
            edicion: self::textoONulo($datos['edicion'] ?? null),
            portadaUrl: self::textoONulo($datos['portadaUrl'] ?? null),
            descripcion: self::textoONulo($datos['descripcion'] ?? null),
            fuente: Enums\Fuente::from((string) ($datos['fuente'] ?? '')),
            referencia: self::textoONulo($datos['referencia'] ?? null),
        );
    }

    private static function textoONulo(mixed $valor): ?string
    {
        return $valor === null ? null : (string) $valor;
    }

    /**
     * La Ficha de este Candidato, o null cuando no trae ISBN: la Ficha se arma
     * a partir del ISBN y sin él no hay ninguna que armar.
     */
    public function aFicha(): ?Ficha
    {
        if (! $this->isbn instanceof Isbn) {
            return null;
        }

        return new Ficha(
            isbn: $this->isbn,
            titulo: $this->titulo,
            autores: $this->autores,
            editorial: $this->editorial,
            anio: $this->anio,
            edicion: $this->edicion,
            portadaUrl: $this->portadaUrl,
            descripcion: $this->descripcion,
            fuente: $this->fuente,
        );
    }
}
