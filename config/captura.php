<?php

return [

    /*
    |--------------------------------------------------------------------------
    | El registro por inactividad
    |--------------------------------------------------------------------------
    |
    | Casi todos los Escáneres mandan Enter al final del código, y ésos
    | registran con el Enter. Los que no lo mandan registran solos cuando el
    | campo deja de recibir teclas durante estos milisegundos (ADR-001). Nunca
    | se pide oprimir un botón por libro: es el paso que se paga veinte veces.
    |
    | Un Escáner teclea los trece dígitos en menos de cien milisegundos, así
    | que esta espera no corta un código a la mitad.
    |
    */

    'inactividad' => (int) env('CAPTURA_INACTIVIDAD_MS', 600),

];
