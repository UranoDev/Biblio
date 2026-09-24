<?php

use App\Ai\AiModel;
use App\Dashboard\Workload;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The first screen of the back office: where the pending work is (BIB-40).
 *
 * Two hundred books do not get captured evenly, and none of what is left
 * half-done can be seen from the grid: a Cover still generated, a Book waiting
 * on the queue, a generation that failed, a job the worker gave up on. Each of
 * those is a number here and each number opens the list it counted.
 *
 * Nothing is counted in this file: the whole screen is three queries and they
 * live in `App\Dashboard\Workload`, next to the address each number opens.
 */
new #[Title('Inicio')] class extends Component
{
    #[Computed]
    public function workload(): Workload
    {
        return Workload::read();
    }

    /**
     * Whether there is an AiModel to generate with at all.
     *
     * Without one, the two counters of the AI sit at zero and would read as
     * finished work, when what is true is that nothing is ever going to be
     * asked for. The screen says so instead of letting the zero speak.
     */
    #[Computed]
    public function generating(): bool
    {
        return AiModel::chosen() instanceof AiModel;
    }
}; ?>

<div class="flex w-full max-w-4xl flex-col gap-8">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">Inicio</flux:heading>

        <flux:text>
            Qué falta hacer en el acervo. Cada número abre la lista que cuenta.
        </flux:text>
    </div>

    @if ($this->workload->empty())
        {{--
            With not one Book registered there is nothing finished to announce:
            every counter would sit at zero and every one of them would read as
            work done. Until then the screen says where to start.
        --}}
        <div class="flex flex-col gap-3" data-test="empty">
            <flux:text>Todavía no has registrado ningún Libro.</flux:text>

            <div class="flex flex-wrap gap-3">
                <flux:button :href="route('scan')" wire:navigate icon="plus" data-test="start-scan">
                    Escanear uno
                </flux:button>

                <flux:button variant="subtle" :href="route('title-lookup')" wire:navigate data-test="start-title-lookup">
                    Darlo de alta por título
                </flux:button>
            </div>
        </div>
    @else
        <section class="flex flex-col gap-4">
            <flux:heading size="lg">Lo que falta</flux:heading>

            @unless ($this->generating)
                <flux:callout variant="warning" icon="sparkles" data-test="generation-off">
                    <flux:callout.heading>Todavía no hay modelo elegido</flux:callout.heading>

                    <flux:callout.text>
                        Mientras no elijas uno, ningún Libro va a recibir categorías ni
                        descripción de la IA.
                    </flux:callout.text>

                    <flux:callout.link :href="route('ai-model.edit')" wire:navigate>
                        Elegir el modelo de IA
                    </flux:callout.link>
                </flux:callout>
            @endunless

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->workload->pending() as $counter)
                    <x-counter :counter="$counter" />
                @endforeach
            </div>
        </section>

        <flux:separator />

        <section class="flex flex-col gap-4">
            <flux:heading size="lg">Lo que hay</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->workload->library() as $counter)
                    <x-counter :counter="$counter" />
                @endforeach
            </div>
        </section>
    @endif
</div>
