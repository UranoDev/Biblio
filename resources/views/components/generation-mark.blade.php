{{--
    Where the Book stands with the AI, on the screens that show many of them
    (BIB-22).

    Waiting its turn and having been given up on look alike from the outside
    —neither has anything new to show— and are not the same thing, so each one
    carries its own mark and a Book that is up to date carries none.
--}}

@props(['book'])

@if ($book->isGenerationQueued())
    <flux:badge size="sm" icon="arrow-path" data-test="generation-queued-{{ $book->getKey() }}">
        En la cola
    </flux:badge>
@elseif ($book->generation_failed_at !== null)
    <flux:badge size="sm" color="red" data-test="generation-failed-{{ $book->getKey() }}">
        La IA falló
    </flux:badge>
@endif
