@props([
    'libro',
    'tamano' => 'miniatura',
])

@php
    $url = $tamano === 'completa' ? $libro->portadaUrl() : $libro->portadaMiniaturaUrl();
@endphp

{{-- Un Libro sin Portada no muestra una imagen rota: muestra su título. --}}
@if ($url)
    <img
        src="{{ $url }}"
        alt="Portada de {{ $libro->titulo }}"
        loading="lazy"
        {{ $attributes->class(['aspect-2/3 w-full rounded-lg object-cover']) }}
    >
@else
    <div
        role="img"
        aria-label="{{ $libro->titulo }}, sin Portada"
        {{ $attributes->class([
            'flex aspect-2/3 w-full flex-col justify-end gap-1 rounded-lg',
            'bg-zinc-100 p-3 ring-1 ring-zinc-200 ring-inset',
            'dark:bg-zinc-800 dark:ring-zinc-700',
        ]) }}
    >
        <span class="line-clamp-4 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $libro->titulo }}</span>

        @if (filled($libro->autores))
            <span class="line-clamp-2 text-xs text-zinc-500 dark:text-zinc-400">{{ implode(', ', $libro->autores) }}</span>
        @endif
    </div>
@endif
