<?php

use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The tokens and components of the back office on one screen, drawn with the
 * same CSS and the same Flux components the other screens use, so what is seen
 * here is what they look like.
 */
new #[Title('Guía de estilos')] class extends Component
{
    /** @return list<array{name: string, token: string, hex: string, use: string}> */
    public function palette(): array
    {
        return [
            ['name' => 'Papel', 'token' => '--color-paper', 'hex' => '#faf6ee', 'use' => 'Fondo de todo, con grano.'],
            ['name' => 'Tinta', 'token' => '--color-ink', 'hex' => '#1a1a18', 'use' => 'Texto y botón principal.'],
            ['name' => 'Tinta tenue', 'token' => '--color-ink-muted', 'hex' => '#6b6459', 'use' => 'Texto secundario.'],
            ['name' => 'Regla', 'token' => '--color-rule', 'hex' => '#d4cec0', 'use' => 'Bordes de botones y la línea de las Citas.'],
            ['name' => 'Divisor', 'token' => '--color-divider', 'hex' => '#e2ddd1', 'use' => 'Separadores.'],
            ['name' => 'Enlace', 'token' => '--color-link', 'hex' => '#8a5a2b', 'use' => 'Lo único con color: los enlaces.'],
        ];
    }
}; ?>

<div class="flex w-full max-w-4xl flex-col gap-12">
    <div class="flex flex-col gap-1">
        <flux:heading size="xl">Guía de estilos</flux:heading>
        <flux:text>Los colores, las letras y los componentes con los que están hechas las pantallas.</flux:text>
    </div>

    <section class="flex flex-col gap-4" data-test="palette">
        <flux:heading size="lg">Colores</flux:heading>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->palette() as $color)
                <div class="flex items-center gap-4">
                    <div class="size-14 shrink-0 rounded-md border border-rule" style="background: {{ $color['hex'] }}"></div>

                    <div class="flex min-w-0 flex-col">
                        <span class="font-medium text-ink">{{ $color['name'] }}</span>
                        <span class="font-mono text-xs text-ink-muted">{{ $color['hex'] }} · {{ $color['token'] }}</span>
                        <span class="text-sm text-ink-muted">{{ $color['use'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <flux:separator />

    <section class="flex flex-col gap-6" data-test="type">
        <flux:heading size="lg">Letras</flux:heading>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <span class="font-sans text-3xl text-ink">Instrument Sans</span>
                <span class="text-sm text-ink-muted">Toda la interfaz.</span>
            </div>

            <div class="flex flex-col gap-1">
                <span class="font-mono text-3xl text-ink">IBM Plex Mono</span>
                <span class="text-sm text-ink-muted">Las Citas y los datos: ISBN, año.</span>
            </div>
        </div>

        <div class="flex flex-col gap-5">
            <div class="flex flex-col gap-1">
                <span class="text-xs text-ink-muted">flux:heading xl · lg · base</span>
                <flux:heading size="xl">Alta por título</flux:heading>
                <flux:heading size="lg">Ediciones de «Pedro Páramo»</flux:heading>
                <flux:heading>Portada</flux:heading>
            </div>

            <div class="flex flex-col gap-1">
                <span class="text-xs text-ink-muted">flux:text · flux:subheading</span>
                <flux:text>Pasa el código de barras por el Escáner y el Libro se registra.</flux:text>
                <flux:subheading>Opcional. Acota las ediciones que devuelve la Fuente.</flux:subheading>
            </div>

            <div class="flex flex-col gap-1">
                <span class="text-xs text-ink-muted">type-section-heading · type-book-title · type-authors</span>
                <span class="type-section-heading text-ink-muted">Se ve en la Vitrina</span>
                <span class="type-book-title text-ink">Pedro Páramo</span>
                <span class="type-authors text-ink-muted">Juan Rulfo</span>
            </div>

            <div class="flex flex-col gap-1">
                <span class="text-xs text-ink-muted">type-quote</span>
                <p class="type-quote text-ink">Vine a Comala porque me dijeron que acá vivía mi padre, un tal Pedro Páramo.</p>
            </div>
        </div>
    </section>

    <flux:separator />

    <section class="flex flex-col gap-6" data-test="buttons">
        <flux:heading size="lg">Botones</flux:heading>

        <div class="flex flex-col gap-3">
            <span class="text-xs text-ink-muted">Los que usan las pantallas</span>

            <div class="flex flex-wrap items-center gap-3">
                <flux:button variant="primary">primary</flux:button>
                <flux:button variant="outline">outline</flux:button>
                <flux:button variant="filled">filled</flux:button>
                <flux:button variant="danger">danger</flux:button>
                <flux:button variant="outline" icon="pencil-square">outline con ícono</flux:button>
                <flux:button variant="outline" size="sm">outline sm</flux:button>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <span class="text-xs text-ink-muted">Los que ya no usa ninguna pantalla, porque parecen texto</span>

            <div class="flex flex-wrap items-center gap-3">
                <flux:button variant="subtle">subtle</flux:button>
                <flux:button variant="ghost">ghost</flux:button>
            </div>
        </div>
    </section>

    <flux:separator />

    <section class="flex flex-col gap-4" data-test="links">
        <flux:heading size="lg">Enlaces</flux:heading>

        <div class="flex flex-col gap-3">
            <div class="flex flex-col gap-1">
                <span class="text-xs text-ink-muted">flux:link</span>
                <flux:link href="#">Ver la ficha del Libro</flux:link>
            </div>

            <div class="flex flex-col gap-1">
                <span class="text-xs text-ink-muted">a.text-link, como en la Vitrina</span>
                <a href="#" class="text-link">Novela mexicana</a>
            </div>

            <div class="flex flex-col gap-1">
                <span class="text-xs text-ink-muted">flux:sidebar.item, el menú</span>
                <div class="max-w-xs">
                    <flux:sidebar.item icon="book-open-text" href="#">Alta por título</flux:sidebar.item>
                </div>
            </div>
        </div>
    </section>

    <flux:separator />

    <section class="flex flex-col gap-6" data-test="fields">
        <flux:heading size="lg">Campos</flux:heading>

        <div class="grid items-start gap-6 sm:grid-cols-2">
            <flux:input label="Título" value="Pedro Páramo" />
            <flux:input label="Autor" description:trailing="Opcional. Acota las ediciones que devuelve la Fuente." />
            <flux:select label="Situación">
                <flux:select.option>Acervo</flux:select.option>
                <flux:select.option>Por comprar</flux:select.option>
            </flux:select>
            <flux:textarea label="Apunte" rows="2" />
        </div>

        <div class="flex flex-wrap gap-10">
            <flux:radio.group label="Situación">
                <flux:radio value="owned" label="Acervo" checked />
                <flux:radio value="wanted" label="Por comprar" />
            </flux:radio.group>

            <flux:checkbox label="Mostrar en la Vitrina" checked />
        </div>
    </section>

    <flux:separator />

    <section class="flex flex-col gap-4" data-test="feedback">
        <flux:heading size="lg">Avisos y etiquetas</flux:heading>

        <div class="flex flex-wrap gap-2">
            <flux:badge>Acervo</flux:badge>
            <flux:badge color="amber">Por comprar</flux:badge>
            <flux:badge color="green">Leído</flux:badge>
        </div>

        <flux:callout icon="information-circle">
            <flux:callout.heading>Ningún Modelo elegido</flux:callout.heading>
            <flux:callout.text>Las Categorías se piden cuando eliges un Modelo en Ajustes.</flux:callout.text>
        </flux:callout>
    </section>
</div>
