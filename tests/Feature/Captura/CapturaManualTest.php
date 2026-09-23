<?php

use App\Enums\Fuente;
use App\Enums\Situacion;
use App\Models\Libro;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();

    $this->actingAs(User::factory()->create());
});

/** La pantalla tal como llega desde el Escaneo o desde el Alta por título. */
function capturaManual(array $desde = []): Testable
{
    return Livewire::withQueryParams($desde)->test('pages::captura-manual');
}

it('pide sesión', function () {
    auth()->logout();

    $this->get(route('captura-manual'))->assertRedirect(route('login'));
});

it('llega desde el Escaneo con el ISBN ya puesto', function () {
    $this->get(route('captura-manual', ['isbn' => '9788437604176']))
        ->assertOk()
        ->assertSee('9788437604176');

    capturaManual(['isbn' => '9788437604176'])
        ->assertSet('isbn', '9788437604176')
        ->assertSet('titulo', '');
});

it('llega desde el Alta por título con el título ya puesto', function () {
    $this->get(route('captura-manual', ['titulo' => 'El llano en llamas']))
        ->assertOk()
        ->assertSee('El llano en llamas');

    capturaManual(['titulo' => 'El llano en llamas'])
        ->assertSet('titulo', 'El llano en llamas')
        ->assertSet('isbn', '');
});

it('registra un Libro con sólo el título', function () {
    capturaManual()
        ->set('titulo', 'Cartucho')
        ->call('registrar')
        ->assertHasNoErrors();

    $libro = Libro::sole();

    expect($libro->titulo)->toBe('Cartucho')
        ->and($libro->isbn)->toBeNull()
        ->and($libro->autores)->toBe([])
        ->and($libro->editorial)->toBeNull()
        ->and($libro->anio)->toBeNull();
});

it('no registra nada sin título', function () {
    capturaManual()
        ->set('titulo', '  ')
        ->call('registrar')
        ->assertHasErrors('titulo');

    expect(Libro::count())->toBe(0);
});

it('marca el Libro como capturado a mano', function () {
    capturaManual()->set('titulo', 'Cartucho')->call('registrar');

    expect(Libro::sole()->fuente)->toBe(Fuente::Manual);
});

it('conserva el orden en que se escribieron los Autores', function () {
    capturaManual()
        ->set('titulo', 'Cien años de soledad')
        ->call('agregarAutor')
        ->call('agregarAutor')
        ->set('autores.0', 'Gabriel García Márquez')
        ->set('autores.1', 'Carmen Balcells')
        ->set('autores.2', 'Gregory Rabassa')
        ->call('registrar')
        ->assertHasNoErrors();

    expect(Libro::sole()->autores)
        ->toBe(['Gabriel García Márquez', 'Carmen Balcells', 'Gregory Rabassa']);
});

it('descarta los renglones de Autor que quedaron en blanco', function () {
    capturaManual()
        ->set('titulo', 'Pedro Páramo')
        ->call('agregarAutor')
        ->call('agregarAutor')
        ->set('autores.0', 'Juan Rulfo')
        ->set('autores.1', '   ')
        ->set('autores.2', 'Jorge Zepeda')
        ->call('registrar');

    expect(Libro::sole()->autores)->toBe(['Juan Rulfo', 'Jorge Zepeda']);
});

it('quita un Autor sin descolocar a los demás', function () {
    capturaManual()
        ->call('agregarAutor')
        ->call('agregarAutor')
        ->set('autores.0', 'Primero')
        ->set('autores.1', 'Segundo')
        ->set('autores.2', 'Tercero')
        ->call('quitarAutor', 1)
        ->assertSet('autores', ['Primero', 'Tercero']);
});

it('guarda la Ficha completa cuando se escribe toda', function () {
    capturaManual()
        ->set('titulo', 'Noticias del Imperio')
        ->set('autores.0', 'Fernando del Paso')
        ->set('editorial', 'Diana')
        ->set('anio', '1987')
        ->set('edicion', 'Primera edición')
        ->set('isbn', '978-84-376-0417-6')
        ->call('registrar')
        ->assertHasNoErrors();

    $libro = Libro::sole();

    expect($libro->titulo)->toBe('Noticias del Imperio')
        ->and($libro->autores)->toBe(['Fernando del Paso'])
        ->and($libro->editorial)->toBe('Diana')
        ->and($libro->anio)->toBe(1987)
        ->and($libro->edicion)->toBe('Primera edición')
        // El ISBN se guarda normalizado a trece dígitos, como en el Escaneo.
        ->and($libro->isbn)->toBe('9788437604176');
});

it('registra el año de un libro anterior a 1901', function () {
    capturaManual()
        ->set('titulo', 'Los bandidos de Río Frío')
        ->set('anio', '1891')
        ->call('registrar')
        ->assertHasNoErrors();

    expect(Libro::sole()->anio)->toBe(1891);
});

it('avisa el año que no es un año', function () {
    capturaManual()
        ->set('titulo', 'Cartucho')
        ->set('anio', 'mil novecientos treinta y uno')
        ->call('registrar')
        ->assertHasErrors('anio');

    expect(Libro::count())->toBe(0);
});

it('avisa el ISBN que ya está registrado en vez de duplicarlo', function () {
    Libro::factory()->create([
        'isbn' => '9788437604176',
        'titulo' => 'Pedro Páramo',
    ]);

    capturaManual()
        ->set('titulo', 'Pedro Páramo, otra vez')
        ->set('isbn', '9788437604176')
        ->call('registrar')
        ->assertHasErrors('isbn')
        ->assertSee('«Pedro Páramo» ya está registrado con ese ISBN.');

    expect(Libro::count())->toBe(1);
});

it('reconoce el ISBN ya registrado aunque se escriba con diez dígitos', function () {
    Libro::factory()->create(['isbn' => '9788437604176', 'titulo' => 'Pedro Páramo']);

    capturaManual()
        ->set('titulo', 'Pedro Páramo, otra vez')
        ->set('isbn', '84-376-0417-X')
        ->call('registrar')
        ->assertHasErrors('isbn');

    expect(Libro::count())->toBe(1);
});

it('avisa el ISBN inválido', function () {
    capturaManual()
        ->set('titulo', 'Cartucho')
        ->set('isbn', '9788437604177')
        ->call('registrar')
        ->assertHasErrors('isbn')
        ->assertSee('«9788437604177» no es un ISBN.');

    expect(Libro::count())->toBe(0);
});

it('registra en el Acervo por omisión', function () {
    capturaManual()->set('titulo', 'Cartucho')->call('registrar');

    expect(Libro::sole()->situacion)->toBe(Situacion::Acervo);
});

it('registra Por comprar cuando se elige esa Situación', function () {
    capturaManual()
        ->set('titulo', 'Cartucho')
        ->set('situacion', Situacion::PorComprar->value)
        ->call('registrar')
        ->assertHasNoErrors();

    expect(Libro::sole()->situacion)->toBe(Situacion::PorComprar);
});

it('rechaza una Situación que no es ninguna de las dos', function () {
    capturaManual()
        ->set('titulo', 'Cartucho')
        ->set('situacion', 'prestado')
        ->call('registrar')
        ->assertHasErrors('situacion')
        ->assertSee('La Situación es Acervo o Por comprar.');

    expect(Libro::count())->toBe(0);
});

it('ofrece subir la Portada en cuanto el Libro queda registrado', function () {
    capturaManual()
        ->set('titulo', 'Cartucho')
        ->call('registrar')
        ->assertSee('«Cartucho» quedó registrado')
        ->assertSeeLivewire('portada-de-libro');
});

it('deja el Libro registrado aunque no se le suba ninguna Portada', function () {
    capturaManual()->set('titulo', 'Cartucho')->call('registrar');

    $libro = Libro::sole();

    expect($libro->tienePortada())->toBeFalse()
        // Sin URL de origen no hay descarga que reintentar: la única vía es
        // subirla a mano.
        ->and($libro->puedeReintentarLaPortada())->toBeFalse();

    Queue::assertNothingPushed();
});

it('sube la Portada del Libro recién capturado con el componente que ya existe', function () {
    Storage::fake();

    capturaManual()->set('titulo', 'Cartucho')->call('registrar');

    $libro = Libro::sole();

    Livewire::test('portada-de-libro', ['libro' => $libro])
        ->set('archivo', UploadedFile::fake()->createWithContent('portada.jpg', imagenDePrueba()))
        ->call('guardar')
        ->assertHasNoErrors();

    expect($libro->refresh()->tienePortada())->toBeTrue();
});

it('deja la pantalla en blanco para capturar el Libro siguiente', function () {
    capturaManual(['isbn' => '9788437604176'])
        ->set('titulo', 'Cartucho')
        ->call('registrar')
        ->call('otro')
        ->assertSet('titulo', '')
        ->assertSet('isbn', '')
        ->assertSet('autores', [''])
        ->assertSet('registrado', null)
        ->assertSet('situacion', Situacion::Acervo->value)
        ->assertSee('Registrar el Libro');

    expect(Libro::count())->toBe(1);
});
