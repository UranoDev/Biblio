<?php

namespace App\Enums;

/**
 * Si un Libro está en el Acervo o Por comprar.
 *
 * Son la misma entidad con distinta Situación, no dos listas: un Libro que se
 * compra no se vuelve a capturar, nada más cambia de Situación.
 */
enum Situacion: string
{
    case Acervo = 'acervo';
    case PorComprar = 'por_comprar';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Acervo => 'Acervo',
            self::PorComprar => 'Por comprar',
        };
    }
}
