<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class OwnerSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Crear al único usuario del sistema, con los datos del entorno.
     */
    public function run(): void
    {
        $email = config('owner.email');
        $password = config('owner.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException(
                'Faltan OWNER_EMAIL y OWNER_PASSWORD en el entorno: sin ellos no se puede crear al dueño.'
            );
        }

        $owner = User::firstOrNew(['email' => $email]);

        $owner->name = config('owner.name');
        $owner->email = $email;
        $owner->password = $password;
        $owner->save();

        // El dashboard exige `verified` y el unico canal de correo en este
        // proyecto es el log, asi que el dueno nace verificado. Se usa la API
        // del modelo en vez de asignar `now()` a la propiedad: la app corre con
        // CarbonImmutable (AppServiceProvider) y el tipo declarado de
        // email_verified_at es el Carbon mutable.
        if (! $owner->hasVerifiedEmail()) {
            $owner->markEmailAsVerified();
        }
    }
}
