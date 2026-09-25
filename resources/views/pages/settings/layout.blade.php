<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <flux:navlist aria-label="{{ __('Settings') }}">
            <flux:navlist.item :href="route('profile.edit')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
            <flux:navlist.item :href="route('security.edit')" wire:navigate>{{ __('Security') }}</flux:navlist.item>
            <flux:navlist.item :href="route('appearance.edit')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
            <flux:navlist.item :href="route('ai-model.edit')" wire:navigate>Modelo de IA</flux:navlist.item>
            <flux:navlist.item :href="route('regenerate')" wire:navigate>Regenerar</flux:navlist.item>
            <flux:navlist.item :href="route('quote-images')" wire:navigate>Imágenes para compartir</flux:navlist.item>
            <flux:navlist.item :href="route('covers')" wire:navigate>Portadas</flux:navlist.item>
            <flux:navlist.item :href="route('amazon-wishlist')" wire:navigate>Lista de Amazon</flux:navlist.item>
            <flux:navlist.item :href="route('failed-jobs')" wire:navigate>Trabajos fallidos</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
