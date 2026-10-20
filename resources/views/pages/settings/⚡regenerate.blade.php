<?php

use App\Ai\AiModel;
use App\Ai\RegenerationPlan;
use App\Ai\Regenerations;
use App\Enums\RegenerationTarget;
use App\Models\Regeneration;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The Settings screen that asks the AI again for what it wrote: the Categories
 * and the Descriptions of the Books already registered (BIB-21).
 *
 * It is the screen for after changing the AiModel. The run goes through the
 * queue (ADR-006), so nothing here waits for an answer: what the screen shows
 * from then on is how far the batch has got, which it reads from the queue and
 * not from the browser — reloading the page does not lose it.
 *
 * The Description of the Source and mine are never reached (ADR-007), and how
 * many stay untouched is said before launching.
 */
new #[Title('Regenerar')] class extends Component
{
    /** What to ask for again: categories, descriptions, or both. */
    public string $target = 'both';

    /** Whether the run is narrowed to the Books left on another AiModel. */
    public bool $onlyPrevious = true;

    /** What the last launch did, read at a glance and never in a modal. */
    public ?string $notice = null;

    #[Computed]
    public function chosen(): ?AiModel
    {
        return AiModel::chosen();
    }

    /** The target as it was picked, or both when the value is not a known one. */
    #[Computed]
    public function pickedTarget(): RegenerationTarget
    {
        return RegenerationTarget::tryFrom($this->target) ?? RegenerationTarget::Both;
    }

    /**
     * The options of the first question.
     *
     * @return list<RegenerationTarget>
     */
    #[Computed]
    public function targets(): array
    {
        return RegenerationTarget::cases();
    }

    /** How many Books the run would reach, and how many Descriptions it keeps. */
    #[Computed]
    public function plan(): RegenerationPlan
    {
        return app(Regenerations::class)->plan($this->pickedTarget, $this->onlyPrevious);
    }

    /** The last run launched, which is where the progress is read from. */
    #[Computed]
    public function regeneration(): ?Regeneration
    {
        return Regeneration::last();
    }

    /** How many Books the queue gave up on, whichever run they came from. */
    #[Computed]
    public function failed(): int
    {
        return app(Regenerations::class)->failed();
    }

    public function updatedTarget(): void
    {
        $this->forget();
    }

    public function updatedOnlyPrevious(): void
    {
        $this->forget();
    }

    /** Puts the Books in the queue and leaves the screen watching the batch. */
    public function start(): void
    {
        $this->validate([
            'target' => ['required', Rule::in(array_column(RegenerationTarget::cases(), 'value'))],
        ]);

        $regeneration = app(Regenerations::class)->start($this->pickedTarget, $this->onlyPrevious);

        $this->forget();

        if (! $regeneration instanceof Regeneration) {
            $this->notice = 'No hay modelo elegido, así que no hay a quién pedirle nada.';

            return;
        }

        $this->notice = match (true) {
            $regeneration->total === 0 => 'No hay libros que regenerar con esa selección.',
            $regeneration->total === 1 => 'Un libro está en la cola.',
            default => $regeneration->total.' libros están en la cola.',
        };
    }

    /** Throws the run at the Books the queue gave up on, and at those alone. */
    public function retryFailed(): void
    {
        $regeneration = app(Regenerations::class)->retryFailed($this->pickedTarget);

        $this->forget();

        if (! $regeneration instanceof Regeneration) {
            $this->notice = 'No hay modelo elegido, así que no hay a quién pedirle nada.';

            return;
        }

        $this->notice = match (true) {
            $regeneration->total === 0 => 'Ningún libro quedó pendiente de reintentar.',
            $regeneration->total === 1 => 'Un libro volvió a la cola.',
            default => $regeneration->total.' libros volvieron a la cola.',
        };
    }

    /** The counts are read again after anything that can move them. */
    private function forget(): void
    {
        unset($this->plan, $this->regeneration, $this->failed, $this->pickedTarget);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout
        heading="Regenerar"
        subheading="Volver a pedirle a la inteligencia artificial las categorías y las descripciones de los libros ya registrados."
    >
        <div class="flex flex-col gap-8">
            @if ($this->chosen === null)
                <flux:callout variant="warning" icon="sparkles" data-test="generation-off">
                    <flux:callout.heading>Todavía no hay modelo elegido</flux:callout.heading>

                    <flux:callout.text>
                        La generación está apagada: no hay a quién pedirle las categorías ni las
                        descripciones. Elige un modelo para poder regenerar.
                    </flux:callout.text>
                </flux:callout>

                <flux:button :href="route('ai-model.edit')" variant="outline" class="self-start" wire:navigate>
                    Elegir el modelo
                </flux:button>
            @else
                @if ($this->regeneration && $this->regeneration->isRunning())
                    <div class="flex flex-col gap-2" data-test="running" wire:poll.5s>
                        <flux:heading size="lg">En la cola</flux:heading>

                        <flux:text>
                            Se están pidiendo {{ $this->regeneration->target->running() }} de
                            {{ $this->regeneration->total }}
                            {{ $this->regeneration->total === 1 ? 'libro' : 'libros' }}.
                        </flux:text>

                        <flux:text data-test="progress">
                            Van {{ $this->regeneration->done() }} de {{ $this->regeneration->total }};
                            faltan {{ $this->regeneration->remaining() }}.
                        </flux:text>

                        <flux:text class="text-sm">La cola sigue aunque cierres esta pantalla.</flux:text>
                    </div>
                @elseif ($this->regeneration && $this->regeneration->total > 0)
                    <div class="flex flex-col gap-2" data-test="finished">
                        <flux:heading size="lg">La última corrida terminó</flux:heading>

                        <flux:text>
                            Se pidieron {{ $this->regeneration->target->running() }} de
                            {{ $this->regeneration->total }}
                            {{ $this->regeneration->total === 1 ? 'libro' : 'libros' }}.
                        </flux:text>
                    </div>
                @endif

                @if ($notice)
                    <flux:text class="text-base" data-test="notice">{{ $notice }}</flux:text>
                @endif

                <form wire:submit="start" class="flex flex-col gap-6">
                    <flux:radio.group wire:model.live="target" label="Qué regenerar">
                        @foreach ($this->targets as $option)
                            <flux:radio
                                wire:key="target-{{ $option->value }}"
                                value="{{ $option->value }}"
                                label="{{ $option->label() }}"
                                data-test="target-{{ $option->value }}"
                            />
                        @endforeach
                    </flux:radio.group>

                    <flux:checkbox
                        wire:model.live="onlyPrevious"
                        label="Sólo los libros que quedaron con otro modelo"
                        description="Sin esto se le vuelve a pedir a la IA lo de todos los libros, y cada uno se cobra otra vez."
                        data-test="only-previous"
                    />

                    <div class="flex flex-col gap-1" data-test="plan">
                        <flux:text data-test="plan-books">
                            {{ $this->plan->books === 1
                                ? 'Se le va a pedir a la IA lo de un libro.'
                                : 'Se le va a pedir a la IA lo de '.$this->plan->books.' libros.' }}
                        </flux:text>

                        @if ($this->plan->translated)
                            <flux:text data-test="plan-translated">
                                {{ $this->plan->translated === 1
                                    ? 'De ellos, una descripción no se va a escribir de nuevo: viene de la fuente en otro idioma y se va a traducir al español.'
                                    : 'De ellos, '.$this->plan->translated.' descripciones no se van a escribir de nuevo: vienen de la fuente en otro idioma y se van a traducir al español.' }}
                            </flux:text>
                        @endif

                        @if ($this->plan->kept)
                            <flux:text data-test="plan-kept">
                                {{ $this->plan->kept === 1
                                    ? 'Una descripción se queda como está: no la escribió la IA, sino la fuente o yo.'
                                    : $this->plan->kept.' descripciones se quedan como están: no las escribió la IA, sino la fuente o yo.' }}
                            </flux:text>
                        @endif
                    </div>

                    <flux:button
                        type="submit"
                        variant="primary"
                        class="self-start"
                        :disabled="! $this->plan->reachesAnything()"
                        data-test="start"
                    >
                        Lanzar la regeneración
                    </flux:button>
                </form>

                @if ($this->failed)
                    <div class="flex flex-col gap-2" data-test="failed">
                        <flux:heading>La cola se rindió con algunos</flux:heading>

                        <flux:text>
                            {{ $this->failed === 1
                                ? 'Un libro quedó marcado en su ficha, con lo que dijo el proveedor.'
                                : $this->failed.' libros quedaron marcados en su ficha, con lo que dijo el proveedor.' }}
                        </flux:text>

                        <flux:button
                            wire:click="retryFailed"
                            variant="outline"
                            class="self-start"
                            data-test="retry-failed"
                        >
                            Reintentar sólo esos
                        </flux:button>
                    </div>
                @endif
            @endif
        </div>
    </x-pages::settings.layout>
</section>
