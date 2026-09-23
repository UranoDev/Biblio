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
     * Create the only user of the system, with the data from the environment.
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

        // The dashboard requires `verified` and the only mail channel in this
        // project is the log, so the owner is born verified. The model API is
        // used instead of assigning `now()` to the property: the app runs with
        // CarbonImmutable (AppServiceProvider) and the declared type of
        // email_verified_at is the mutable Carbon.
        if (! $owner->hasVerifiedEmail()) {
            $owner->markEmailAsVerified();
        }
    }
}
