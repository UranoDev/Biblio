<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\OwnerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OwnerSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'owner.name' => 'Dueño',
            'owner.email' => 'dueno@example.com',
            'owner.password' => 'la-del-entorno',
        ]);
    }

    public function test_it_creates_the_only_user(): void
    {
        $this->seed(OwnerSeeder::class);

        $this->assertSame(1, User::count());

        $owner = User::sole();

        $this->assertSame('Dueño', $owner->name);
        $this->assertSame('dueno@example.com', $owner->email);
        $this->assertNotNull($owner->email_verified_at);
    }

    public function test_the_owner_can_log_in_with_the_password_from_the_environment(): void
    {
        $this->seed(OwnerSeeder::class);

        $response = $this->post(route('login.store'), [
            'email' => 'dueno@example.com',
            'password' => 'la-del-entorno',
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('shelf', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_seeding_twice_leaves_a_single_user(): void
    {
        $this->seed(OwnerSeeder::class);
        $this->seed(OwnerSeeder::class);

        $this->assertSame(1, User::count());
    }

    public function test_it_fails_when_the_environment_has_no_password(): void
    {
        config(['owner.password' => null]);

        $this->expectException(RuntimeException::class);

        $this->seed(OwnerSeeder::class);
    }
}
