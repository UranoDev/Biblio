<?php

use App\Ai\BookAgent;
use App\Ai\Proposals;
use App\Enums\GenerationStatus;
use App\Jobs\ProposeForBook;
use App\Models\AiSetting;
use App\Models\Book;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Tests\Support\PretendJob;

beforeEach(function () {
    AiSetting::choose('gemini', 'gemini-3.6-flash');
});

/**
 * Runs the job the way a worker does: its middleware first, then handle(). What
 * comes back is the queue's side of it — whether the job went back in line and
 * for how long, or whether it failed.
 */
function runProposal(ProposeForBook $job, int $attempts = 1): PretendJob
{
    $pretend = new PretendJob($job, $attempts);

    $job->setJob($pretend);

    (new Pipeline(app()))
        ->send($job)
        ->through($job->middleware())
        ->then(fn (ProposeForBook $job) => app()->call([$job, 'handle']));

    return $pretend;
}

function proposalFor(Book $book): ProposeForBook
{
    return new ProposeForBook($book, 'gemini', 'gemini-3.6-flash');
}

/** What the provider answers when it refuses at the door, without translating. */
function refusedWith(int $status, string $message = 'Rate limit exceeded'): RequestException
{
    return new RequestException(new Response(new Psr7Response(
        $status,
        ['Content-Type' => 'application/json'],
        json_encode(['error' => ['message' => $message]]),
    )));
}

// Lo transitorio vuelve a la cola.

it('devuelve el job a la cola ante un 429, sin fallar ni escribir nada', function () {
    BookAgent::fake(fn () => throw RateLimitedException::forProvider('gemini', 429));

    $book = Book::factory()->withoutDescription()->create([
        'categories_ai_model' => null,
        'categories_provider' => null,
    ]);

    $job = runProposal(proposalFor($book));

    expect($job->isReleased())->toBeTrue()
        ->and($job->hasFailed())->toBeFalse()
        ->and($job->releasedFor)->toBeGreaterThan(0);

    $book = $book->fresh();

    expect($book->categories)->toBeEmpty()
        ->and($book->categories_ai_model)->toBeNull()
        ->and($book->description)->toBeNull()
        ->and($book->generation_failed_at)->toBeNull()
        ->and($book->categoriesStatus())->toBe(GenerationStatus::Pending);
});

it('no gasta intentos: los deja sin límite y pone el tope en el tiempo', function () {
    config(['proposals.give_up_after' => 6]);

    $job = proposalFor(Book::factory()->create());

    $this->freezeTime();

    expect($job->tries)->toBe(0)
        ->and($job->retryUntil()->getTimestamp())->toBe(now()->addHours(6)->getTimestamp());
});

it('reconoce el 429 que llega crudo, sin que el SDK lo traduzca', function () {
    // The trace that opened BIB-33 carried exactly this: a RequestException
    // with the 429 inside and no exception of the SDK around it.
    BookAgent::fake(fn () => throw refusedWith(429));

    $job = runProposal(proposalFor(Book::factory()->create()));

    expect($job->isReleased())->toBeTrue()
        ->and($job->hasFailed())->toBeFalse();
});

it('devuelve el job a la cola ante un 503', function () {
    BookAgent::fake(fn () => throw ProviderOverloadedException::forProvider('gemini', 503));

    $job = runProposal(proposalFor(Book::factory()->create()));

    expect($job->isReleased())->toBeTrue()
        ->and($job->hasFailed())->toBeFalse();
});

it('devuelve el job a la cola ante cualquier 5xx', function (int $status) {
    BookAgent::fake(fn () => throw refusedWith($status, 'high demand'));

    $job = runProposal(proposalFor(Book::factory()->create()));

    expect($job->isReleased())->toBeTrue()
        ->and($job->hasFailed())->toBeFalse();
})->with([500, 502, 503, 504]);

it('devuelve el job a la cola cuando se agota el tiempo de espera', function () {
    BookAgent::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    $job = runProposal(proposalFor(Book::factory()->create()));

    expect($job->isReleased())->toBeTrue()
        ->and($job->hasFailed())->toBeFalse();
});

it('espera cada vez más entre intentos', function () {
    config(['proposals.first_wait' => 60, 'proposals.longest_wait' => 900]);

    BookAgent::fake(fn () => throw ProviderOverloadedException::forProvider('gemini', 503));

    $book = Book::factory()->create();

    $waits = [];

    foreach (range(1, 4) as $attempt) {
        $waits[] = runProposal(proposalFor($book), $attempt)->releasedFor;
    }

    expect($waits)->toBe([60, 120, 240, 480]);
});

it('la espera tiene un techo', function () {
    config(['proposals.first_wait' => 60, 'proposals.longest_wait' => 120]);

    BookAgent::fake(fn () => throw ProviderOverloadedException::forProvider('gemini', 503));

    $book = Book::factory()->create();

    $waits = [];

    foreach (range(1, 4) as $attempt) {
        $waits[] = runProposal(proposalFor($book), $attempt)->releasedFor;
    }

    expect($waits)->toBe([60, 120, 120, 120]);
});

it('la espera vuelve a empezar después de una generación que sí pasó', function () {
    config(['proposals.first_wait' => 60, 'proposals.longest_wait' => 900]);

    $asked = 0;

    BookAgent::fake(function () use (&$asked) {
        $asked++;

        return $asked === 3
            ? ['categories' => ['Novela mexicana']]
            : throw ProviderOverloadedException::forProvider('gemini', 503);
    });

    $book = Book::factory()->create();

    runProposal(proposalFor($book), 1);
    runProposal(proposalFor($book), 2);
    runProposal(proposalFor($book), 3);

    expect(runProposal(proposalFor($book), 4)->releasedFor)->toBe(60);
});

// Lo definitivo falla de inmediato.

it('falla de inmediato con una llave inválida', function () {
    BookAgent::fake(fn () => throw refusedWith(401, 'API key not valid'));

    $book = Book::factory()->create(['categories_ai_model' => null, 'categories_provider' => null]);

    $job = runProposal(proposalFor($book));

    expect($job->hasFailed())->toBeTrue()
        ->and($job->isReleased())->toBeFalse();

    $book = $book->fresh();

    expect($book->categoriesStatus())->toBe(GenerationStatus::Failed)
        ->and($book->generation_failure)->toContain('API key not valid');
});

it('falla de inmediato con un modelo inexistente', function () {
    BookAgent::fake(fn () => throw refusedWith(404, 'models/gemini-inventado is not found'));

    $job = runProposal(proposalFor(Book::factory()->create()));

    expect($job->hasFailed())->toBeTrue()
        ->and($job->isReleased())->toBeFalse();
});

it('falla de inmediato cuando se acaba el crédito, que esperar no repone', function () {
    BookAgent::fake(fn () => throw InsufficientCreditsException::forProvider('gemini', 402));

    $job = runProposal(proposalFor(Book::factory()->create()));

    expect($job->hasFailed())->toBeTrue()
        ->and($job->isReleased())->toBeFalse();
});

it('falla de inmediato cuando la respuesta no cumple el esquema', function () {
    BookAgent::fake([['categories' => []]]);

    $book = Book::factory()->withoutDescription()->create([
        'categories_ai_model' => null,
        'categories_provider' => null,
    ]);

    $job = runProposal(proposalFor($book));

    expect($job->hasFailed())->toBeTrue()
        ->and($job->isReleased())->toBeFalse();

    $book = $book->fresh();

    expect($book->categories)->toBeEmpty()
        ->and($book->description)->toBeNull()
        ->and($book->categoriesStatus())->toBe(GenerationStatus::Failed);
});

it('falla de inmediato ante un error que no se sabe qué es', function () {
    // Unknown counts as a failure and not as a wait: getting it wrong this way
    // shows the error too early, and the other way hides it for hours.
    BookAgent::fake(fn () => throw new RuntimeException('Something nobody has seen.'));

    $job = runProposal(proposalFor(Book::factory()->create()));

    expect($job->hasFailed())->toBeTrue()
        ->and($job->isReleased())->toBeFalse();
});

it('volver a encolar saca al Book de la falla y lo pone a esperar turno', function () {
    $book = Book::factory()->generationFailed()->create([
        'categories_ai_model' => null,
        'categories_provider' => null,
    ]);

    expect($book->categoriesStatus())->toBe(GenerationStatus::Failed);

    Queue::fake();

    expect(app(Proposals::class)->queueFor($book))->toBeTrue();

    Queue::assertPushed(ProposeForBook::class);

    expect($book->fresh()->categoriesStatus())->toBe(GenerationStatus::Pending);
});

// El ritmo.

it('respeta el ritmo configurado y lo cuenta en el cache compartido', function () {
    config(['proposals.requests_per_minute' => 2]);

    BookAgent::fake(fn () => ['categories' => ['Novela mexicana']]);

    $books = Book::factory()->count(3)->create();

    // Three different jobs, each one a fresh instance: the count cannot be
    // living in the memory of any of them.
    $first = runProposal(proposalFor($books[0]));
    $second = runProposal(proposalFor($books[1]));
    $third = runProposal(proposalFor($books[2]));

    expect($first->isReleased())->toBeFalse()
        ->and($second->isReleased())->toBeFalse()
        ->and($third->isReleased())->toBeTrue()
        ->and($third->hasFailed())->toBeFalse();

    // The third one never reached the provider, so it has nothing written.
    expect($books[2]->fresh()->categories_ai_model)->toBeNull();

    // And once the minute is over it goes through on its own.
    $this->travel(70)->seconds();

    expect(runProposal(proposalFor($books[2]), 2)->isReleased())->toBeFalse()
        ->and($books[2]->fresh()->categories_ai_model)->toBe('gemini-3.6-flash');
});

it('capturar doscientos Books seguidos no deja ni un job fallido', function () {
    config(['proposals.requests_per_minute' => 5]);

    BookAgent::fake(fn () => ['categories' => ['Novela mexicana']]);

    $books = Book::factory()->count(200)->create([
        'categories_ai_model' => null,
        'categories_provider' => null,
    ]);

    $started = now();

    /** @var list<array{job: ProposeForBook, at: Carbon, attempts: int}> $line */
    $line = $books->map(fn (Book $book): array => [
        'job' => proposalFor($book),
        'at' => now(),
        'attempts' => 0,
    ])->all();

    $failed = 0;

    while ($line !== []) {
        $ready = null;

        foreach ($line as $position => $entry) {
            if ($entry['at'] <= now()) {
                $ready = $position;
                break;
            }
        }

        if ($ready === null) {
            // The worker has nothing to take: the whole line is waiting its
            // turn, so the clock moves to when the first one comes back.
            $this->travelTo(min(array_column($line, 'at')));

            continue;
        }

        $entry = $line[$ready];

        unset($line[$ready]);

        $line = array_values($line);

        $entry['attempts']++;

        $job = runProposal($entry['job'], $entry['attempts']);

        if ($job->hasFailed()) {
            $failed++;
        }

        if ($job->isReleased()) {
            $entry['at'] = now()->addSeconds((int) $job->releasedFor);

            $line[] = $entry;
        }
    }

    expect($failed)->toBe(0)
        ->and(Book::query()->whereNull('categories_ai_model')->count())->toBe(0);

    // Two hundred Books at five a minute is forty minutes of queue, and that is
    // fine: what it cannot be is forty minutes of failures.
    expect($started->diffInMinutes(now()))->toBeGreaterThanOrEqual(38);
});
