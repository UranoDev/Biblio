<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dónde viven las Portadas
    |--------------------------------------------------------------------------
    |
    | El disco es el que diga FILESYSTEM_DISK y no se nombra aquí: el código
    | siempre escribe en el disco por omisión (ADR-004). Lo único que se
    | configura es la carpeta dentro de ese disco.
    |
    */

    'directorio' => env('PORTADAS_DIRECTORIO', 'portadas'),

    /*
    |--------------------------------------------------------------------------
    | La versión reducida
    |--------------------------------------------------------------------------
    |
    | El ancho, en píxeles, de la miniatura que usan las retículas. La original
    | se guarda tal como llegó.
    |
    */

    'ancho_miniatura' => (int) env('PORTADAS_ANCHO_MINIATURA', 320),

    /*
    |--------------------------------------------------------------------------
    | Límites de la descarga
    |--------------------------------------------------------------------------
    |
    | Una descarga que tarda de más no detiene el registro del Libro: se corta
    | y queda para reintentar.
    |
    */

    'timeout' => (int) env('PORTADAS_TIMEOUT', 10),

    'peso_maximo' => (int) env('PORTADAS_PESO_MAXIMO', 5 * 1024 * 1024),

];
