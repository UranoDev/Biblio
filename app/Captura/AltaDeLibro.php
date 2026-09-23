<?php

namespace App\Captura;

use App\Enums\Fuente;
use App\Enums\Situacion;
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
    /** Con la Ficha que trajo una Fuente: el Escaneo y el Alta por título. */
    public function desdeLaFicha(Ficha $ficha, Situacion $situacion = Situacion::Acervo): Libro
    {
        $libro = Libro::create([
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
        ]);

        DescargarPortada::paraElLibro($libro, $ficha->portadaUrl);

        $this->encolarLasCategorias($libro);

        return $libro;
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
        $libro = Libro::create([
            'isbn' => $datos->isbn?->valor(),
            'titulo' => $datos->titulo,
            'autores' => $datos->autores,
            'editorial' => $datos->editorial,
            'anio' => $datos->anio,
            'edicion' => $datos->edicion,
            'fuente' => Fuente::Manual,
            'situacion' => $datos->situacion,
        ]);

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
