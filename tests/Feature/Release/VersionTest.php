<?php

use App\Models\User;
use App\Release\Version;

/*
 * Qué versión está corriendo (BIB-39).
 *
 * El servidor no tiene `.git`, así que la única respuesta posible es la que
 * viaja escrita en el repositorio: el encabezado más reciente de CHANGELOG.md.
 * Se prueban los dos estados, porque el segundo es el de hoy: sin changelog
 * ninguna pantalla truena y el texto dice que no hay versión cortada.
 */

/** Dónde apunta la aplicación durante estas pruebas, en vez del changelog real. */
function changelogUnderTest(): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'bib-changelog-'.getmypid().'.md';
}

function changelogSays(string $contents): void
{
    file_put_contents(changelogUnderTest(), $contents);
    Version::forget();
}

beforeEach(function () {
    config(['release.changelog' => changelogUnderTest()]);

    @unlink(changelogUnderTest());
    Version::forget();
});

afterEach(function () {
    @unlink(changelogUnderTest());
    Version::forget();
});

it('la versión es la del encabezado más reciente del changelog', function () {
    changelogSays(<<<'MD'
        # Changelog

        ## [2026.09.24] - 2026 sep 24

        - BIB-39: saber qué versión está corriendo

        ## [2026.09.01] - 2026 sep 01

        - BIB-1: el principio
        MD);

    expect(Version::current())->toBe('2026.09.24')
        ->and(Version::label())->toBe('V. 2026.09.24');
});

it('lo que está resuelto pero sin cortar no es una versión', function () {
    changelogSays(<<<'MD'
        # Changelog

        ## [Unreleased]

        - BIB-40: algo que todavía no se publica

        ## [2026.09.24] - 2026 sep 24

        - BIB-39: saber qué versión está corriendo
        MD);

    expect(Version::current())->toBe('2026.09.24');
});

it('sin changelog no hay versión, y se dice así', function () {
    expect(Version::current())->toBeNull()
        ->and(Version::label())->toBe('Sin versión cortada');
});

it('la versión se comprueba sin iniciar sesión', function () {
    changelogSays("## [2026.09.24] - 2026 sep 24\n");

    $this->get(route('showcase'))
        ->assertOk()
        ->assertSee('<meta name="version" content="2026.09.24">', escape: false);
});

it('sin changelog la cara pública no truena ni inventa una versión', function () {
    $this->get(route('showcase'))
        ->assertOk()
        ->assertDontSee('name="version"', escape: false);
});

it('el back office dice qué versión está corriendo, debajo del nombre', function () {
    changelogSays("## [2026.09.24] - 2026 sep 24\n");

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('V. 2026.09.24')
        ->assertSee('<meta name="version" content="2026.09.24" />', escape: false);
});

it('sin changelog el back office no truena y lo dice', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Sin versión cortada');
});

it('la barra lateral ya no lleva la documentación de Laravel al pie', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('laravel.com/docs')
        ->assertDontSee('github.com/laravel/livewire-starter-kit');
});

it('se llega a Configuración y a Cerrar sesión desde el menú del usuario', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="user-menu-button"', escape: false)
        ->assertSee(route('profile.edit'))
        ->assertSee('Cerrar sesión')
        ->assertSee('data-test="logout-button"', escape: false);
});
