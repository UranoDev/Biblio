<?php

namespace App\Dominio;

use Stringable;

/**
 * El identificador impreso en el código de barras. Identifica la edición, no
 * la obra: dos ediciones del mismo título son dos ISBN y, por lo tanto, dos
 * Libros.
 *
 * Se guarda siempre normalizado a trece dígitos, de modo que el ISBN-10 y el
 * ISBN-13 del mismo Libro sean el mismo valor y lleguen al mismo resultado.
 */
final readonly class Isbn implements Stringable
{
    private function __construct(private string $isbn13) {}

    /**
     * @throws IsbnInvalido si el texto no es un ISBN o su dígito de control no cuadra.
     */
    public static function desde(string $valor): self
    {
        $limpio = self::normalizar($valor);

        if (preg_match('/^\d{9}[\dX]$/', $limpio) === 1) {
            if (! self::digitoDeControl10Correcto($limpio)) {
                throw IsbnInvalido::porDigitoDeControl($valor);
            }

            return new self(self::aTrece(substr($limpio, 0, 9)));
        }

        if (preg_match('/^\d{13}$/', $limpio) === 1) {
            if (! self::digitoDeControl13Correcto($limpio)) {
                throw IsbnInvalido::porDigitoDeControl($valor);
            }

            return new self($limpio);
        }

        throw IsbnInvalido::porFormato($valor);
    }

    public static function esValido(string $valor): bool
    {
        try {
            self::desde($valor);

            return true;
        } catch (IsbnInvalido) {
            return false;
        }
    }

    /**
     * El ISBN normalizado a trece dígitos, sin guiones. Es lo que se guarda y
     * lo que se le pregunta a la Fuente.
     */
    public function valor(): string
    {
        return $this->isbn13;
    }

    /**
     * El mismo ISBN escrito con diez dígitos, o null cuando no existe: sólo
     * los que empiezan con 978 tienen equivalente de diez.
     */
    public function comoDiez(): ?string
    {
        if (! str_starts_with($this->isbn13, '978')) {
            return null;
        }

        $nueve = substr($this->isbn13, 3, 9);
        $suma = 0;

        foreach (str_split($nueve) as $posicion => $digito) {
            $suma += (int) $digito * (10 - $posicion);
        }

        $control = (11 - $suma % 11) % 11;

        return $nueve.($control === 10 ? 'X' : (string) $control);
    }

    public function esIgual(self $otro): bool
    {
        return $this->isbn13 === $otro->isbn13;
    }

    public function __toString(): string
    {
        return $this->isbn13;
    }

    /**
     * El Escáner manda el número tal cual viene impreso: con guiones, con
     * espacios y a veces con la X minúscula.
     */
    private static function normalizar(string $valor): string
    {
        return strtoupper(str_replace([' ', '-', '‐', '–', '—'], '', trim($valor)));
    }

    private static function digitoDeControl10Correcto(string $isbn): bool
    {
        $suma = 0;

        foreach (str_split($isbn) as $posicion => $caracter) {
            $digito = $caracter === 'X' ? 10 : (int) $caracter;
            $suma += $digito * (10 - $posicion);
        }

        return $suma % 11 === 0;
    }

    private static function digitoDeControl13Correcto(string $isbn): bool
    {
        return self::control13(substr($isbn, 0, 12)) === $isbn[12];
    }

    private static function aTrece(string $nueve): string
    {
        $doce = '978'.$nueve;

        return $doce.self::control13($doce);
    }

    private static function control13(string $doce): string
    {
        $suma = 0;

        foreach (str_split($doce) as $posicion => $digito) {
            $suma += (int) $digito * ($posicion % 2 === 0 ? 1 : 3);
        }

        return (string) ((10 - $suma % 10) % 10);
    }
}
