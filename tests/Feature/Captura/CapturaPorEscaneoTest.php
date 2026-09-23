<?php

use App\Enums\Situacion;
use App\Fuentes\Respuesta;
use App\Jobs\DescargarPortada;
use App\Models\Libro;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();

    $this->actingAs(User::factory()->create());
});

/** Escanear un ISBN es escribirlo en el campo y que llegue el Enter. */
function escanear(Testable $pantalla, string $isbn): Testable
{
    return $pantalla->set('isbn', $isbn)->call('registrar');
}

it('pide sesión', function () {
    auth()->logout();

    $this->get(route('captura'))->assertRedirect(route('login'));
});

it('se abre con el campo del ISBN enfocado', function () {
    $this->get(route('captura'))
        ->assertOk()
        ->assertSee('autofocus', false)
        ->assertSee('x-ref="isbn"', false);
});

it('devuelve el foco al campo después de cada Escaneo', function () {
    lasFuentesContestan(fn ($isbn) => Respuesta::conFicha(fichaDe((string) $isbn)));

    escanear(Livewire::test('pages::captura'), isbnDePrueba(1))
        ->assertDispatched('enfocar-el-isbn')
        ->assertSet('isbn', '');
});

it('registra veinte Libros seguidos sin un clic entre uno y otro', function () {
    lasFuentesContestan(fn ($isbn) => Respuesta::conFicha(fichaDe(
        isbn: (string) $isbn,
        titulo: 'Libro '.$isbn,
    )));

    $pantalla = Livewire::test('pages::captura');

    foreach (range(1, 20) as $numero) {
        escanear($pantalla, isbnDePrueba($numero))->assertDispatched('enfocar-el-isbn');
    }

    expect(Libro::count())->toBe(20);

    $pantalla->assertCount('rafaga', 20);
});

it('no abre ningún modal, pase lo que pase con el Escaneo', function () {
    $desenlaces = [
        fn () => lasFuentesContestan(Respuesta::conFicha(fichaDe('9788437604176'))),
        fn () => lasFuentesContestan(Respuesta::sinFicha()),
        fn () => lasFuentesContestan(Respuesta::noDisponible('Google Books: cuota agotada')),
    ];

    foreach ($desenlaces as $preparar) {
        $preparar();

        escanear(Livewire::test('pages::captura'), '9788437604176')
            ->assertDispatched('enfocar-el-isbn')
            ->assertDontSee('<ui-modal', false)
            ->assertDontSee('role="dialog"', false)
            ->assertDontSee('data-flux-modal', false);
    }

    // Y el ISBN inválido, que no llega a consultar ninguna Fuente.
    escanear(Livewire::test('pages::captura'), '1234567890')
        ->assertDispatched('enfocar-el-isbn')
        ->assertDontSee('<ui-modal', false)
        ->assertDontSee('role="dialog"', false);
});

it('avisa que el Libro ya estaba en el Acervo', function () {
    Libro::factory()->enAcervo()->create([
        'isbn' => '9788437604176',
        'titulo' => 'Pedro Páramo',
    ]);

    escanear(Livewire::test('pages::captura'), '9788437604176')
        ->assertSee('«Pedro Páramo» ya está en el Acervo.')
        ->assertDontSee('Deshacer');

    expect(Libro::count())->toBe(1);
});

it('avisa el paso de Por comprar al Acervo y lo deja deshacer', function () {
    $libro = Libro::factory()->porComprar()->create([
        'isbn' => '9788437604176',
        'titulo' => 'Pedro Páramo',
    ]);

    $pantalla = escanear(Livewire::test('pages::captura'), '9788437604176')
        ->assertSee('«Pedro Páramo» estaba Por comprar y pasó al Acervo.')
        ->assertSee('Deshacer');

    expect($libro->fresh()->situacion)->toBe(Situacion::Acervo);

    $pantalla->call('deshacer', $pantalla->get('rafaga')[0]['clave'])
        ->assertSee('«Pedro Páramo» vuelve a estar Por comprar.')
        ->assertSee('Regresó a Por comprar')
        ->assertDispatched('enfocar-el-isbn');

    expect($libro->fresh()->situacion)->toBe(Situacion::PorComprar);
});

it('muestra la ráfaga con lo más reciente arriba', function () {
    lasFuentesContestan(fn ($isbn) => Respuesta::conFicha(fichaDe(
        isbn: (string) $isbn,
        titulo: 'Libro '.$isbn,
    )));

    $pantalla = Livewire::test('pages::captura');

    escanear($pantalla, isbnDePrueba(1));
    escanear($pantalla, isbnDePrueba(2));

    $rafaga = $pantalla->get('rafaga');

    expect($rafaga[0]['isbn'])->toBe(isbnDePrueba(2))
        ->and($rafaga[1]['isbn'])->toBe(isbnDePrueba(1));
});

it('guarda el ISBN que ninguna Fuente conoce para la Captura manual', function () {
    lasFuentesContestan(Respuesta::sinFicha());

    escanear(Livewire::test('pages::captura'), '978-84-376-0417-6')
        ->assertSet('isbnSinFuente', '9788437604176')
        ->assertSee('Ninguna Fuente conoce el ISBN 9788437604176.')
        ->assertSet('isbn', '');
});

it('ofrece la Captura manual con el ISBN que ninguna Fuente conoce', function () {
    lasFuentesContestan(Respuesta::sinFicha());

    escanear(Livewire::test('pages::captura'), '9788437604176')
        ->assertSee(route('captura-manual', ['isbn' => '9788437604176']), false);
});

it('avisa el ISBN inválido y no interrumpe la ráfaga', function () {
    lasFuentesContestan(fn ($isbn) => Respuesta::conFicha(fichaDe((string) $isbn)));

    $pantalla = escanear(Livewire::test('pages::captura'), '9788437604177')
        ->assertSee('«9788437604177» no es un ISBN.');

    expect(Libro::count())->toBe(0);

    escanear($pantalla, isbnDePrueba(1))->assertSee('quedó registrado.');

    expect(Libro::count())->toBe(1);
});

it('encola la Portada del Libro que se registra en la ráfaga', function () {
    lasFuentesContestan(Respuesta::conFicha(fichaDe(
        isbn: '9788437604176',
        portadaUrl: 'https://covers.openlibrary.org/b/id/12345-L.jpg',
    )));

    escanear(Livewire::test('pages::captura'), '9788437604176');

    Queue::assertPushed(DescargarPortada::class);
});

it('no registra nada cuando el campo llega vacío', function () {
    escanear(Livewire::test('pages::captura'), '   ')
        ->assertCount('rafaga', 0)
        ->assertDispatched('enfocar-el-isbn');

    expect(Libro::count())->toBe(0);
});
