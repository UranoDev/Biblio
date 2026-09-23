<?php

use App\Captura\AltaDeLibro;
use App\Captura\LibroCapturado;
use App\Dominio\Isbn;
use App\Enums\Situacion;
use App\Models\Libro;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * La Captura manual: registrar un Libro escribiendo sus datos, porque ninguna
 * Fuente lo conoce (ADR-002).
 *
 * Se llega desde el Escaneo con el ISBN ya puesto y desde el Alta por título
 * con el título ya puesto, de modo que no haya que volver a escribir lo que ya
 * se escribió.
 */
new #[Title('Captura manual')] class extends Component
{
    public string $titulo = '';

    /**
     * Los Autores en el orden en que se acreditan. El renglón en blanco es el
     * que se está por llenar; al guardar, los que queden vacíos se descartan.
     *
     * @var list<string>
     */
    public array $autores = [''];

    public string $editorial = '';

    public string $anio = '';

    public string $edicion = '';

    public string $isbn = '';

    public string $situacion = '';

    /** El Libro recién registrado, al que todavía se le puede subir la Portada. */
    public ?int $registrado = null;

    public function mount(): void
    {
        // Lo que ya se escribió en la vía anterior: el ISBN que se escaneó, o
        // el título que se buscó en la Fuente.
        $this->isbn = trim((string) request()->query('isbn', ''));
        $this->titulo = trim((string) request()->query('titulo', ''));

        $this->situacion = Situacion::Acervo->value;
    }

    #[Computed]
    public function libro(): ?Libro
    {
        return $this->registrado === null ? null : Libro::find($this->registrado);
    }

    public function agregarAutor(): void
    {
        $this->autores[] = '';
    }

    public function quitarAutor(int $posicion): void
    {
        unset($this->autores[$posicion]);

        // Reindexar es lo que conserva el orden de los que quedan.
        $this->autores = array_values($this->autores);

        if ($this->autores === []) {
            $this->autores = [''];
        }
    }

    public function registrar(AltaDeLibro $alta): void
    {
        $this->validate($this->reglas(), $this->avisos());

        $libro = $alta->aMano(new LibroCapturado(
            titulo: trim($this->titulo),
            autores: $this->autoresEscritos(),
            editorial: $this->enBlancoEsNulo($this->editorial),
            anio: trim($this->anio) === '' ? null : (int) trim($this->anio),
            edicion: $this->enBlancoEsNulo($this->edicion),
            isbn: trim($this->isbn) === '' ? null : Isbn::desde($this->isbn),
            situacion: Situacion::from($this->situacion),
        ));

        $this->registrado = $libro->getKey();

        unset($this->libro);
    }

    /** Deja la pantalla lista para el Libro siguiente. */
    public function otro(): void
    {
        $this->reset('titulo', 'autores', 'editorial', 'anio', 'edicion', 'isbn', 'registrado');
        $this->resetValidation();

        $this->situacion = Situacion::Acervo->value;

        unset($this->libro);
    }

    /** @return array<string, array<int, mixed>> */
    private function reglas(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:255'],
            'autores' => ['array'],
            'autores.*' => ['nullable', 'string', 'max:255'],
            'editorial' => ['nullable', 'string', 'max:255'],
            'anio' => ['nullable', 'integer', 'min:1450', 'max:'.((int) date('Y') + 1)],
            'edicion' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', $this->isbnQueNoEsteRegistrado()],
            'situacion' => ['required', Rule::enum(Situacion::class)],
        ];
    }

    /** @return array<string, string> */
    private function avisos(): array
    {
        return [
            'titulo.required' => 'Falta el título.',
            'titulo.max' => 'El título no puede pasar de 255 caracteres.',
            'autores.*.max' => 'El nombre del Autor no puede pasar de 255 caracteres.',
            'editorial.max' => 'La editorial no puede pasar de 255 caracteres.',
            'anio.integer' => 'El año se escribe con dígitos.',
            'anio.min' => 'El año tiene que ser de 1450 en adelante.',
            'anio.max' => 'El año no puede ser posterior al que viene.',
            'edicion.max' => 'La edición no puede pasar de 255 caracteres.',
            'situacion.required' => 'Falta la Situación.',
            'situacion.Illuminate\Validation\Rules\Enum' => 'La Situación es Acervo o Por comprar.',
        ];
    }

    /**
     * Un ISBN ya registrado avisa cuál es el Libro que lo tiene, en vez de
     * duplicarlo: el ISBN es único en el acervo (ADR-005).
     */
    private function isbnQueNoEsteRegistrado(): \Closure
    {
        return function (string $atributo, mixed $valor, \Closure $falla): void {
            $tecleado = trim((string) $valor);

            if ($tecleado === '') {
                return;
            }

            if (! Isbn::esValido($tecleado)) {
                $falla('«'.$tecleado.'» no es un ISBN.');

                return;
            }

            $registrado = Libro::query()
                ->where('isbn', Isbn::desde($tecleado)->valor())
                ->first();

            if ($registrado instanceof Libro) {
                $falla('«'.$registrado->titulo.'» ya está registrado con ese ISBN.');
            }
        };
    }

    /** @return list<string> */
    private function autoresEscritos(): array
    {
        $escritos = array_map(trim(...), $this->autores);

        return array_values(array_filter($escritos, fn (string $autor): bool => $autor !== ''));
    }

    private function enBlancoEsNulo(string $valor): ?string
    {
        return trim($valor) === '' ? null : trim($valor);
    }
}; ?>

<div class="flex w-full max-w-2xl flex-col gap-8">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">Captura manual</flux:heading>
        <flux:text>Escribe los datos del Libro que ninguna Fuente conoce. Sólo el título hace falta.</flux:text>
    </div>

    @if ($this->libro)
        <div class="flex flex-col gap-6" data-test="registrado">
            <flux:text class="text-base">«{{ $this->libro->titulo }}» quedó registrado. Situación: {{ $this->libro->situacion->etiqueta() }}.</flux:text>

            <livewire:portada-de-libro :libro="$this->libro" :key="'portada-'.$this->libro->getKey()" />

            <div class="flex gap-3">
                <flux:button wire:click="otro" variant="primary">Capturar otro Libro</flux:button>

                <flux:button :href="route('captura')" variant="subtle" wire:navigate>
                    Ir a la Captura
                </flux:button>
            </div>
        </div>
    @else
        <form wire:submit="registrar" class="flex flex-col gap-6">
            <flux:input wire:model="titulo" label="Título" autocomplete="off" data-test="campo-titulo" />

            <flux:field>
                <flux:label>Autores</flux:label>
                <flux:description>En el orden en que vienen acreditados.</flux:description>

                <div class="flex flex-col gap-2">
                    @foreach ($autores as $indice => $autor)
                        <div wire:key="autor-{{ $indice }}" class="flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <flux:input
                                    wire:model="autores.{{ $indice }}"
                                    autocomplete="off"
                                    placeholder="Nombre del Autor"
                                    class="flex-1"
                                />

                                @if (count($autores) > 1)
                                    <flux:button
                                        type="button"
                                        icon="x-mark"
                                        variant="subtle"
                                        wire:click="quitarAutor({{ $indice }})"
                                        aria-label="Quitar este Autor"
                                    />
                                @endif
                            </div>

                            <flux:error name="autores.{{ $indice }}" />
                        </div>
                    @endforeach

                    <flux:button type="button" variant="subtle" class="self-start" wire:click="agregarAutor">
                        Agregar otro Autor
                    </flux:button>
                </div>
            </flux:field>

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:input wire:model="editorial" label="Editorial" autocomplete="off" />
                <flux:input wire:model="anio" label="Año" inputmode="numeric" autocomplete="off" />
                <flux:input wire:model="edicion" label="Edición" autocomplete="off" />
                <flux:input
                    wire:model="isbn"
                    label="ISBN"
                    description="Si no trae código de barras, déjalo en blanco."
                    inputmode="numeric"
                    autocomplete="off"
                    data-test="campo-isbn"
                />
            </div>

            <flux:radio.group wire:model="situacion" label="Situación" variant="segmented">
                <flux:radio value="{{ \App\Enums\Situacion::Acervo->value }}" label="Acervo" />
                <flux:radio value="{{ \App\Enums\Situacion::PorComprar->value }}" label="Por comprar" />
            </flux:radio.group>

            <flux:text>La Portada se sube en cuanto el Libro quede registrado, y se puede dejar sin ella.</flux:text>

            <flux:button type="submit" variant="primary" class="self-start">Registrar el Libro</flux:button>
        </form>
    @endif
</div>
