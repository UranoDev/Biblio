@props(['counter'])

{{--
    One number of the Dashboard. The whole tile is the link: the number is the
    beginning of a task, so there is nothing on it that does not open the list
    it counted (BIB-40).

    At zero the tile stops shouting -- the number goes quiet, a check appears
    next to it and the sentence underneath says what is finished. Only the
    counters of pending work carry that sentence.
--}}
<a
    href="{{ $counter->url }}"
    wire:navigate
    @class([
        'group flex flex-col gap-1 rounded-lg border p-4 transition-colors',
        'border-zinc-200 hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500',
    ])
    data-test="counter-{{ $counter->key }}"
>
    <span class="flex items-center gap-2">
        <span
            @class([
                'font-mono text-3xl leading-none',
                'text-zinc-400 dark:text-zinc-500' => $counter->isDone(),
            ])
            data-test="total-{{ $counter->key }}"
        >
            {{ $counter->total }}
        </span>

        @if ($counter->isDone())
            <flux:icon.check variant="micro" class="text-zinc-400 dark:text-zinc-500" />
        @endif
    </span>

    <span class="text-sm group-hover:underline">{{ $counter->label }}</span>

    @if ($counter->isDone())
        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $counter->done }}</span>
    @endif
</a>
