<?php

return [

    /*
    |--------------------------------------------------------------------------
    | El orden de la cascada
    |--------------------------------------------------------------------------
    |
    | Se consulta una por una hasta que alguna devuelva la Ficha. Cambiar el
    | orden o quitar una Fuente se hace aquí y no toca ninguna pantalla
    | (ADR-002). Las claves son las de los arreglos de abajo.
    |
    */

    'orden' => ['open_library', 'google_books'],

    'timeout' => (int) env('FUENTES_TIMEOUT', 10),

    'open_library' => [
        'url' => env('OPEN_LIBRARY_URL', 'https://openlibrary.org'),
        'portadas_url' => env('OPEN_LIBRARY_PORTADAS_URL', 'https://covers.openlibrary.org'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Books
    |--------------------------------------------------------------------------
    |
    | Sin llave el respaldo funciona igual, pero comparte una cuota global que
    | se agota y devuelve HTTP 429. Eso se reporta como «la Fuente no pudo
    | contestar», nunca como «no lo conoce».
    |
    */

    'google_books' => [
        'url' => env('GOOGLE_BOOKS_URL', 'https://www.googleapis.com'),
        'llave' => env('GOOGLE_BOOKS_API_KEY'),
    ],

];
