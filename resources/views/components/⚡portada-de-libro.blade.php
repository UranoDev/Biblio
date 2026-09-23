<?php

use App\Jobs\DescargarPortada;
use App\Models\Libro;
use App\Portadas\AlmacenDePortadas;
use App\Portadas\ImagenInvalida;
use App\Portadas\NoSePudoGuardarLaPortada;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * La Portada de un Libro, con las dos vías que no dependen de la Fuente:
 * subir una a mano y volver a intentar la descarga (ADR-004).
 */
new class extends Component
{
    use WithFileUploads;

    public Libro $libro;

    public $archivo = null;

    public ?string $aviso = null;

    public function guardar(AlmacenDePortadas $almacen): void
    {
        $this->validate(
            ['archivo' => ['required', 'image', 'max:5120']],
            [
                'archivo.required' => 'Falta elegir una imagen.',
                'archivo.image' => 'El archivo tiene que ser una imagen.',
                'archivo.max' => 'La imagen no puede pesar más de 5 MB.',
            ],
        );

        try {
            $almacen->guardarSubida($this->libro, $this->archivo);
        } catch (ImagenInvalida) {
            $this->addError('archivo', 'El archivo no se pudo leer como imagen.');

            return;
        } catch (NoSePudoGuardarLaPortada) {
            $this->addError('archivo', 'La Portada no se pudo guardar en el disco.');

            return;
        }

        $this->reset('archivo', 'aviso');
        $this->libro->refresh();
    }

    public function reintentar(): void
    {
        $this->aviso = DescargarPortada::reintentar($this->libro)
            ? 'La descarga de la Portada quedó en cola.'
            : 'Este Libro no tiene de dónde bajar la Portada.';
    }
}; ?>

<div class="flex gap-6">
    <x-portada :libro="$libro" tamano="completa" class="w-40 shrink-0" />

    <div class="flex flex-col gap-3">
        <flux:heading size="sm">Portada</flux:heading>

        @unless ($libro->tienePortada())
            <flux:text>
                @if ($libro->puedeReintentarLaPortada())
                    La descarga de la Portada no se completó.
                @else
                    Este Libro no tiene Portada.
                @endif
            </flux:text>

            @if ($libro->puedeReintentarLaPortada())
                <flux:button wire:click="reintentar" variant="subtle" class="self-start">
                    Reintentar la descarga
                </flux:button>
            @endif
        @endunless

        @if ($aviso)
            <flux:text>{{ $aviso }}</flux:text>
        @endif

        <form wire:submit="guardar" class="flex flex-col gap-3">
            <flux:input
                type="file"
                wire:model="archivo"
                accept="image/*"
                :label="$libro->tienePortada() ? 'Reemplazar la Portada' : 'Subir una Portada'"
            />

            <flux:button type="submit" variant="primary" class="self-start">Guardar</flux:button>
        </form>
    </div>
</div>
