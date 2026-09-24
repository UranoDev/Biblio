<?php

use App\Ai\BookAgent;
use App\Intake\AddBook;
use App\Jobs\ProposeForBook;
use App\Models\AiSetting;
use Illuminate\Support\Facades\Queue;

it('encola la generación al registrar un Book, sin esperar la respuesta', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    BookAgent::fake();
    Queue::fake();

    $book = app(AddBook::class)->fromRecord(recordOf('9789707802186'));

    Queue::assertPushed(
        ProposeForBook::class,
        fn (ProposeForBook $job): bool => $job->book->is($book)
            && $job->provider === 'gemini'
            && $job->aiModel === 'gemini-2.5-flash',
    );

    // Nothing answered yet: registering the Book did not wait for the API.
    BookAgent::assertNeverPrompted();
});

it('registral Book igual cuando todavía no hay AiModel elegido', function () {
    BookAgent::fake();

    $book = app(AddBook::class)->fromRecord(recordOf('9789707802186'));

    expect($book->exists)->toBeTrue();

    BookAgent::assertNeverQueued();
    BookAgent::assertNeverPrompted();
});
