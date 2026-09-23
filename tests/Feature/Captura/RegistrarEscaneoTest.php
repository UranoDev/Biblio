<?php

use App\Captura\RegistrarEscaneo;
use App\Captura\ResultadoDelEscaneo;
use App\Enums\DesenlaceDelEscaneo;
use App\Enums\Procedencia;
use App\Enums\Situacion;
use App\Fuentes\Respuesta;
use App\Jobs\DescargarPortada;
use App\Models\Libro;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function escaneoDe(string $tecleado): ResultadoDelEscaneo
{
    return app(RegistrarEscaneo::class)->registrar($tecleado);
}

it('registra el Libro con la Ficha que trajo la Fuente', function () {
    lasFuentesContestan(Respuesta::conFicha(fichaDe(
        isbn: '9788437604176',
        descripcion: 'La historia de Juan Preciado en Comala.',
    )));

    $resultado = escaneoDe('9788437604176');

    expect($resultado->desenlace)->toBe(DesenlaceDelEscaneo::Registrado)
        ->and($resultado->libro->titulo)->toBe('Pedro Páramo')
        ->and($resultado->libro->autores)->toBe(['Juan Rulfo'])
        ->and($resultado->libro->isbn)->toBe('9788437604176')
        ->and($resultado->libro->situacion)->toBe(Situacion::Acervo)
        ->and($resultado->libro->descripcion_procedencia)->toBe(Procedencia::Fuente)
        ->and(Libro::count())->toBe(1);
});

it('encola la Portada del Libro que acaba de registrar', function () {
    lasFuentesContestan(Respuesta::conFicha(fichaDe(
        isbn: '9788437604176',
        portadaUrl: 'https://covers.openlibrary.org/b/id/12345-L.jpg',
    )));

    $resultado = escaneoDe('9788437604176');

    Queue::assertPushed(DescargarPortada::class);

    expect($resultado->libro->fresh()->portada_origen_url)
        ->toBe('https://covers.openlibrary.org/b/id/12345-L.jpg');
});

it('no encola nada cuando la Ficha no trae Portada', function () {
    lasFuentesContestan(Respuesta::conFicha(fichaDe('9788437604176')));

    escaneoDe('9788437604176');

    Queue::assertNothingPushed();
});

it('avisa que el Libro ya está en el Acervo, sin duplicarlo ni volver a preguntarle a la Fuente', function () {
    $libro = Libro::factory()->enAcervo()->create(['isbn' => '9788437604176']);

    lasFuentesContestan(Respuesta::noDisponible('no tendría que haberse consultado'));

    $resultado = escaneoDe('9788437604176');

    expect($resultado->desenlace)->toBe(DesenlaceDelEscaneo::YaEstaEnElAcervo)
        ->and($resultado->libro->is($libro))->toBeTrue()
        ->and($resultado->sePuedeDeshacer())->toBeFalse()
        ->and(Libro::count())->toBe(1);
});

it('pasa al Acervo el Libro que estaba Por comprar', function () {
    $libro = Libro::factory()->porComprar()->create(['isbn' => '9788437604176']);

    $resultado = escaneoDe('9788437604176');

    expect($resultado->desenlace)->toBe(DesenlaceDelEscaneo::PasoAlAcervo)
        ->and($resultado->sePuedeDeshacer())->toBeTrue()
        ->and($libro->fresh()->situacion)->toBe(Situacion::Acervo)
        ->and(Libro::count())->toBe(1);
});

it('reconoce el ISBN-10 del Libro guardado con su ISBN-13', function () {
    Libro::factory()->enAcervo()->create(['isbn' => '9788437604176']);

    expect(escaneoDe('8437604176')->desenlace)->toBe(DesenlaceDelEscaneo::YaEstaEnElAcervo)
        ->and(Libro::count())->toBe(1);
});

it('ofrece Captura manual cuando ninguna Fuente conoce el ISBN', function () {
    lasFuentesContestan(Respuesta::sinFicha());

    $resultado = escaneoDe('9788437604176');

    expect($resultado->desenlace)->toBe(DesenlaceDelEscaneo::IsbnDesconocido)
        ->and($resultado->ofreceCapturaManual())->toBeTrue()
        ->and($resultado->isbn->valor())->toBe('9788437604176')
        ->and(Libro::count())->toBe(0);
});

it('no ofrece Captura manual cuando la Fuente no pudo contestar', function () {
    lasFuentesContestan(Respuesta::noDisponible('Google Books: cuota agotada'));

    $resultado = escaneoDe('9788437604176');

    expect($resultado->desenlace)->toBe(DesenlaceDelEscaneo::FuenteNoDisponible)
        ->and($resultado->ofreceCapturaManual())->toBeFalse()
        ->and($resultado->motivo)->toContain('cuota agotada')
        ->and(Libro::count())->toBe(0);
});

it('rechaza lo que no es un ISBN sin consultar a ninguna Fuente', function () {
    lasFuentesContestan(Respuesta::noDisponible('no tendría que haberse consultado'));

    $resultado = escaneoDe('1234567890');

    expect($resultado->desenlace)->toBe(DesenlaceDelEscaneo::IsbnInvalido)
        ->and($resultado->tecleado)->toBe('1234567890')
        ->and($resultado->isbn)->toBeNull()
        ->and(Libro::count())->toBe(0);
});

it('acepta el ISBN tal como lo imprime el código de barras, con guiones', function () {
    lasFuentesContestan(Respuesta::conFicha(fichaDe('9788437604176')));

    expect(escaneoDe('978-84-376-0417-6')->libro->isbn)->toBe('9788437604176');
});

it('deja el Libro sin Descripción cuando la Fuente no la trajo', function () {
    lasFuentesContestan(Respuesta::conFicha(fichaDe('9788437604176')));

    $libro = escaneoDe('9788437604176')->libro;

    expect($libro->descripcion)->toBeNull()
        ->and($libro->descripcion_procedencia)->toBeNull();
});
