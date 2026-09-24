{{--
    Where the Book stands with the AI, on the screens that show many of them
    (BIB-22).

    Waiting its turn and having been given up on look alike from the outside
    —neither has anything new to show— and are not the same thing, so each one
    carries its own mark and a Book that is up to date carries none.

    Both marks come from the same reading of the Book that its sheet uses
    (BIB-46), so a Book cannot be «en la cola» on the grid and failed on its
    sheet. A mark of the queue nobody ever cleared counts as a failure here too:
    the job behind it is gone.
--}}

@props(['book'])

@php($status = $book->generationAttempt())

@if ($status === \App\Enums\GenerationStatus::Pending)
    <flux:badge size="sm" icon="arrow-path" data-test="generation-queued-{{ $book->getKey() }}">
        En la cola
    </flux:badge>
@elseif ($status === \App\Enums\GenerationStatus::Failed)
    <flux:badge size="sm" color="red" data-test="generation-failed-{{ $book->getKey() }}">
        La IA falló
    </flux:badge>
@endif
