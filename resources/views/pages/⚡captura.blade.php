<?php

use App\Captura\RegistrarEscaneo;
use App\Captura\ResultadoDelEscaneo;
use App\Enums\DesenlaceDelEscaneo;
use App\Models\Libro;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * La captura por Escaneo: un campo de ISBN siempre enfocado y una ráfaga de
 * veinte libros sin un clic entre uno y otro (ADR-001).
 *
 * Nada de lo que pasa aquí abre un modal ni pide confirmación, ni siquiera
 * para avisar que un Libro ya estaba: un modal se lleva el foco, el Escáner
 * teclea al vacío y el libro no se registra sin que nada lo diga.
 */
new #[Title('Captura')] class extends Component
{
    public string $isbn = '';

    /** Cómo terminó el último Escaneo. Se lee de un vistazo y no interrumpe. */
    public ?string $aviso = null;

    /**
     * El ISBN que ninguna Fuente conoce. Se guarda para que la Captura manual
     * arranque con él y no haya que volver a teclearlo.
     */
    public ?string $isbnSinFuente = null;

    /**
     * Lo escaneado en esta ráfaga, lo más reciente arriba.
     *
     * @var list<array{clave: string, desenlace: string, etiqueta: string, color: string, titulo: string|null, isbn: string, libro: int|null, deshacible: bool}>
     */
    public array $rafaga = [];

    public function registrar(RegistrarEscaneo $escaneo): void
    {
        $tecleado = trim($this->isbn);

        // El campo se vacía siempre, incluso cuando el Escaneo no registró
        // nada: lo que queda tecleado se le pega al código siguiente.
        $this->reset('isbn', 'isbnSinFuente');

        if ($tecleado !== '') {
            $this->anotar($escaneo->registrar($tecleado));
        }

        $this->devolverElFoco();
    }

    /** Regresa a Por comprar el Libro que el reescaneo pasó al Acervo. */
    public function deshacer(string $clave): void
    {
        foreach ($this->rafaga as $posicion => $entrada) {
            if ($entrada['clave'] !== $clave || ! $entrada['deshacible']) {
                continue;
            }

            Libro::query()->find($entrada['libro'])?->regresaAPorComprar();

            $this->rafaga[$posicion]['deshacible'] = false;
            $this->rafaga[$posicion]['etiqueta'] = 'Regresó a Por comprar';
            $this->rafaga[$posicion]['color'] = 'zinc';

            $this->aviso = $this->entreComillas($entrada['titulo']).' vuelve a estar Por comprar.';
        }

        $this->devolverElFoco();
    }

    private function anotar(ResultadoDelEscaneo $resultado): void
    {
        $this->aviso = $this->avisoDe($resultado);

        if ($resultado->ofreceCapturaManual()) {
            $this->isbnSinFuente = $resultado->isbn?->valor();
        }

        array_unshift($this->rafaga, [
            'clave' => (string) Str::uuid(),
            'desenlace' => $resultado->desenlace->value,
            'etiqueta' => $resultado->desenlace->etiqueta(),
            'color' => $this->colorDe($resultado->desenlace),
            'titulo' => $resultado->libro?->titulo,
            'isbn' => $resultado->isbn?->valor() ?? $resultado->tecleado,
            'libro' => $resultado->libro?->getKey(),
            'deshacible' => $resultado->sePuedeDeshacer(),
        ]);
    }

    private function avisoDe(ResultadoDelEscaneo $resultado): string
    {
        $titulo = $this->entreComillas($resultado->libro?->titulo);

        return match ($resultado->desenlace) {
            DesenlaceDelEscaneo::Registrado => $titulo.' quedó registrado.',
            DesenlaceDelEscaneo::YaEstaEnElAcervo => $titulo.' ya está en el Acervo.',
            DesenlaceDelEscaneo::PasoAlAcervo => $titulo.' estaba Por comprar y pasó al Acervo.',
            DesenlaceDelEscaneo::IsbnDesconocido => 'Ninguna Fuente conoce el ISBN '.$resultado->isbn.'.',
            DesenlaceDelEscaneo::FuenteNoDisponible => 'Las Fuentes no pudieron contestar por el ISBN '.$resultado->isbn.'. Vuelve a escanearlo.',
            DesenlaceDelEscaneo::IsbnInvalido => $this->entreComillas($resultado->tecleado).' no es un ISBN.',
        };
    }

    private function colorDe(DesenlaceDelEscaneo $desenlace): string
    {
        return match ($desenlace) {
            DesenlaceDelEscaneo::Registrado, DesenlaceDelEscaneo::PasoAlAcervo => 'emerald',
            DesenlaceDelEscaneo::YaEstaEnElAcervo => 'zinc',
            default => 'amber',
        };
    }

    private function entreComillas(?string $texto): string
    {
        return '«'.($texto ?? '').'»';
    }

    /**
     * El campo vuelve a tener el foco después de cada Escaneo, sin que nadie
     * toque nada. Si el foco se va, el Escáner teclea al vacío (ADR-001).
     */
    private function devolverElFoco(): void
    {
        $this->dispatch('enfocar-el-isbn');
    }
}; ?>

<div
    class="flex w-full flex-col gap-8"
    x-data="{
        temporizador: null,
        espera: {{ (int) config('captura.inactividad') }},
        enfocar() {
            this.$refs.isbn?.focus()
        },
        detener() {
            clearTimeout(this.temporizador)
        },
        porInactividad(valor) {
            this.detener()

            if (! this.pareceCompleto(valor)) {
                return
            }

            this.temporizador = setTimeout(() => {
                this.$wire.set('isbn', valor, false)
                this.$wire.registrar()
            }, this.espera)
        },
        pareceCompleto(valor) {
            const limpio = valor.replace(/[\s-]/g, '').toUpperCase()

            if (/^\d{13}$/.test(limpio)) {
                return true
            }

            // Diez dígitos que empiezan con 978 o 979 no son un ISBN-10: son
            // un ISBN-13 al que todavía le faltan teclas.
            return /^\d{9}[\dX]$/.test(limpio) &amp;&amp; ! /^97[89]/.test(limpio)
        },
    }"
    x-init="enfocar()"
    x-on:enfocar-el-isbn.window="enfocar()"
>
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">Captura</flux:heading>
        <flux:text>Pasa el código de barras por el Escáner y el Libro se registra con la Ficha de la Fuente.</flux:text>
    </div>

    <form wire:submit="registrar" x-on:submit="detener()" class="max-w-md">
        <flux:input
            x-ref="isbn"
            wire:model="isbn"
            x-on:input="porInactividad($event.target.value)"
            label="ISBN"
            description="Los Escáneres que no mandan Enter registran solos, al dejar de teclear."
            autofocus
            autocomplete="off"
            inputmode="numeric"
            data-test="campo-isbn"
        />
    </form>

    @if ($aviso)
        <flux:text class="text-base" data-test="aviso">{{ $aviso }}</flux:text>
    @endif

    @if ($isbnSinFuente)
        <flux:button
            variant="subtle"
            class="self-start"
            :href="route('captura-manual', ['isbn' => $isbnSinFuente])"
            wire:navigate
        >
            Capturar a mano el ISBN {{ $isbnSinFuente }}
        </flux:button>
    @endif

    <div class="flex flex-col gap-3">
        <flux:heading size="lg">Esta ráfaga</flux:heading>

        @forelse ($rafaga as $entrada)
            <div
                wire:key="{{ $entrada['clave'] }}"
                class="flex items-center gap-4 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700"
            >
                <span @class([
                    'shrink-0 rounded-md px-2 py-1 text-xs font-medium',
                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200' => $entrada['color'] === 'emerald',
                    'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300' => $entrada['color'] === 'zinc',
                    'bg-amber-100 text-amber-900 dark:bg-amber-900 dark:text-amber-100' => $entrada['color'] === 'amber',
                ])>{{ $entrada['etiqueta'] }}</span>

                <div class="min-w-0 flex-1">
                    @if ($entrada['titulo'])
                        <flux:text class="truncate font-medium">{{ $entrada['titulo'] }}</flux:text>
                    @endif

                    <flux:text class="truncate text-sm">{{ $entrada['isbn'] }}</flux:text>
                </div>

                @if ($entrada['deshacible'])
                    <flux:button
                        size="sm"
                        variant="subtle"
                        wire:click="deshacer('{{ $entrada['clave'] }}')"
                    >
                        Deshacer
                    </flux:button>
                @endif
            </div>
        @empty
            <flux:text>Aquí aparece lo que vayas escaneando, lo último arriba.</flux:text>
        @endforelse
    </div>
</div>
