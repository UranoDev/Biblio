<?php

namespace App\Enums;

/**
 * En qué terminó pasar un código de barras por el Escáner.
 *
 * Los tres primeros son los del reescaneo (ADR-005) y los tres últimos son las
 * formas de no registrar nada. «No lo conoce» y «no pudo contestar» se
 * distinguen porque llevan a cosas distintas: el primero ofrece Captura
 * manual y el segundo pide volver a escanear (ADR-002).
 */
enum DesenlaceDelEscaneo: string
{
    case Registrado = 'registrado';
    case YaEstaEnElAcervo = 'ya_esta_en_el_acervo';
    case PasoAlAcervo = 'paso_al_acervo';
    case IsbnDesconocido = 'isbn_desconocido';
    case FuenteNoDisponible = 'fuente_no_disponible';
    case IsbnInvalido = 'isbn_invalido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Registrado => 'Registrado',
            self::YaEstaEnElAcervo => 'Ya está en el Acervo',
            self::PasoAlAcervo => 'Pasó al Acervo',
            self::IsbnDesconocido => 'Ninguna Fuente lo conoce',
            self::FuenteNoDisponible => 'La Fuente no pudo contestar',
            self::IsbnInvalido => 'No es un ISBN',
        };
    }

    /** Si el Escaneo dejó un Libro en el Acervo, sea nuevo o ya registrado. */
    public function hayLibro(): bool
    {
        return match ($this) {
            self::Registrado, self::YaEstaEnElAcervo, self::PasoAlAcervo => true,
            default => false,
        };
    }
}
