{{--
    El menú de quien administra el acervo: quién tiene la sesión abierta y
    Cerrar sesión.

    Vive en la esquina superior derecha de la barra de arriba, en pantalla ancha
    y en pantalla angosta (BIB-39). Antes estaba al pie de la barra lateral,
    debajo de dos enlaces a la documentación de Laravel, que es donde acaba lo
    que no tenía otro lugar.

    Ajustes salió de acá y se fue a la barra lateral (BIB-56): en el menú del
    perfil costaba encontrarlo, y de la cuenta lo único que queda es cerrar la
    sesión.
--}}
<flux:dropdown position="bottom" align="end">
    <flux:profile
        :initials="auth()->user()->initials()"
        icon-trailing="chevron-down"
        data-test="user-menu-button"
    />

    <flux:menu>
        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
            <flux:avatar
                :name="auth()->user()->name"
                :initials="auth()->user()->initials()"
            />

            <div class="grid flex-1 text-start text-sm leading-tight">
                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
            </div>
        </div>

        <flux:menu.separator />

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item
                as="button"
                type="submit"
                icon="arrow-right-start-on-rectangle"
                class="w-full cursor-pointer"
                data-test="logout-button"
            >
                Cerrar sesión
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
