<?php

use App\Enums\CoverOrigin;
use App\Enums\Source;
use App\Jobs\ReadCoverDraft;
use App\Models\Book;
use App\Models\CoverDraft;
use App\Models\DriveImport;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    Storage::fake();

    $this->actingAs(User::factory()->create());
});

/** A draft already read, as ReadCoverDraft leaves it. */
function readDraft(array $attributes = []): CoverDraft
{
    return coverDraft([
        'title' => 'Pedro Páramo',
        'authors' => ['Juan Rulfo'],
        'publisher' => 'RM',
        'read_at' => now(),
        ...$attributes,
    ]);
}

it('pide sesión', function () {
    $draft = readDraft();

    auth()->logout();

    $this->get(route('cover-drafts'))->assertRedirect(route('login'));
    $this->get(route('cover-draft.photo', $draft))->assertRedirect(route('login'));
});

it('lista las fotos por revisar con lo que se leyó de cada una', function () {
    $draft = readDraft();

    Livewire::test('pages::cover-drafts')
        ->assertSee('Pedro Páramo')
        ->assertSee('Juan Rulfo')
        ->assertSeeHtml('data-test="review-'.$draft->getKey().'"');
});

it('dice cuando no hay fotos por revisar', function () {
    Livewire::test('pages::cover-drafts')->assertSeeHtml('data-test="empty"');
});

it('dice cuando la carpeta del Drive no está configurada', function () {
    config(['drive.credentials' => null]);

    Livewire::test('pages::cover-drafts')->assertSee('La carpeta del Drive no está configurada');
});

it('dice cuándo se revisó la carpeta por última vez, y si falló', function () {
    configureDrive();

    DriveImport::recordFailure('invalid_grant');

    Livewire::test('pages::cover-drafts')->assertSee('La última revisión de la carpeta falló');

    DriveImport::recordPass();

    Livewire::test('pages::cover-drafts')->assertSee('Última revisión de la carpeta');
});

it('sirve la foto del borrador', function () {
    $draft = readDraft();

    $this->get(route('cover-draft.photo', $draft))->assertOk();
});

it('descarta un borrador junto con su foto', function () {
    $draft = readDraft();

    Livewire::test('pages::cover-drafts')->call('discard', $draft->getKey());

    expect(CoverDraft::query()->count())->toBe(0);
    Storage::disk()->assertMissing($draft->photo_path);
    expect(Book::query()->count())->toBe(0);
});

it('vuelve a leer una foto que no se pudo leer', function () {
    $draft = coverDraft(['read_at' => now(), 'read_failure' => 'nothing legible']);

    Livewire::test('pages::cover-drafts')->call('readAgain', $draft->getKey());

    expect($draft->fresh()->isRead())->toBeFalse();
    Queue::assertPushed(ReadCoverDraft::class);
});

it('abre la Captura manual llena con lo que se leyó de la foto', function () {
    $draft = readDraft(['year' => 1955]);

    Livewire::withQueryParams(['draft' => $draft->getKey()])
        ->test('pages::manual-entry')
        ->assertSet('draft', $draft->getKey())
        ->assertSet('title', 'Pedro Páramo')
        ->assertSet('authors', ['Juan Rulfo'])
        ->assertSet('publisher', 'RM')
        ->assertSet('year', '1955')
        ->assertSee('Estos datos se leyeron de la foto');
});

it('registra el Libro con la foto como Portada y quita el borrador', function () {
    $draft = readDraft();

    Livewire::withQueryParams(['draft' => $draft->getKey()])
        ->test('pages::manual-entry')
        ->set('title', 'Pedro Páramo (corregido)')
        ->call('register')
        ->assertHasNoErrors();

    $book = Book::sole();

    expect($book->title)->toBe('Pedro Páramo (corregido)')
        ->and($book->source)->toBe(Source::Manual)
        ->and($book->cover_origin)->toBe(CoverOrigin::Owner)
        ->and(CoverDraft::query()->count())->toBe(0);

    Storage::disk()->assertExists($book->cover_path);
    Storage::disk()->assertMissing($draft->photo_path);
});

it('no registra un ISBN repetido y conserva el borrador', function () {
    $book = Book::factory()->create(['isbn' => '9786070725364']);
    $draft = readDraft(['isbn' => '9786070725364']);

    Livewire::withQueryParams(['draft' => $draft->getKey()])
        ->test('pages::manual-entry')
        ->call('register')
        ->assertHasErrors('isbn');

    expect(CoverDraft::query()->count())->toBe(1)
        ->and(Book::query()->count())->toBe(1);
});

it('lleva a la siguiente foto por revisar después de registrar', function () {
    $draft = readDraft();
    readDraft(['title' => 'El llano en llamas']);

    Livewire::withQueryParams(['draft' => $draft->getKey()])
        ->test('pages::manual-entry')
        ->call('register')
        ->assertSeeHtml('data-test="next-draft"');
});

it('cuenta las fotos por revisar en el Tablero', function () {
    readDraft();
    readDraft();

    Livewire::test('pages::dashboard')->assertSeeHtml('data-test="counter-cover-drafts"');
    expect(CoverDraft::query()->count())->toBe(2);
});
