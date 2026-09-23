<?php

use App\Captura\RegistrarCandidato;
use App\Captura\ResultadoDelAlta;
use App\Enums\Fuente;
use App\Enums\Procedencia;
use App\Enums\Situacion;
use App\Fuentes\Candidato;
use App\Jobs\DescargarPortada;
use App\Models\Libro;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function registrarCandidato(...$argumentos): ResultadoDelAlta
{
    return app(RegistrarCandidato::class)->registrar(...$argumentos);
}

it('registra el Candidato con la Situación que se le indique', function () {
    $resultado = registrarCandidato(
        candidatoDe(isbn: '9788437604176', titulo: 'Pedro Páramo'),
        Situacion::Acervo,
    );

    expect($resultado->yaEstaba)->toBeFalse()
        ->and($resultado->libro->titulo)->toBe('Pedro Páramo')
        ->and($resultado->libro->isbn)->toBe('9788437604176')
        ->and($resultado->libro->situacion)->toBe(Situacion::Acervo);
});

it('nace Por comprar cuando no se indica Situación', function () {
    $resultado = registrarCandidato(candidatoDe(isbn: '9788437604176'));

    expect($resultado->libro->situacion)->toBe(Situacion::PorComprar);
});

it('deja registrado de qué Fuente salió la edición', function () {
    $resultado = registrarCandidato(candidatoDe(isbn: '9788437604176'));

    expect($resultado->libro->fuente)->toBe(Fuente::OpenLibrary);
});

it('encola la Portada que dio la Fuente', function () {
    $resultado = registrarCandidato(candidatoDe(
        isbn: '9788437604176',
        portadaUrl: 'https://covers.example/pedro-paramo.jpg',
    ));

    Queue::assertPushed(DescargarPortada::class, fn (DescargarPortada $job): bool => $job->libro->is($resultado->libro));

    expect($resultado->libro->refresh()->portada_origen_url)
        ->toBe('https://covers.example/pedro-paramo.jpg');
});

it('registra el Candidato que no trae ISBN, y el Libro queda sin ISBN', function () {
    $resultado = registrarCandidato(candidatoDe(isbn: null, titulo: 'El llano en llamas'));

    expect($resultado->yaEstaba)->toBeFalse()
        ->and($resultado->libro->isbn)->toBeNull()
        ->and($resultado->libro->titulo)->toBe('El llano en llamas')
        ->and($resultado->libro->fuente)->toBe(Fuente::OpenLibrary);
});

it('encola la Portada también cuando el Candidato no trae ISBN', function () {
    $resultado = registrarCandidato(candidatoDe(
        isbn: null,
        portadaUrl: 'https://covers.example/sin-isbn.jpg',
    ));

    Queue::assertPushed(DescargarPortada::class);

    expect($resultado->libro->refresh()->portada_origen_url)
        ->toBe('https://covers.example/sin-isbn.jpg');
});

it('avisa cuál es el Libro que ya tenía ese ISBN, en vez de duplicarlo', function () {
    $yaEstaba = Libro::factory()->create(['isbn' => '9788437604176', 'titulo' => 'Pedro Páramo']);

    $resultado = registrarCandidato(candidatoDe(isbn: '9788437604176'), Situacion::Acervo);

    expect($resultado->yaEstaba)->toBeTrue()
        ->and($resultado->libro->is($yaEstaba))->toBeTrue()
        ->and(Libro::count())->toBe(1);
});

it('no cambia la Situación del Libro que ya estaba', function () {
    Libro::factory()->create([
        'isbn' => '9788437604176',
        'situacion' => Situacion::PorComprar,
    ]);

    $resultado = registrarCandidato(candidatoDe(isbn: '9788437604176'), Situacion::Acervo);

    expect($resultado->libro->situacion)->toBe(Situacion::PorComprar);
});

it('dos Candidatos sin ISBN del mismo título se registran por separado', function () {
    registrarCandidato(candidatoDe(isbn: null, titulo: 'Cartucho'));
    registrarCandidato(candidatoDe(isbn: null, titulo: 'Cartucho'));

    expect(Libro::count())->toBe(2);
});

it('guarda la Descripción del Candidato con la procedencia de la Fuente', function () {
    $candidato = new Candidato(
        isbn: null,
        titulo: 'Cartucho',
        autores: ['Nellie Campobello'],
        editorial: 'Era',
        anio: 2000,
        edicion: null,
        portadaUrl: null,
        descripcion: 'Relatos de la lucha en el norte de México.',
        fuente: Fuente::GoogleBooks,
    );

    $resultado = registrarCandidato($candidato);

    expect($resultado->libro->descripcion)->toBe('Relatos de la lucha en el norte de México.')
        ->and($resultado->libro->descripcion_procedencia)->toBe(Procedencia::Fuente);
});

it('un Candidato sin Descripción no guarda procedencia', function () {
    $resultado = registrarCandidato(candidatoDe(isbn: null));

    expect($resultado->libro->descripcion)->toBeNull()
        ->and($resultado->libro->descripcion_procedencia)->toBeNull();
});
