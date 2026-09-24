{{--
    What the AI is doing about one half of a Book —its Categories, its
    Description— in a clause that goes after the sentence describing that half
    (BIB-33, BIB-46).

    It exists because the two sections of the sheet used to answer the same
    question in their own way, and one of them answered it by guessing: a Book
    with no Description was announced as being «en la cola de» whichever AiModel
    was chosen at that moment, whether or not anything had ever been queued for
    it. Twelve hours after a job died that sentence was still there, naming a
    provider that had never seen the request.

    So a wait is only ever said of a Book carrying the mark of the queue, the
    provider and the AiModel named are the ones the request travelled with, and
    the Book nobody ever asked about says that instead.

    `what` is what the AI was going to do, in the infinitive: «escribirla»,
    «traducirla», «proponerlas». A Book with nothing pending renders nothing.
--}}

@props(['book', 'status', 'what'])

@php
    $aiModel = $book->generation_ai_model;
    $provider = $book->generation_provider;
    $failedAt = $book->generationFailedAt();

    // When it happened, and how long ago. The «hace doce horas» is the half that
    // makes the difference readable: a moment on its own invites nobody to
    // notice that the wait it describes ended last night.
    $when = $failedAt === null
        ? null
        : 'el '.$failedAt->translatedFormat('d M Y, H:i').', '.$failedAt->diffForHumans();

    $with = $aiModel === null
        ? null
        : 'con '.$aiModel.($provider === null ? '' : ', del proveedor '.$provider);

    $sentence = match (true) {
        // Nothing came back at all: no answer and no failure either, so there is
        // nothing from the provider to quote. What there is to say is when it
        // was queued and with what.
        $status === \App\Enums\GenerationStatus::Failed && $book->generationWasAbandoned() => implode(' ', array_filter([
            'Se encoló', $when, $with, 'y la cola nunca volvió a contestar.',
        ])),
        $status === \App\Enums\GenerationStatus::Failed => implode(' ', array_filter([
            'La IA dejó de intentar '.$what, $when, $with,
        ])).'.',
        $status === \App\Enums\GenerationStatus::Pending => $aiModel === null
            ? 'Está en la cola.'
            : 'Está en la cola de '.$aiModel.'.',
        $status === \App\Enums\GenerationStatus::NotAsked => 'Todavía no se le ha pedido a la IA '.$what.'.',
        $status === \App\Enums\GenerationStatus::Off => 'Falta elegir el modelo para '.$what.'.',
        default => null,
    };

    $mark = match ($status) {
        \App\Enums\GenerationStatus::Failed => 'generation-failed',
        \App\Enums\GenerationStatus::Pending => 'generation-pending',
        \App\Enums\GenerationStatus::NotAsked => 'generation-not-asked',
        \App\Enums\GenerationStatus::Off => 'generation-off',
        default => null,
    };
@endphp

@if ($sentence !== null)
    <span data-test="{{ $mark }}">
        {{ $sentence }}

        @if ($status === \App\Enums\GenerationStatus::Failed && $book->generation_failure !== null)
            Lo último que contestó el proveedor: «{{ $book->generation_failure }}».
        @endif
    </span>
@endif
