@props([
    'url' => null,
    'titulo',
    'autores' => [],
])

{{--
    La Portada de una edición que todavía no es un Libro, así que la imagen
    sale de la Fuente: no hay ninguna guardada de qué tirar. La que se guarda
    es la del Libro ya registrado, y ésa nunca se enlaza (ADR-004).
--}}
@if (filled($url))
    <img
        src="{{ $url }}"
        alt="Portada de {{ $titulo }}"
        loading="lazy"
        referrerpolicy="no-referrer"
        {{ $attributes->class(['aspect-2/3 w-full rounded-lg object-cover']) }}
    >
@else
    <div
        role="img"
        aria-label="{{ $titulo }}, sin Portada"
        {{ $attributes->class([
            'flex aspect-2/3 w-full flex-col justify-end gap-1 rounded-lg',
            'bg-zinc-100 p-2 ring-1 ring-zinc-200 ring-inset',
            'dark:bg-zinc-800 dark:ring-zinc-700',
        ]) }}
    >
        <span class="line-clamp-4 text-xs font-medium text-zinc-800 dark:text-zinc-100">{{ $titulo }}</span>

        @if (filled($autores))
            <span class="line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ implode(', ', $autores) }}</span>
        @endif
    </div>
@endif
