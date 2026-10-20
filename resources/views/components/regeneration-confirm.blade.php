{{--
    The question before replacing Categories that were corrected by hand
    (BIB-22).

    The AI overwrites its own work without asking; what I corrected is replaced
    only after a yes.
--}}

@props(['book'])

<div class="flex flex-col gap-3" data-test="confirm-regeneration">
    <flux:callout variant="warning" icon="exclamation-triangle">
        <flux:callout.heading>Las categorías de «{{ $book->title }}» están corregidas a mano</flux:callout.heading>

        <flux:callout.text>
            Si la IA las vuelve a escribir, se reemplazan las que dejaste tú.
        </flux:callout.text>
    </flux:callout>

    <div class="flex flex-wrap gap-3">
        <flux:button variant="danger" wire:click="confirmRegeneration" data-test="confirm-regeneration-yes">
            Reemplazarlas
        </flux:button>

        <flux:button variant="outline" wire:click="cancelRegeneration" data-test="confirm-regeneration-no">
            Dejarlas como están
        </flux:button>
    </div>
</div>
