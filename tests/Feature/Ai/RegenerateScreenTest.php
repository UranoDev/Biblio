<?php

use App\Ai\BookAgent;
use App\Enums\RegenerationTarget;
use App\Jobs\ProposeForBook;
use App\Models\AiSetting;
use App\Models\Book;
use App\Models\Regeneration;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    AiSetting::choose('gemini', 'gemini-3.6-flash');

    $this->actingAs(User::factory()->create());
});

function regenerateScreen(): Testable
{
    return Livewire::test('pages::settings.regenerate');
}

it('no es alcanzable sin sesión', function () {
    auth()->logout();

    $this->get(route('regenerate'))->assertRedirect(route('login'));
});

it('se abre desde la configuración', function () {
    $this->get(route('regenerate'))->assertOk();
});

// Sin AiModel elegido la pantalla lo explica en vez de fallar.

it('dice que la generación está apagada mientras no haya modelo elegido', function () {
    AiSetting::query()->delete();

    $this->get(route('regenerate'))
        ->assertOk()
        ->assertSee('Todavía no hay modelo elegido')
        ->assertDontSee('Lanzar la regeneración');
});

// Lo que dice antes de lanzar.

it('dice cuántos Books va a tocar y cuántas Descriptions deja fuera', function () {
    Book::factory()->count(2)->withoutDescription()->create();
    Book::factory()->descriptionFromOwner()->create();

    $screen = regenerateScreen()->set('onlyPrevious', false);

    expect($screen->get('plan')->books)->toBe(3)
        ->and($screen->get('plan')->kept)->toBe(1);

    $screen->assertSee('Se le va a pedir a la IA lo de 3 libros.')
        ->assertSee('descripción se queda como está');
});

it('dice cuántas descripciones va a traducir en vez de escribir', function () {
    Book::factory()->count(2)->descriptionInAnotherLanguage()->create();
    Book::factory()->withoutDescription()->create();

    $screen = regenerateScreen()->set('onlyPrevious', false);

    expect($screen->get('plan')->translated)->toBe(2);

    $screen->assertSee('2 descripciones no se van a escribir de nuevo')
        ->assertSee('se van a traducir al español');
});

it('no habla de traducciones cuando no hay ninguna que traducir', function () {
    Book::factory()->count(2)->withoutDescription()->create();

    regenerateScreen()
        ->set('onlyPrevious', false)
        ->assertDontSee('se van a traducir al español');
});

it('vuelve a contar al cambiar la selección', function () {
    Book::factory()->descriptionFromAi('gemini', 'gemini-2.5-flash')->create();
    Book::factory()->descriptionFromAi('gemini', 'gemini-3.6-flash')->create();

    $screen = regenerateScreen()->set('target', RegenerationTarget::Descriptions->value);

    expect($screen->set('onlyPrevious', true)->get('plan')->books)->toBe(1)
        ->and($screen->set('onlyPrevious', false)->get('plan')->books)->toBe(2);
});

// Lanzar.

it('pone los Books en la cola y dice cuántos', function () {
    Queue::fake();

    Book::factory()->count(2)->create();

    regenerateScreen()
        ->set('onlyPrevious', false)
        ->call('start')
        ->assertSee('2 libros están en la cola.');

    Queue::assertPushed(ProposeForBook::class, 2);

    expect(Regeneration::last()->total)->toBe(2);
});

it('lanza sólo lo que se eligió regenerar', function () {
    Queue::fake();

    Book::factory()->withoutDescription()->create();

    regenerateScreen()
        ->set('target', RegenerationTarget::Descriptions->value)
        ->set('onlyPrevious', false)
        ->call('start');

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->target === RegenerationTarget::Descriptions,
    );
});

it('lo dice cuando la selección no alcanza a ningún Book', function () {
    Queue::fake();

    regenerateScreen()
        ->set('onlyPrevious', false)
        ->call('start')
        ->assertSee('No hay libros que regenerar con esa selección.');

    Queue::assertNothingPushed();
});

// El avance sobrevive a recargar la pantalla.

it('muestra el avance de la corrida que ya estaba andando', function () {
    Queue::fake();

    Book::factory()->count(3)->create();

    regenerateScreen()->set('onlyPrevious', false)->call('start');

    // Otra visita a la misma dirección, como después de recargar: el avance
    // sale de la cola y no del componente que la lanzó.
    $this->get(route('regenerate'))
        ->assertOk()
        ->assertSee('En la cola')
        ->assertSee('Van 0 de 3');
});

it('deja de mostrarse en la cola cuando la corrida terminó', function () {
    // Sin Queue::fake(): la cola corre de verdad —en las pruebas es `sync`— y
    // lo que la pantalla lee es el batch ya terminado.
    BookAgent::fake(fn (): array => ['categories' => ['Novela mexicana']]);

    Book::factory()->create();

    regenerateScreen()->set('onlyPrevious', false)->call('start');

    $this->get(route('regenerate'))
        ->assertOk()
        ->assertSee('La última corrida terminó')
        ->assertDontSee('En la cola');
});

// Los que fallaron.

it('señala los Books que la cola dio por perdidos y los reintenta solos', function () {
    Queue::fake();

    $lost = Book::factory()->generationFailed()->create();
    Book::factory()->count(2)->create();

    regenerateScreen()
        ->assertSee('La cola se rindió con algunos')
        ->call('retryFailed')
        ->assertSee('Un libro volvió a la cola.');

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->book->is($lost),
    );

    Queue::assertPushed(ProposeForBook::class, 1);
});

it('no habla de fallos cuando no hay ninguno', function () {
    Book::factory()->create();

    regenerateScreen()->assertDontSee('La cola se rindió con algunos');
});
