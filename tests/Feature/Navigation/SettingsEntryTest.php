<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;

/*
 * Por dónde se entra a Ajustes (BIB-56).
 *
 * El enlace vivía en el menú del perfil, arriba a la derecha, y ahí costaba
 * encontrarlo. Ahora está en la barra lateral, con el resto de las pantallas, y
 * el menú del perfil se quedó sólo con cerrar la sesión.
 *
 * Las subpantallas de adentro no cambiaron: se llega a las mismas direcciones
 * de siempre, y la primera sigue siendo Perfil.
 */

/** La etiqueta del renglón de la barra lateral que lleva a Ajustes. */
function sidebarSettingsItem(string $html): string
{
    preg_match(
        '#<a[^>]*href="[^"]*/settings/profile"[^>]*data-flux-sidebar-item[^>]*>#',
        $html,
        $matches,
    );

    return $matches[0] ?? '';
}

it('lleva a Ajustes desde la barra lateral', function () {
    $response = $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Ajustes')
        ->assertSee(route('profile.edit'));

    expect(sidebarSettingsItem((string) $response->getContent()))->not->toBe('');
});

it('marca Ajustes en la barra lateral desde cualquier subpantalla de adentro', function () {
    $user = User::factory()->create();

    foreach (['profile.edit', 'appearance.edit', 'failed-jobs'] as $screen) {
        $inside = (string) $this->actingAs($user)->get(route($screen))->getContent();

        // El atributo, no la clase: la lista de clases del renglón trae
        // `data-current:` en cada variante de Tailwind, marcado o no.
        expect(sidebarSettingsItem($inside))->toContain('data-current="data-current"');
    }

    $outside = (string) $this->actingAs($user)->get(route('dashboard'))->getContent();

    expect(sidebarSettingsItem($outside))->not->toContain('data-current="data-current"');
});

it('el menú del usuario ya no ofrece Ajustes, sólo cerrar la sesión', function () {
    $this->actingAs(User::factory()->create());

    // El menú se arma aparte del resto de la pantalla: la barra lateral sí
    // lleva a `settings/profile`, así que preguntárselo a la página entera no
    // distinguiría uno del otro.
    $menu = Blade::render('<x-user-menu />');

    expect($menu)
        ->not->toContain('Ajustes')
        ->not->toContain('Configuración')
        ->not->toContain(route('profile.edit'))
        ->toContain('Cerrar sesión')
        ->toContain(route('logout'));
});

it('entrar a Ajustes abre el Perfil, como siempre', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings')
        ->assertRedirect(route('profile.edit'));

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Perfil');
});

it('ninguna pantalla de adentro sigue diciendo «Configuración»', function () {
    $user = User::factory()->create();

    // Modelo de IA queda fuera: la pantalla le pregunta a los proveedores
    // quiénes están vivos, y el encabezado que se mira acá es el mismo de las
    // demás.
    foreach (['profile.edit', 'appearance.edit', 'regenerate', 'quote-images', 'covers', 'failed-jobs'] as $screen) {
        $this->actingAs($user)
            ->get(route($screen))
            ->assertOk()
            ->assertSee('Ajustes')
            ->assertDontSee('Configuración');
    }
});
