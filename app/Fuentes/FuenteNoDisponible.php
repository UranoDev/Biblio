<?php

namespace App\Fuentes;

use RuntimeException;

/**
 * La Fuente no pudo contestar. Se usa dentro de un adaptador para cortar la
 * consulta a medias; hacia afuera sale convertida en `Respuesta::noDisponible`.
 */
final class FuenteNoDisponible extends RuntimeException {}
