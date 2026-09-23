<?php

use App\Captura\RegistrarCandidato;
use App\Enums\Situacion;
use App\Fuentes\Candidato;
use App\Fuentes\ResolvedorDeFuentes;
use App\Models\Libro;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * El Alta por título: la vía para lo que no se puede escanear —los Libros Por
 * comprar, que no están en la mano, y los viejos, que no traen código de
 * barras.
 *
 * Es una sola pantalla para los dos casos: el flujo es el mismo y lo único que
 * cambia es la Situación con la que nace el Libro (decisión 14).
 *
 * Como un Libro es una edición (ADR-005), la Fuente devuelve varias del mismo
 * título y cada Candidato se muestra con Portada, autores, editorial y año:
 * sin eso se elige a ciegas.
 */
new #[Title('Alta por título')] class extends Component
{
    public string $titulo = '';

    public string $autor = '';

    public string $situacion = '';

    /**
     * Las ediciones que devolvió la Fuente, en datos planos.
     *
     * La pantalla las recuerda entre una petición y la siguiente porque volver
     * a preguntarle a la Fuente al momento de elegir contestaría otra cosa —o
     * no contestaría—, y lo que se registraría no sería la edición que se vio
     * en la lista.
     *
     * @var list<array<string, mixed>>
     */
    public array $candidatos = [];

    /** El título con el que se consultó, al que corresponde la lista. */
    public string $consultado = '';

    public ?string $aviso = null;

    /** La Fuente contestó y no tiene ninguna edición: queda la Captura manual. */
    public bool $sinEdiciones = false;

    /** El Libro que se acaba de registrar. */
    public ?int $registrado = null;

    /** El Libro que ya tenía el ISBN del Candidato que se eligió. */
    public ?int $duplicado = null;

    public function mount(): void
    {
        // Por comprar por omisión: ésta es sobre todo la vía de los Libros que
        // todavía no están.
        $this->situacion = Situacion::PorComprar->value;
    }

    #[Computed]
    public function libro(): ?Libro
    {
        return $this->registrado === null ? null : Libro::find($this->registrado);
    }

    #[Computed]
    public function elQueYaEstaba(): ?Libro
    {
        return $this->duplicado === null ? null : Libro::find($this->duplicado);
    }

    /**
     * Si hay a dónde mandar a ver el Libro que ya estaba. La ficha del Libro
     * llega con BIB-14; mientras no exista, el aviso dice cuál es y ya.
     */
    #[Computed]
    public function hayFichaDelLibro(): bool
    {
        return Route::has('libro');
    }

    public function consultar(ResolvedorDeFuentes $fuentes): void
    {
        $this->validate($this->reglas(), $this->avisos());

        $this->reset('candidatos', 'aviso', 'sinEdiciones', 'registrado', 'duplicado');
        $this->olvidarLosLibros();

        $titulo = trim($this->titulo);
        $autor = trim($this->autor) === '' ? null : trim($this->autor);

        $candidatos = $fuentes->porTitulo($titulo, $autor);

        $this->consultado = $titulo;

        if ($candidatos->hayCandidatos()) {
            $this->candidatos = array_map(
                fn (Candidato $candidato): array => $candidato->comoArreglo() + ['clave' => $candidato->clave()],
                $candidatos->candidatos,
            );

            return;
        }

        if ($candidatos->noConoceElTitulo()) {
            $this->sinEdiciones = true;
            $this->aviso = 'Ninguna Fuente tiene ediciones de «'.$titulo.'».';

            return;
        }

        // Que la Fuente no haya podido contestar no manda a Captura manual:
        // escribiría a mano un Libro que el catálogo sí tiene (ADR-002).
        $this->aviso = 'Las Fuentes no pudieron contestar por «'.$titulo.'». Vuelve a intentarlo.';
    }

    public function elegir(string $clave, RegistrarCandidato $registro): void
    {
        $renglon = $this->renglonDe($clave);

        if ($renglon === null) {
            return;
        }

        $resultado = $registro->registrar(
            Candidato::desdeArreglo($renglon),
            Situacion::tryFrom($this->situacion) ?? Situacion::PorComprar,
        );

        $this->olvidarLosLibros();

        if ($resultado->yaEstaba) {
            $this->duplicado = $resultado->libro->getKey();
            $this->aviso = '«'.$resultado->libro->titulo.'» ya está registrado con ese ISBN.';

            return;
        }

        $this->duplicado = null;
        $this->registrado = $resultado->libro->getKey();
        $this->aviso = '«'.$resultado->libro->titulo.'» quedó registrado. Situación: '
            .$resultado->libro->situacion->etiqueta().'.';
    }

    /** Deja la pantalla lista para el Libro siguiente. */
    public function otro(): void
    {
        $this->reset('titulo', 'autor', 'candidatos', 'consultado', 'aviso', 'sinEdiciones', 'registrado', 'duplicado');
        $this->resetValidation();
        $this->olvidarLosLibros();

        $this->situacion = Situacion::PorComprar->value;
    }

    /**
     * El renglón que se eligió. La clave la da el catálogo, así que dos
     * renglones sólo la comparten cuando son la misma edición con los mismos
     * datos — y ahí da igual cuál de los dos se registre.
     *
     * @return array<string, mixed>|null
     */
    private function renglonDe(string $clave): ?array
    {
        foreach ($this->candidatos as $renglon) {
            if (($renglon['clave'] ?? null) === $clave) {
                return $renglon;
            }
        }

        return null;
    }

    private function olvidarLosLibros(): void
    {
        unset($this->libro, $this->elQueYaEstaba);
    }

    /** @return array<string, array<int, mixed>> */
    private function reglas(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'autor' => ['nullable', 'string', 'max:255'],
            'situacion' => ['required', Rule::enum(Situacion::class)],
        ];
    }

    /** @return array<string, string> */
    private function avisos(): array
    {
        return [
            'titulo.required' => 'Falta el título.',
            'titulo.max' => 'El título no puede pasar de 255 caracteres.',
            'autor.max' => 'El nombre del Autor no puede pasar de 255 caracteres.',
            'situacion.required' => 'Falta la Situación.',
            'situacion.Illuminate\Validation\Rules\Enum' => 'La Situación es Acervo o Por comprar.',
        ];
    }
}; ?>

<div class="flex w-full max-w-3xl flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">Alta por título</flux:heading>
        <flux:text>Escribe el título, elige entre las ediciones que trae la Fuente y el Libro queda registrado.</flux:text>
    </div>

    @if ($this->libro)
        <div class="flex flex-col gap-6" data-test="registrado">
            <flux:text class="text-base" data-test="aviso">{{ $aviso }}</flux:text>

            <div class="flex gap-3">
                <flux:button wire:click="otro" variant="primary">Dar de alta otro Libro</flux:button>

                <flux:button :href="route('captura')" variant="subtle" wire:navigate>
                    Ir a la Captura
                </flux:button>
            </div>
        </div>
    @else
        <form wire:submit="consultar" class="flex flex-col gap-6">
            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input
                    wire:model="titulo"
                    label="Título"
                    autocomplete="off"
                    autofocus
                    data-test="campo-titulo"
                />

                <flux:input
                    wire:model="autor"
                    label="Autor"
                    description="Opcional. Acota las ediciones que devuelve la Fuente."
                    autocomplete="off"
                    data-test="campo-autor"
                />
            </div>

            <flux:radio.group wire:model="situacion" label="Situación" variant="segmented">
                <flux:radio value="{{ \App\Enums\Situacion::PorComprar->value }}" label="Por comprar" />
                <flux:radio value="{{ \App\Enums\Situacion::Acervo->value }}" label="Acervo" />
            </flux:radio.group>

            <flux:button type="submit" variant="primary" class="self-start">Consultar la Fuente</flux:button>
        </form>

        @if ($aviso)
            <flux:text class="text-base" data-test="aviso">{{ $aviso }}</flux:text>
        @endif

        @if ($this->elQueYaEstaba && $this->hayFichaDelLibro)
            <flux:button
                variant="subtle"
                class="self-start"
                :href="route('libro', $this->elQueYaEstaba)"
                wire:navigate
                data-test="ir-al-libro"
            >
                Ir a «{{ $this->elQueYaEstaba->titulo }}»
            </flux:button>
        @endif

        @if ($sinEdiciones)
            <flux:button
                variant="subtle"
                class="self-start"
                :href="route('captura-manual', array_filter(['titulo' => $consultado, 'autor' => trim($autor)]))"
                wire:navigate
                data-test="ir-a-captura-manual"
            >
                Capturar a mano «{{ $consultado }}»
            </flux:button>
        @endif

        @if ($candidatos)
            <div class="flex flex-col gap-3">
                <flux:heading size="lg">Ediciones de «{{ $consultado }}»</flux:heading>

                <flux:text>Cada renglón es una edición distinta del mismo título.</flux:text>

                @foreach ($candidatos as $edicion)
                    <div
                        wire:key="{{ $edicion['clave'] }}"
                        class="flex items-start gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700"
                        data-test="candidato"
                    >
                        <div class="w-16 shrink-0">
                            <x-portada-de-candidato
                                :url="$edicion['portadaUrl']"
                                :titulo="$edicion['titulo']"
                                :autores="$edicion['autores']"
                            />
                        </div>

                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <flux:heading class="truncate">{{ $edicion['titulo'] }}</flux:heading>

                            @if (filled($edicion['autores']))
                                <flux:text class="truncate">{{ implode(', ', $edicion['autores']) }}</flux:text>
                            @endif

                            <flux:text class="text-sm">
                                {{ collect([
                                    $edicion['editorial'],
                                    $edicion['anio'],
                                    $edicion['edicion'],
                                ])->filter()->implode(' · ') ?: 'Sin editorial ni año' }}
                            </flux:text>

                            <flux:text class="text-sm">
                                {{ $edicion['isbn'] ? 'ISBN '.$edicion['isbn'] : 'Esta edición no trae ISBN' }}
                            </flux:text>
                        </div>

                        <flux:button
                            size="sm"
                            variant="primary"
                            class="shrink-0"
                            wire:click="elegir(@js($edicion['clave']))"
                        >
                            Es ésta
                        </flux:button>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
