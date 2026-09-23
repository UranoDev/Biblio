<?php

use App\Enums\Situacion;
use App\Fuentes\Candidatos;
use App\Jobs\DescargarPortada;
use App\Models\Libro;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();

    $this->actingAs(User::factory()->create());
});

/** Escribir el título y pedirle a la Fuente sus ediciones. */
function consultar(string $titulo = 'Pedro Páramo', ?string $autor = null): Testable
{
    $pantalla = Livewire::test('pages::alta-por-titulo')->set('titulo', $titulo);

    if ($autor !== null) {
        $pantalla->set('autor', $autor);
    }

    return $pantalla->call('consultar');
}

/** La clave con la que la pantalla apunta al Candidato de esa posición. */
function claveDelCandidato(Testable $pantalla, int $posicion = 0): string
{
    return $pantalla->get('candidatos')[$posicion]['clave'];
}

/** Las dos ediciones de siempre: mismo título, distinta editorial y distinto año. */
function dosEdiciones(): Candidatos
{
    return Candidatos::con([
        candidatoDe(
            isbn: '9788437604176',
            editorial: 'Cátedra',
            anio: 1997,
            portadaUrl: 'https://covers.example/catedra.jpg',
            referencia: 'catedra-1997',
        ),
        candidatoDe(
            isbn: '9786074454635',
            editorial: 'Editorial RM',
            anio: 2005,
            portadaUrl: 'https://covers.example/rm.jpg',
            referencia: 'rm-2005',
        ),
    ]);
}

it('pide sesión', function () {
    auth()->logout();

    $this->get(route('alta-por-titulo'))->assertRedirect(route('login'));
});

it('se abre con la Situación en Por comprar', function () {
    $this->get(route('alta-por-titulo'))->assertOk();

    Livewire::test('pages::alta-por-titulo')
        ->assertSet('situacion', Situacion::PorComprar->value);
});

it('muestra los Candidatos con Portada, autores, editorial y año', function () {
    lasFuentesDevuelven(dosEdiciones());

    consultar()
        ->assertCount('candidatos', 2)
        ->assertSee('Juan Rulfo')
        ->assertSee('Cátedra')
        ->assertSee('1997')
        ->assertSee('https://covers.example/catedra.jpg', false);
});

it('distingue dos ediciones del mismo título por editorial, año e ISBN', function () {
    lasFuentesDevuelven(dosEdiciones());

    consultar()
        ->assertSee('Cátedra')
        ->assertSee('Editorial RM')
        ->assertSee('1997')
        ->assertSee('2005')
        ->assertSee('9788437604176')
        ->assertSee('9786074454635');
});

it('le manda el Autor a la Fuente para acotar', function () {
    $recibido = [];

    lasFuentesDevuelven(function (string $titulo, ?string $autor) use (&$recibido): Candidatos {
        $recibido = [$titulo, $autor];

        return dosEdiciones();
    });

    consultar('Pedro Páramo', 'Juan Rulfo');

    expect($recibido)->toBe(['Pedro Páramo', 'Juan Rulfo']);
});

it('consulta sin Autor cuando no se escribió ninguno', function () {
    $recibido = ['', 'sin pasar'];

    lasFuentesDevuelven(function (string $titulo, ?string $autor) use (&$recibido): Candidatos {
        $recibido = [$titulo, $autor];

        return dosEdiciones();
    });

    consultar('Pedro Páramo');

    expect($recibido)->toBe(['Pedro Páramo', null]);
});

it('no consulta nada sin título', function () {
    lasFuentesDevuelven(dosEdiciones());

    Livewire::test('pages::alta-por-titulo')
        ->set('titulo', '  ')
        ->call('consultar')
        ->assertHasErrors('titulo')
        ->assertCount('candidatos', 0);
});

it('registra el Libro con la Situación elegida al elegir el Candidato', function () {
    lasFuentesDevuelven(dosEdiciones());

    $pantalla = consultar()->set('situacion', Situacion::Acervo->value);

    $pantalla->call('elegir', claveDelCandidato($pantalla, 1));

    $libro = Libro::sole();

    expect($libro->isbn)->toBe('9786074454635')
        ->and($libro->editorial)->toBe('Editorial RM')
        ->and($libro->anio)->toBe(2005)
        ->and($libro->situacion)->toBe(Situacion::Acervo);

    $pantalla->assertSet('registrado', $libro->getKey())->assertSee('quedó registrado');
});

it('registra Por comprar cuando no se cambia la Situación', function () {
    lasFuentesDevuelven(dosEdiciones());

    $pantalla = consultar();

    $pantalla->call('elegir', claveDelCandidato($pantalla));

    expect(Libro::sole()->situacion)->toBe(Situacion::PorComprar);
});

it('encola la Portada del Candidato que se eligió', function () {
    lasFuentesDevuelven(dosEdiciones());

    $pantalla = consultar();

    $pantalla->call('elegir', claveDelCandidato($pantalla));

    Queue::assertPushed(DescargarPortada::class);

    expect(Libro::sole()->portada_origen_url)->toBe('https://covers.example/catedra.jpg');
});

it('registra el Candidato que no trae ISBN', function () {
    lasFuentesDevuelven(Candidatos::con([
        candidatoDe(isbn: null, titulo: 'El llano en llamas', referencia: 'sin-isbn'),
    ]));

    $pantalla = consultar('El llano en llamas');

    $pantalla->assertSee('no trae ISBN')
        ->call('elegir', claveDelCandidato($pantalla));

    $libro = Libro::sole();

    expect($libro->titulo)->toBe('El llano en llamas')
        ->and($libro->isbn)->toBeNull();
});

it('avisa cuál es el Libro que ya tenía ese ISBN, en vez de duplicarlo', function () {
    Libro::factory()->create(['isbn' => '9788437604176', 'titulo' => 'Pedro Páramo']);

    lasFuentesDevuelven(dosEdiciones());

    $pantalla = consultar();

    $pantalla->call('elegir', claveDelCandidato($pantalla))
        ->assertSet('registrado', null)
        ->assertSee('ya está registrado con ese ISBN');

    expect(Libro::count())->toBe(1);
});

it('ofrece ir al Libro que ya estaba, en cuanto exista su ficha', function () {
    // La ficha del Libro llega con BIB-14. Hasta entonces no hay a dónde
    // mandar, y el aviso dice cuál es el Libro y ya.
    Route::middleware('web')->get('libros/{libro}', fn () => '')->name('libro');

    Libro::factory()->create(['isbn' => '9788437604176', 'titulo' => 'Pedro Páramo']);

    lasFuentesDevuelven(dosEdiciones());

    $pantalla = consultar();

    $pantalla->call('elegir', claveDelCandidato($pantalla))
        ->assertSee('Ir a «Pedro Páramo»');
});

it('deja elegir otra edición después de avisar que una ya estaba', function () {
    Libro::factory()->create(['isbn' => '9788437604176']);

    lasFuentesDevuelven(dosEdiciones());

    $pantalla = consultar();

    $pantalla->call('elegir', claveDelCandidato($pantalla))
        ->assertCount('candidatos', 2)
        ->call('elegir', claveDelCandidato($pantalla, 1));

    expect(Libro::count())->toBe(2)
        ->and(Libro::query()->where('isbn', '9786074454635')->exists())->toBeTrue();
});

it('ofrece Captura manual cuando ninguna Fuente tiene ese título, conservando lo escrito', function () {
    lasFuentesDevuelven(Candidatos::ninguno());

    consultar('Un título que nadie tiene', 'Quien sea')
        ->assertSet('sinEdiciones', true)
        ->assertSee('Ninguna Fuente tiene ediciones')
        ->assertSee(route('captura-manual', [
            'titulo' => 'Un título que nadie tiene',
            'autor' => 'Quien sea',
        ]));
});

it('la Fuente que no pudo contestar pide reintentar y no ofrece Captura manual', function () {
    lasFuentesDevuelven(Candidatos::noDisponible('HTTP 429'));

    consultar()
        ->assertSet('sinEdiciones', false)
        ->assertSee('no pudieron contestar')
        ->assertSee('Vuelve a intentarlo')
        ->assertDontSee('Capturar a mano');
});

it('deja la pantalla lista para el Libro siguiente', function () {
    lasFuentesDevuelven(dosEdiciones());

    $pantalla = consultar();

    $pantalla->call('elegir', claveDelCandidato($pantalla))
        ->call('otro')
        ->assertSet('titulo', '')
        ->assertSet('autor', '')
        ->assertSet('registrado', null)
        ->assertSet('situacion', Situacion::PorComprar->value)
        ->assertCount('candidatos', 0);
});

it('no usa la palabra «búsqueda»: aquí se consulta la Fuente', function () {
    lasFuentesDevuelven(dosEdiciones());

    $pantalla = consultar();

    foreach (['úsqueda', 'uscar', 'usca ', 'uscador'] as $prohibida) {
        $pantalla->assertDontSee($prohibida);
    }

    $pantalla->assertSee('Consultar la Fuente');
});
