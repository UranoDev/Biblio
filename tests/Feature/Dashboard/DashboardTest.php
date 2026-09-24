<?php

use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Dashboard (BIB-40): where the pending work is.
 *
 * What is tested is not that the numbers come out but that each one **opens its
 * list**: every counter is followed to wherever it leads, and there the Books
 * that arrived are checked against the ones it counted. A counter leading
 * nowhere would pass a test that only looked at the number.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** The screen, as HTML. */
function dashboardHtml(): string
{
    return (string) test()->get(route('dashboard'))->getContent();
}

/** The number the screen prints for one counter. */
function counted(string $key): int
{
    preg_match(
        '/data-test="total-'.preg_quote($key, '/').'"[^>]*>\s*([0-9]+)/',
        dashboardHtml(),
        $matches,
    );

    expect($matches)->not->toBeEmpty("El contador «{$key}» no está en la pantalla.");

    return (int) $matches[1];
}

/** The address that counter opens. */
function opens(string $key): string
{
    preg_match(
        '/<a\s[^>]*data-test="counter-'.preg_quote($key, '/').'"/',
        dashboardHtml(),
        $tag,
    );

    expect($tag)->not->toBeEmpty("El contador «{$key}» no es un enlace.");

    preg_match('/href="([^"]+)"/', $tag[0], $href);

    return html_entity_decode($href[1]);
}

it('pide sesión', function () {
    auth()->logout();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('abre sin el relleno del esqueleto', function () {
    Book::factory()->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Inicio')
        ->assertDontSee('placeholder-pattern');
});

/*
|--------------------------------------------------------------------------
| Cada número lleva a su lista
|--------------------------------------------------------------------------
*/

it('cuenta las Portadas que siguen generadas y abre esa lista', function () {
    Book::factory()->coverGenerated()->create(['title' => 'Sin fotografiar']);
    Book::factory()->create(['title' => 'Con su imagen']);

    expect(counted('cover-generated'))->toBe(1);

    $this->get(opens('cover-generated'))
        ->assertSee('Sin fotografiar')
        ->assertDontSee('Con su imagen');
});

it('cuenta los Books que esperan a la IA y abre esa lista', function () {
    Book::factory()->generationQueued()->create(['title' => 'En la cola']);
    Book::factory()->create(['title' => 'Ya tiene lo suyo']);

    expect(counted('generation-queued'))->toBe(1);

    $this->get(opens('generation-queued'))
        ->assertSee('En la cola')
        ->assertDontSee('Ya tiene lo suyo');
});

it('cuenta las generaciones que fallaron y abre esa lista', function () {
    Book::factory()->generationFailed()->create(['title' => 'Se rompió']);
    Book::factory()->generationQueued()->create(['title' => 'Sigue esperando']);

    expect(counted('generation-failed'))->toBe(1);

    $this->get(opens('generation-failed'))
        ->assertSee('Se rompió')
        ->assertDontSee('Sigue esperando');
});

it('cuenta los Books sin ninguna Quote y abre esa lista', function () {
    $withQuote = Book::factory()->create(['title' => 'Ya tiene cita']);
    Quote::factory()->for($withQuote)->create();

    Book::factory()->create(['title' => 'Nada escrito']);

    expect(counted('without-quotes'))->toBe(1);

    $this->get(opens('without-quotes'))
        ->assertSee('Nada escrito')
        ->assertDontSee('Ya tiene cita');
});

it('separa el Acervo de lo que está Por comprar, y cada uno abre su lista', function () {
    Book::factory()->owned()->create(['title' => 'Ya es mío']);
    Book::factory()->wanted()->count(2)->create();

    expect(counted('owned'))->toBe(1)
        ->and(counted('wanted'))->toBe(2);

    $this->get(opens('owned'))->assertSee('Ya es mío');
    $this->get(opens('wanted'))->assertDontSee('Ya es mío');
});

it('separa lo leído de lo que está por leer, y cada uno abre su lista', function () {
    Book::factory()->owned()->read()->create(['title' => 'Con fecha']);
    Book::factory()->owned()->readUndated()->create(['title' => 'Sin fecha']);
    Book::factory()->owned()->create(['title' => 'Pendiente']);

    // «Leído» is the question «¿lo leí?»: both read states, dated and not,
    // which is exactly what a filter of three states does not answer.
    expect(counted('finished'))->toBe(2)
        ->and(counted('unread'))->toBe(1);

    $this->get(opens('finished'))
        ->assertSee('Con fecha')
        ->assertSee('Sin fecha')
        ->assertDontSee('Pendiente');

    $this->get(opens('unread'))
        ->assertSee('Pendiente')
        ->assertDontSee('Con fecha');
});

it('no cuenta como por leer lo que ni siquiera se ha comprado', function () {
    Book::factory()->wanted()->create();

    expect(counted('unread'))->toBe(0);
});

it('cuenta las Quotes escritas y abre la búsqueda de citas', function () {
    Quote::factory()->count(3)->create();

    expect(counted('quotes'))->toBe(3)
        ->and(opens('quotes'))->toBe(route('quote-search'));
});

it('cuenta los trabajos que la cola dejó de intentar y abre su lista', function () {
    Book::factory()->create();

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => (string) json_encode(['displayName' => 'App\\Jobs\\GenerateForBook']),
        'exception' => "RuntimeException: el proveedor no contestó\n#0 /app/foo.php(1)",
        'failed_at' => now(),
    ]);

    expect(counted('failed-jobs'))->toBe(1);

    $this->get(opens('failed-jobs'))
        ->assertOk()
        ->assertSee('GenerateForBook')
        ->assertSee('el proveedor no contestó');
});

/*
|--------------------------------------------------------------------------
| El cero
|--------------------------------------------------------------------------
*/

it('enseña un contador en cero como una cosa terminada', function () {
    Book::factory()->coverFromOwner()->create();

    $html = dashboardHtml();

    expect(counted('cover-generated'))->toBe(0)
        ->and($html)->toContain('Ninguna portada quedó generada.')
        ->and($html)->toContain('Ningún trabajo falló.');
});

it('no dice que el trabajo está hecho cuando no hay ni un Libro registrado', function () {
    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Todavía no has registrado ningún Libro.')
        ->assertDontSee('Ninguna portada quedó generada.')
        ->assertSee('Escanear uno');
});

/*
|--------------------------------------------------------------------------
| El costo
|--------------------------------------------------------------------------
*/

it('cuesta las mismas consultas con doscientos Libros que con dos', function () {
    Book::factory()->count(2)->create();

    DB::enableQueryLog();
    $this->get(route('dashboard'))->assertOk();
    $small = count(DB::getQueryLog());
    DB::flushQueryLog();

    Book::factory()->count(200)->create();
    DB::flushQueryLog();

    $this->get(route('dashboard'))->assertOk();
    $large = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($large)->toBe($small);
});

/*
|--------------------------------------------------------------------------
| Lo que depende de una configuración
|--------------------------------------------------------------------------
*/

it('avisa que sin modelo elegido la IA no va a escribir nada', function () {
    Book::factory()->create();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Todavía no hay modelo elegido')
        ->assertSee(route('ai-model.edit'), escape: false);
});

it('no avisa nada cuando ya hay un modelo elegido', function () {
    Book::factory()->create();

    AiSetting::choose('gemini', 'gemini-2.5-flash');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Todavía no hay modelo elegido');
});
