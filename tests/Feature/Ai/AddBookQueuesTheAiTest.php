<?php

use App\Ai\BookAgent;
use App\Intake\AddBook;
use App\Models\AiSetting;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Jobs\InvokeAgent;

it('encola la generación al registrar un Book, sin esperar la respuesta', function () {
    AiSetting::choose('gemini', 'gemini-2.5-flash');

    BookAgent::fake();
    Queue::fake();

    app(AddBook::class)->fromRecord(recordOf('9789707802186'));

    Queue::assertPushed(InvokeAgent::class);

    BookAgent::assertQueued(fn ($prompt) => $prompt->contains('Pedro Páramo'));

    // Nothing answered yet: registering the Book did not wait for the API.
    BookAgent::assertNeverPrompted();
});

it('registral Book igual cuando todavía no hay AiModel elegido', function () {
    BookAgent::fake();

    $book = app(AddBook::class)->fromRecord(recordOf('9789707802186'));

    expect($book->exists)->toBeTrue();

    BookAgent::assertNeverQueued();
});
