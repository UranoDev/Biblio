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

    /*
    |--------------------------------------------------------------------------
    | El orden del Alta por título
    |--------------------------------------------------------------------------
    |
    | Es al revés que el de arriba, y no por gusto. Un Candidato es una edición
    | (ADR-005) y tiene que traer Portada, autores, editorial y año, porque sin
    | eso no hay forma de distinguir la de bolsillo de la del 2015.
    |
    | Comprobado el 2026-09-23 contra los catálogos reales, buscando «Pedro
    | Páramo» de Rulfo:
    |
    | - Open Library busca OBRAS, no ediciones. Devolvió 6 obras que entre
    |   todas suman 134 ediciones, y de cada una entrega UNA sola, elegida por
    |   ella: la de la primera obra no trae ISBN, y la de la segunda es una
    |   traducción al portugués. `editions.limit` se ignora, así que no hay
    |   manera de pedirle las demás; enumerarlas costaría una petición por obra.
    | - Google Books busca VOLÚMENES, y cada volumen es una edición con su
    |   `industryIdentifiers`, su editorial y su año en la misma respuesta. Es
    |   la forma que esta pantalla necesita, y en una sola petición.
    |
    | Por eso Google Books va primero aquí y Open Library queda de red: sirve
    | cuando la otra no pudo contestar, que con la búsqueda por título es el
    | caso más probable —es la operación que más rápido agota una cuota.
    |
    | El autor acota sin vaciar, medido el mismo día contra Open Library: el
    | título solo da 48 obras, con «Rulfo» da 6, y con «juan rulfo» da las
    | mismas 6 —ni las mayúsculas ni el nombre completo lo rompen.
    |
    */

    'orden_por_titulo' => ['google_books', 'open_library'],

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
