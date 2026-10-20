<?php

use App\Ai\CoverReader;
use App\Enums\Source;
use App\Enums\Status;
use App\Intake\CoverUnreadable;
use App\Intake\ReadCover;
use App\Models\Book;
use App\Models\User;
use App\Sources\LookupResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    Storage::fake();
});

function coverPhoto(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('portada.jpg', testImage(400, 600));
}

it('llena los datos con lo que la IA leyó de la foto', function () {
    CoverReader::fake([[
        'title' => 'La estrella de Ratner',
        'authors' => ['Don DeLillo'],
        'publisher' => 'Seix Barral',
        'isbn' => '',
    ]]);
    sourcesAnswer(LookupResult::withoutRecord());

    $reading = app(ReadCover::class)->read(coverPhoto());

    expect($reading->title)->toBe('La estrella de Ratner')
        ->and($reading->authors)->toBe(['Don DeLillo'])
        ->and($reading->publisher)->toBe('Seix Barral')
        ->and($reading->isbn)->toBeNull()
        ->and($reading->fromSource)->toBeFalse();
});

it('prefiere la ficha de la Fuente cuando la foto trae un ISBN que conoce', function () {
    CoverReader::fake([[
        'title' => 'La estreya de Ratner',
        'authors' => ['Don Delilo'],
        'publisher' => '',
        'isbn' => '978-607-07-2536-4',
    ]]);
    sourcesAnswer(LookupResult::withRecord(recordOf('9786070725364')));

    $reading = app(ReadCover::class)->read(coverPhoto());

    expect($reading->fromSource)->toBeTrue()
        ->and($reading->isbn->value())->toBe('9786070725364')
        ->and($reading->title)->toBe(recordOf('9786070725364')->title);
});

it('descarta un ISBN leído cuyo dígito de control no cuadra', function () {
    CoverReader::fake([[
        'title' => 'Pedro Páramo',
        'authors' => ['Juan Rulfo'],
        'publisher' => '',
        'isbn' => '9788437604177',
    ]]);
    sourcesAnswer(LookupResult::withoutRecord());

    expect(app(ReadCover::class)->read(coverPhoto())->isbn)->toBeNull();
});

it('avisa que no pudo leer la foto cuando la IA no ve título ni ISBN', function () {
    CoverReader::fake([['title' => '', 'authors' => [], 'publisher' => '', 'isbn' => '']]);

    app(ReadCover::class)->read(coverPhoto());
})->throws(CoverUnreadable::class);

it('le manda la foto a la IA', function () {
    CoverReader::fake([['title' => 'Pedro Páramo', 'authors' => [], 'publisher' => '', 'isbn' => '']]);
    sourcesAnswer(LookupResult::withoutRecord());

    app(ReadCover::class)->read(coverPhoto());

    CoverReader::assertPrompted(fn ($prompt) => $prompt->attachments->isNotEmpty());
});

it('en la Captura manual la foto llena el formulario y queda como Portada al registrar', function () {
    $this->actingAs(User::factory()->create());

    CoverReader::fake([[
        'title' => 'Maestros de la parrilla',
        'authors' => ['Superama'],
        'publisher' => 'Walmart de México',
        'isbn' => '',
    ]]);
    sourcesAnswer(LookupResult::withoutRecord());

    Livewire::test('pages::manual-entry')
        ->set('coverPhoto', coverPhoto())
        ->assertSet('title', 'Maestros de la parrilla')
        ->assertSet('authors', ['Superama'])
        ->assertSet('publisher', 'Walmart de México')
        ->assertSee('Estos datos se leyeron de la foto: revísalos antes de registrar el Libro.')
        ->call('register')
        ->assertHasNoErrors();

    $book = Book::sole();

    expect($book->title)->toBe('Maestros de la parrilla')
        ->and($book->source)->toBe(Source::Manual)
        ->and($book->status)->toBe(Status::Read)
        ->and($book->hasCover())->toBeTrue();
});

it('en la Captura manual deja el formulario para escribir a mano cuando la foto no se pudo leer', function () {
    $this->actingAs(User::factory()->create());

    CoverReader::fake(fn () => throw new RuntimeException('DeepSeek answered 503'));

    Livewire::test('pages::manual-entry')
        ->set('title', 'Lo que ya escribí')
        ->set('coverPhoto', coverPhoto())
        ->assertSet('title', 'Lo que ya escribí')
        ->assertSee('No se pudieron leer los datos de la foto. Escríbelos a mano; la foto se queda como Portada.');
});

it('la Captura manual está en el menú', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('shelf'))->assertSee(route('manual-entry'), false);
});
