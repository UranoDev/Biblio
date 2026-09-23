<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_registration_feature_is_disabled(): void
    {
        $this->assertFalse(Features::enabled(Features::registration()));
    }

    public function test_the_registration_routes_do_not_exist(): void
    {
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Route::has('register.store'));
    }

    public function test_the_registration_screen_is_not_found(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_nobody_can_register(): void
    {
        $this->post('/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'john@example.com']);
        $this->assertGuest();
    }

    public function test_the_login_screen_offers_no_way_to_register(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('/register');
    }
}
