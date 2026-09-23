<?php

namespace App\Captura;

use App\Enums\Fuente;
use App\Enums\Situacion;
use App\Fuentes\Candidato;
use App\Fuentes\Ficha;
use App\Jobs\DescargarPortada;
use App\Models\Libro;

/**
 * El único lugar donde nace un Libro, venga de donde venga.
 *
 * Las tres vías —el Escaneo, el Alta por título y la Captura manual— difieren
 * en de dónde salen los datos, no en lo que pasa después de registrarlos: la
 * Portada se baja en cola (ADR-004) y las Categorías se piden en cola, nunca
 * durante la captura (ADR-006). Teniendo un solo lugar, agregar una vía no
 * puede olvidarse de ninguna de las dos.
 */
final class AltaDeLibro
{
    /** Con la Ficha que trajo una Fuente a partir del ISBN: el Escaneo. */
    public function desdeLaFicha(Ficha $ficha, Situacion $situacion = Situacion::Acervo): Libro
    {
        return $this->nace([
            'isbn' => $ficha->isbn->valor(),
            'titulo' => $ficha->titulo,
            'autores' => $ficha->autores,
            'editorial' => $ficha->editorial,
            'anio' => $ficha->anio,
            'edicion' => $ficha->edicion,
            'descripcion' => $ficha->descripcion,
            'descripcion_procedencia' => $ficha->procedenciaDeLaDescripcion(),
            'fuente' => $ficha->fuente,
            'situacion' => $situacion,
        ], $ficha->portadaUrl);
    }

    /**
     * Con la edición que se eligió en un Alta por título.
     *
     * Un Candidato con ISBN trae exactamente la Ficha que habría traído el
     * Escaneo, así que entra por ahí. Uno **sin** ISBN se registra igual: el
     * Libro queda sin ISBN, y la unicidad del acervo sólo alcanza a los que sí
     * lo tienen (ADR-005). Lo que no cambia es que la Portada la dio el
     * catálogo, así que se baja y se guarda como cualquier otra (ADR-004).
     */
    public function desdeElCandidato(Candidato $candidato, Situacion $situacion = Situacion::Acervo): Libro
    {
        $ficha = $candidato->aFicha();

        if ($ficha instanceof Ficha) {
            return $this->desdeLaFicha($ficha, $situacion);
        }

        return $this->nace([
            'isbn' => null,
            'titulo' => $candidato->titulo,
            'autores' => $candidato->autores,
            'editorial' => $candidato->editorial,
            'anio' => $candidato->anio,
            'edicion' => $candidato->edicion,
            'descripcion' => $candidato->descripcion,
            'descripcion_procedencia' => $candidato->procedenciaDeLaDescripcion(),
            'fuente' => $candidato->fuente,
            'situacion' => $situacion,
        ], $candidato->portadaUrl);
    }

    /**
     * Con los datos escritos a mano. El Libro queda marcado con la Fuente
     * «Captura manual», que es lo que permite saber después que su Ficha no
     * vino de ningún catálogo y que nadie la va a corregir por su cuenta.
     *
     * No se le encola Portada: no hay URL de dónde bajarla. La imagen se sube
     * a mano, con las piezas que ya existen (ADR-004).
     */
    public function aMano(LibroCapturado $datos): Libro
    {
        return $this->nace([
            'isbn' => $datos->isbn?->valor(),
            'titulo' => $datos->titulo,
            'autores' => $datos->autores,
            'editorial' => $datos->editorial,
            'anio' => $datos->anio,
            'edicion' => $datos->edicion,
            'fuente' => Fuente::Manual,
            'situacion' => $datos->situacion,
        ], null);
    }

    /**
     * Donde nace el Libro, sea cual sea la vía: se registra, se le encola la
     * Portada cuando hay de dónde bajarla y se le encolan las Categorías.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function nace(array $atributos, ?string $portadaUrl): Libro
    {
        $libro = Libro::create($atributos);

        DescargarPortada::paraElLibro($libro, $portadaUrl);

        $this->encolarLasCategorias($libro);

        return $libro;
    }

    /**
     * Las Categorías las escribe una IA a partir de la Ficha, en cola y nunca
     * durante la captura (ADR-006). El agente que las escribe todavía no
     * existe —es BIB-8—, y su despacho va aquí: en este punto el Libro ya
     * quedó registrado, que es lo único que la cola necesita.
     */
    private function encolarLasCategorias(Libro $libro): void {}
}
