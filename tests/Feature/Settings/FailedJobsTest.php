<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The screen of the jobs the queue gave up on (BIB-40).
 *
 * It is where the counter of the Dashboard leads, and it is the only thing in
 * the whole application that says the queue stopped: the Categories, the
 * Description and the images of the Quotes all arrive through it.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function failedJob(string $job = 'App\\Jobs\\GenerateForBook', string $exception = "RuntimeException: no contestó\n#0 /app/foo.php(1)"): void
{
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => (string) json_encode(['displayName' => $job]),
        'exception' => $exception,
        'failed_at' => now(),
    ]);
}

it('pide sesión', function () {
    auth()->logout();

    $this->get(route('failed-jobs'))->assertRedirect(route('login'));
});

it('dice que ninguno falló cuando la cola está limpia', function () {
    $this->get(route('failed-jobs'))
        ->assertOk()
        ->assertSee('Ningún trabajo falló.');
});

it('dice qué trabajo fue, cuándo y qué contestó', function () {
    failedJob();

    $this->get(route('failed-jobs'))
        ->assertOk()
        ->assertSee('1 trabajo fallido.')
        ->assertSee('GenerateForBook')
        ->assertSee('RuntimeException: no contestó');
});

it('se queda con la línea del error y no con el rastro entero', function () {
    failedJob(exception: "RuntimeException: no contestó\n#0 /app/uno.php(1)\n#1 /app/dos.php(2)");

    $this->get(route('failed-jobs'))
        ->assertSee('RuntimeException: no contestó')
        ->assertDontSee('/app/dos.php');
});

it('llama al trabajo por su nombre corto y no por su espacio de nombres', function () {
    failedJob();

    $this->get(route('failed-jobs'))
        ->assertSee('GenerateForBook')
        ->assertDontSee('App\Jobs\GenerateForBook');
});
