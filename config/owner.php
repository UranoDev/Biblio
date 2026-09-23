<?php

return [

    /*
    |--------------------------------------------------------------------------
    | El dueño único
    |--------------------------------------------------------------------------
    |
    | Mi Biblioteca tiene un solo usuario y el registro está cerrado, así que
    | la cuenta la crea el seeder con estos valores. La contraseña vive en el
    | entorno y nunca en el repositorio.
    |
    */

    'name' => env('OWNER_NAME', 'Mi Biblioteca'),

    'email' => env('OWNER_EMAIL'),

    'password' => env('OWNER_PASSWORD'),

];
