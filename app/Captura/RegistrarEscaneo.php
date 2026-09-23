<?php

namespace App\Captura;

use App\Dominio\Isbn;
use App\Dominio\IsbnInvalido;
use App\Enums\Situacion;
use App\Fuentes\ResolvedorDeFuentes;
use App\Models\Libro;

/**
 * Lo que pasa cuando un ISBN entra por el Escáner.
 *
 * Vive fuera de la pantalla porque son las reglas del ADR-005 —un ISBN es
 * único en el acervo y el reescaneo tiene tres desenlaces—, no decisiones de
 * la captura: el Alta por título y la Captura manual comparten la de no
 * duplicar.
 */
final class RegistrarEscaneo
{
    public function __construct(
        private readonly ResolvedorDeFuentes $fuentes,
        private readonly AltaDeLibro $alta,
    ) {}

    public function registrar(string $tecleado): ResultadoDelEscaneo
    {
        $tecleado = trim($tecleado);

        try {
            $isbn = Isbn::desde($tecleado);
        } catch (IsbnInvalido) {
            return ResultadoDelEscaneo::isbnInvalido($tecleado);
        }

        $registrado = Libro::query()->where('isbn', $isbn->valor())->first();

        if ($registrado instanceof Libro) {
            return $this->reescaneo($registrado, $isbn, $tecleado);
        }

        $respuesta = $this->fuentes->porIsbn($isbn);

        if (! $respuesta->hayFicha()) {
            // Que ninguna Fuente lo conozca manda a Captura manual; que alguna
            // no haya podido contestar, no: registraría a mano un Libro que la
            // Fuente sí tenía (ADR-002).
            return $respuesta->noConoceElIsbn()
                ? ResultadoDelEscaneo::isbnDesconocido($isbn, $tecleado)
                : ResultadoDelEscaneo::fuenteNoDisponible($isbn, $tecleado, $respuesta->motivo);
        }

        return ResultadoDelEscaneo::registrado(
            $this->alta->desdeLaFicha($respuesta->ficha),
            $isbn,
            $tecleado,
        );
    }

    /**
     * Los dos desenlaces del ISBN que ya estaba: si está Por comprar, pasa al
     * Acervo solo —ésa es la vía normal de «ya lo compré»— y si ya estaba en
     * el Acervo, nada más se avisa (ADR-005).
     */
    private function reescaneo(Libro $libro, Isbn $isbn, string $tecleado): ResultadoDelEscaneo
    {
        if ($libro->situacion === Situacion::PorComprar) {
            $libro->pasaAlAcervo();

            return ResultadoDelEscaneo::pasoAlAcervo($libro, $isbn, $tecleado);
        }

        return ResultadoDelEscaneo::yaEstaEnElAcervo($libro, $isbn, $tecleado);
    }
}
