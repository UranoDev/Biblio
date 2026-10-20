<?php

use App\Models\User;

it('pide sesión', function () {
    $this->get(route('style-guide'))->assertRedirect(route('login'));
});

it('muestra los colores, las letras y los componentes', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('style-guide'))
        ->assertOk()
        ->assertSee('Guía de estilos')
        ->assertSee('#faf6ee')
        ->assertSee('Instrument Sans')
        ->assertSee('IBM Plex Mono')
        ->assertSee('data-flux-button', false);
});
