<?php

use App\Ai\AiModel;
use App\Ai\Provider;
use App\Ai\Providers;
use App\Ai\SuggestedAiModel;
use App\Models\AiSetting;
use App\Models\Book;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The Settings screen where the AiModel that writes the Categories and the
 * Descriptions is chosen (ADR-006).
 *
 * The choice is stored in `ai_settings`, not in the environment, so trying one
 * AiModel and comparing it with another is an ordinary operation and not a
 * deploy. The API keys go the other way around: they stay in the environment,
 * and this screen only reads *which* Providers have one — never the value, which
 * it neither shows nor lets anybody type.
 */
new #[Title('Modelo de IA')] class extends Component
{
    /** The option for a model the Provider does not name, typed in by hand. */
    public const OTHER = '__other__';

    public string $provider = '';

    public string $model = '';

    public string $otherModel = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $chosen = AiModel::chosen();

        if (! $chosen instanceof AiModel) {
            return;
        }

        $this->provider = $chosen->provider;

        $this->model = in_array($chosen->name, $this->namedModels(), true)
            ? $chosen->name
            : self::OTHER;

        if ($this->model === self::OTHER) {
            $this->otherModel = $chosen->name;
        }
    }

    /**
     * The Providers that can be chosen.
     *
     * @return list<Provider>
     */
    #[Computed]
    public function providers(): array
    {
        return Providers::withKey();
    }

    /**
     * The Providers waiting for their key, to say which variable each one reads.
     *
     * @return list<Provider>
     */
    #[Computed]
    public function missing(): array
    {
        return Providers::withoutKey();
    }

    /**
     * The AiModels the selected Provider names.
     *
     * @return list<SuggestedAiModel>
     */
    #[Computed]
    public function models(): array
    {
        return Providers::find($this->provider)?->models ?? [];
    }

    #[Computed]
    public function chosen(): ?AiModel
    {
        return AiModel::chosen();
    }

    #[Computed]
    public function chosenProvider(): ?Provider
    {
        return $this->chosen === null ? null : Providers::find($this->chosen->provider);
    }

    /** The value of the option for typing the name of a model in by hand. */
    #[Computed]
    public function other(): string
    {
        return self::OTHER;
    }

    /** How many Books got their Categories from another AiModel. */
    #[Computed]
    public function categoriesFromBefore(): int
    {
        return $this->chosen === null
            ? 0
            : Book::query()->categoriesFromAnotherAiModel($this->chosen)->count();
    }

    /** How many Books got their Description from another AiModel. */
    #[Computed]
    public function descriptionFromBefore(): int
    {
        return $this->chosen === null
            ? 0
            : Book::query()->descriptionFromAnotherAiModel($this->chosen)->count();
    }

    /**
     * Whether there is somewhere to send the person to regenerate. The mass
     * regeneration arrives with BIB-21; while it does not exist, the screen says
     * how many Books are on the previous AiModel and nothing else.
     */
    #[Computed]
    public function canRegenerate(): bool
    {
        return Route::has('regenerate');
    }

    /** Choosing another Provider leaves the model to be chosen again. */
    public function updatedProvider(): void
    {
        $this->reset('model', 'otherModel');
        $this->resetValidation();

        unset($this->models);
    }

    /**
     * Choosing a model clears the complaint that asked for one.
     *
     * Without this the screen contradicts itself: switching Provider empties the
     * model, saving then complains, and the complaint stays on screen after the
     * model is picked —- pointing at a radio that is visibly selected.
     */
    public function updatedModel(): void
    {
        $this->resetValidation();
    }

    /** Typing the name of a model clears the complaint that asked for it. */
    public function updatedOtherModel(): void
    {
        $this->resetValidation('otherModel');
    }

    public function save(): void
    {
        $this->validate($this->rules(), $this->messages());

        $name = $this->model === self::OTHER ? trim($this->otherModel) : $this->model;

        AiSetting::choose($this->provider, $name);

        unset($this->chosen, $this->chosenProvider, $this->categoriesFromBefore, $this->descriptionFromBefore);

        $this->notice = 'Las categorías y las descripciones las escribe '
            .Providers::labelOf($this->provider).' con '.$name.'.';
    }

    /**
     * The names the selected Provider gives, which are the options on the
     * screen.
     *
     * @return list<string>
     */
    private function namedModels(): array
    {
        return Providers::find($this->provider)?->modelNames() ?? [];
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'provider' => [
                'required',
                Rule::in(array_map(
                    static fn (Provider $provider): string => $provider->name,
                    Providers::withKey(),
                )),
            ],
            'model' => ['required', Rule::in([...$this->namedModels(), self::OTHER])],
            'otherModel' => [
                Rule::requiredIf($this->model === self::OTHER),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'provider.required' => 'Falta elegir el proveedor.',
            'provider.in' => 'Ese proveedor no tiene su llave en el entorno.',
            'model.required' => 'Falta elegir el modelo.',
            'model.in' => 'Elige uno de los modelos del proveedor, o escribe el nombre de otro.',
            'otherModel.required' => 'Falta el nombre del modelo.',
            'otherModel.max' => 'El nombre del modelo no puede pasar de 255 caracteres.',
        ];
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout
        heading="Modelo de IA"
        subheading="Qué inteligencia artificial escribe las categorías y las descripciones de los libros."
    >
        <div class="flex flex-col gap-8">
            <div class="flex flex-col gap-2" data-test="state">
                @if ($this->chosen)
                    <flux:heading size="lg">{{ \App\Ai\Providers::labelOf($this->chosen->provider) }}</flux:heading>

                    <flux:text>
                        Escribe las categorías y las descripciones con
                        <span class="font-medium">{{ $this->chosen->name }}</span>.
                    </flux:text>

                    @if ($this->chosenProvider === null || ! $this->chosenProvider->hasKey)
                        <flux:callout variant="warning" icon="key" data-test="chosen-without-key">
                            <flux:callout.text>
                                Falta su llave en el entorno{{ $this->chosenProvider?->keyVariable ? ' ('.$this->chosenProvider->keyVariable.')' : '' }}.
                                Hasta que esté puesta, no se generan categorías ni descripciones.
                            </flux:callout.text>
                        </flux:callout>
                    @endif
                @else
                    <flux:callout variant="warning" icon="sparkles" data-test="generation-off">
                        <flux:callout.heading>Todavía no hay modelo elegido</flux:callout.heading>

                        <flux:callout.text>
                            La generación está apagada: los libros que se registren quedan sin categorías
                            y sin descripción de la IA hasta que elijas un modelo aquí.
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </div>

            @if ($notice)
                <flux:text class="text-base" data-test="notice">{{ $notice }}</flux:text>
            @endif

            @if ($this->providers)
                <form wire:submit="save" class="flex flex-col gap-6">
                    <flux:radio.group wire:model.live="provider" label="Proveedor">
                        @foreach ($this->providers as $option)
                            <flux:radio
                                wire:key="provider-{{ $option->name }}"
                                value="{{ $option->name }}"
                                label="{{ $option->label }}"
                                data-test="provider-{{ $option->name }}"
                            />
                        @endforeach
                    </flux:radio.group>

                    @if ($provider)
                        <flux:radio.group wire:model.live="model" label="Modelo">
                            {{-- Each radio is keyed so that changing Provider builds new
                                 ones instead of rewriting these. A radio reads its value
                                 once, when the browser creates it, and never looks at that
                                 attribute again: without the key the list would show the
                                 names of the new Provider while still sending back the
                                 names of the previous one. --}}
                            @foreach ($this->models as $option)
                                <flux:radio
                                    wire:key="model-{{ $provider }}-{{ $option->name }}"
                                    value="{{ $option->name }}"
                                    label="{{ $option->name }}"
                                    description="{{ $option->rolesLabel() }}"
                                    data-test="model-{{ $option->name }}"
                                />
                            @endforeach

                            <flux:radio
                                wire:key="model-other"
                                value="{{ $this->other }}"
                                label="Otro"
                                description="Un modelo que el proveedor no nombra aquí."
                                data-test="model-other"
                            />
                        </flux:radio.group>

                        @if ($model === $this->other)
                            <flux:input
                                wire:model="otherModel"
                                label="Nombre del modelo"
                                description="Tal como lo escribe el proveedor."
                                autocomplete="off"
                                data-test="other-model-field"
                            />
                        @endif
                    @endif

                    <flux:button type="submit" variant="primary" class="self-start">
                        Guardar el modelo
                    </flux:button>
                </form>
            @else
                <flux:text data-test="without-providers">
                    Ningún proveedor tiene su llave en el entorno, así que todavía no hay modelo que elegir.
                </flux:text>
            @endif

            @if ($this->missing)
                <div class="flex flex-col gap-2" data-test="missing-keys">
                    <flux:heading>Proveedores sin llave</flux:heading>

                    <flux:text>
                        Cada uno lee su llave de una variable del entorno, y ahí es donde se pone:
                        no se captura en esta pantalla.
                    </flux:text>

                    <ul class="flex flex-col gap-1">
                        @foreach ($this->missing as $option)
                            <li>
                                <flux:text>
                                    {{ $option->label }} — falta
                                    <span class="font-mono">{{ $option->keyVariable ?? 'su llave' }}</span>
                                </flux:text>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($this->categoriesFromBefore || $this->descriptionFromBefore)
                <div class="flex flex-col gap-2" data-test="from-before">
                    <flux:heading>Escrito con otro modelo</flux:heading>

                    <flux:text>Cambiar de modelo no vuelve a escribir lo que ya está escrito.</flux:text>

                    @if ($this->categoriesFromBefore)
                        <flux:text data-test="categories-from-before">
                            {{ $this->categoriesFromBefore === 1
                                ? 'Un libro tiene sus categorías escritas con otro modelo.'
                                : $this->categoriesFromBefore.' libros tienen sus categorías escritas con otro modelo.' }}
                        </flux:text>
                    @endif

                    @if ($this->descriptionFromBefore)
                        <flux:text data-test="description-from-before">
                            {{ $this->descriptionFromBefore === 1
                                ? 'Un libro tiene su descripción escrita con otro modelo.'
                                : $this->descriptionFromBefore.' libros tienen su descripción escrita con otro modelo.' }}
                        </flux:text>
                    @endif

                    @if ($this->canRegenerate)
                        <flux:button
                            :href="route('regenerate')"
                            variant="subtle"
                            class="mt-2 self-start"
                            wire:navigate
                            data-test="go-to-regenerate"
                        >
                            Ir a regenerar
                        </flux:button>
                    @endif
                </div>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
