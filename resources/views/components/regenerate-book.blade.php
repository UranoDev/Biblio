{{--
    The button that asks the AI again for what it wrote about one Book (BIB-22).

    It is the same button on the grid of the Shelf and on the sheet of the Book,
    so the three things that can be asked for are worded once. The work is the
    queue's (ADR-006): pressing it queues a request and nothing else.

    Without a chosen AiModel the button stays where it is and says that
    generation is off, with the way to the screen that turns it on. Hiding it
    would leave the same screen looking like a Book that simply cannot be
    regenerated.
--}}

@props(['book', 'chosen' => null, 'size' => 'xs'])

<flux:dropdown position="bottom" align="start">
    <flux:button
        :size="$size"
        variant="outline"
        icon:trailing="chevron-down"
        data-test="regenerate-{{ $book->getKey() }}"
    >
        Regenerar
    </flux:button>

    <flux:menu>
        @if ($chosen)
            <flux:menu.heading>Volver a pedirle a {{ $chosen->name }}</flux:menu.heading>

            @foreach (\App\Enums\RegenerationTarget::cases() as $option)
                <flux:menu.item
                    wire:key="regenerate-{{ $book->getKey() }}-{{ $option->value }}"
                    wire:click="regenerate({{ $book->getKey() }}, '{{ $option->value }}')"
                    data-test="regenerate-{{ $book->getKey() }}-{{ $option->value }}"
                >
                    {{ $option->forOneBook() }}
                </flux:menu.item>
            @endforeach
        @else
            <div
                class="max-w-64 px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400"
                data-test="regenerate-{{ $book->getKey() }}-off"
            >
                La generación está apagada: todavía no hay modelo elegido.
            </div>

            <flux:menu.separator />

            <flux:menu.item :href="route('ai-model.edit')" wire:navigate icon="sparkles">
                Elegir el modelo
            </flux:menu.item>
        @endif
    </flux:menu>
</flux:dropdown>
