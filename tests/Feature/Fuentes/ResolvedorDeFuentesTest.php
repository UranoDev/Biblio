<?php

use App\Dominio\Isbn;
use App\Dominio\IsbnInvalido;
use App\Enums;
use App\Fuentes\Ficha;
use App\Fuentes\Fuente;
use App\Fuentes\ResolvedorDeFuentes;
use App\Fuentes\Respuesta;
use Illuminate\Support\Facades\Http;

/** Una Fuente de mentiras que contesta siempre lo mismo, para probar el orden. */
function fuenteQue(Respuesta $respuesta, Enums\Fuente $identificador): Fuente
{
    return new class($respuesta, $identificador) implements Fuente
    {
        public int $consultas = 0;

        public function __construct(
            private readonly Respuesta $respuesta,
            private readonly Enums\Fuente $identificador,
        ) {}

        public function identificador(): Enums\Fuente
        {
            return $this->identificador;
        }

        public function porIsbn(Isbn $isbn): Respuesta
        {
            $this->consultas++;

            return $this->respuesta;
        }
    };
}

function fichaDeMentiras(Enums\Fuente $fuente): Ficha
{
    return new Ficha(
        isbn: Isbn::desde('9788437604176'),
        titulo: 'Pedro Páramo',
        autores: ['Juan Rulfo'],
        editorial: 'Editorial RM',
        anio: 2005,
        edicion: null,
        portadaUrl: null,
        descripcion: null,
        fuente: $fuente,
    );
}

it('se queda con la primera Fuente que conoce el ISBN y no consulta las demás', function () {
    $primera = fuenteQue(Respuesta::conFicha(fichaDeMentiras(Enums\Fuente::OpenLibrary)), Enums\Fuente::OpenLibrary);
    $segunda = fuenteQue(Respuesta::sinFicha(), Enums\Fuente::GoogleBooks);

    $respuesta = (new ResolvedorDeFuentes([$primera, $segunda]))->porIsbn(Isbn::desde('9788437604176'));

    expect($respuesta->ficha->fuente)->toBe(Enums\Fuente::OpenLibrary)
        ->and($segunda->consultas)->toBe(0);
});

it('cae a la siguiente Fuente cuando la primera no conoce el ISBN', function () {
    $primera = fuenteQue(Respuesta::sinFicha(), Enums\Fuente::OpenLibrary);
    $segunda = fuenteQue(Respuesta::conFicha(fichaDeMentiras(Enums\Fuente::GoogleBooks)), Enums\Fuente::GoogleBooks);

    $respuesta = (new ResolvedorDeFuentes([$primera, $segunda]))->porIsbn(Isbn::desde('9788437604176'));

    expect($respuesta->ficha->fuente)->toBe(Enums\Fuente::GoogleBooks);
});

it('devuelve sin Ficha cuando ninguna lo conoce, sin lanzar excepción', function () {
    $resolvedor = new ResolvedorDeFuentes([
        fuenteQue(Respuesta::sinFicha(), Enums\Fuente::OpenLibrary),
        fuenteQue(Respuesta::sinFicha(), Enums\Fuente::GoogleBooks),
    ]);

    $respuesta = $resolvedor->porIsbn(Isbn::desde('9788437604176'));

    expect($respuesta->noConoceElIsbn())->toBeTrue()
        ->and($respuesta->respondio())->toBeTrue();
});

it('no manda a Captura manual un ISBN que una Fuente no pudo contestar', function () {
    $resolvedor = new ResolvedorDeFuentes([
        fuenteQue(Respuesta::sinFicha(), Enums\Fuente::OpenLibrary),
        fuenteQue(Respuesta::noDisponible('HTTP 429'), Enums\Fuente::GoogleBooks),
    ]);

    $respuesta = $resolvedor->porIsbn(Isbn::desde('9788437604176'));

    expect($respuesta->respondio())->toBeFalse()
        ->and($respuesta->noConoceElIsbn())->toBeFalse()
        ->and($respuesta->motivo)->toContain('Google Books');
});

it('sigue consultando cuando la primera Fuente no pudo contestar', function () {
    $resolvedor = new ResolvedorDeFuentes([
        fuenteQue(Respuesta::noDisponible('se cayó la red'), Enums\Fuente::OpenLibrary),
        fuenteQue(Respuesta::conFicha(fichaDeMentiras(Enums\Fuente::GoogleBooks)), Enums\Fuente::GoogleBooks),
    ]);

    expect($resolvedor->porIsbn(Isbn::desde('9788437604176'))->hayFicha())->toBeTrue();
});

it('rechaza un ISBN con el dígito de control inválido antes de salir a la red', function () {
    Http::fake();

    expect(fn () => Isbn::desde('9788437604177'))->toThrow(IsbnInvalido::class);

    Http::assertNothingSent();
});

it('queda armado desde la configuración, con Open Library primero', function () {
    Http::fake([
        'openlibrary.org/isbn/*' => Http::response(grabada('open-library-edicion-sin-obra')),
        'openlibrary.org/authors/*' => Http::response(grabada('open-library-autor-garcia-marquez')),
    ]);

    $ficha = app(ResolvedorDeFuentes::class)->porIsbn(Isbn::desde('9788420471839'))->ficha;

    expect($ficha->fuente)->toBe(Enums\Fuente::OpenLibrary);

    Http::assertNotSent(fn ($peticion) => str_contains($peticion->url(), 'googleapis.com'));
});

it('usa Google Books de respaldo cuando Open Library no conoce el ISBN', function () {
    Http::fake([
        'openlibrary.org/*' => Http::response(status: 404),
        'www.googleapis.com/*' => Http::response(grabada('google-books-volumen')),
    ]);

    $ficha = app(ResolvedorDeFuentes::class)->porIsbn(Isbn::desde('9788437604176'))->ficha;

    expect($ficha->fuente)->toBe(Enums\Fuente::GoogleBooks)
        ->and($ficha->titulo)->toBe('Pedro Páramo');
});
