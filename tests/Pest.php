<?php

use App\Dominio\Isbn;
use App\Enums;
use App\Fuentes\Ficha;
use App\Fuentes\Fuente;
use App\Fuentes\ResolvedorDeFuentes;
use App\Fuentes\Respuesta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // Ninguna prueba sale a internet. Http::fake() con patrones de URL no
        // alcanza: Laravel ejecuta de verdad las peticiones que no coinciden
        // con ningun patron (preventStrayRequests viene en false). Asi el dia
        // que un adaptador pida una URL nueva, la prueba falla diciendolo en
        // vez de pegarle al catalogo real.
        Http::preventStrayRequests();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Una respuesta grabada de una Fuente. Las pruebas de los adaptadores corren
 * contra estos archivos y nunca salen a internet.
 */
function grabada(string $nombre): string
{
    return file_get_contents(__DIR__."/Fixtures/fuentes/{$nombre}.json");
}

/**
 * Una imagen de verdad, hecha en el momento: las pruebas de Portadas no salen
 * a internet ni cargan archivos binarios al repo.
 */
function imagenDePrueba(int $ancho = 400, int $alto = 600, string $formato = 'jpeg'): string
{
    $lienzo = imagecreatetruecolor($ancho, $alto);

    $color = imagecolorallocate($lienzo, 190, 40, 40);

    if ($color !== false) {
        imagefilledrectangle($lienzo, 0, 0, $ancho, $alto, $color);
    }

    ob_start();

    match ($formato) {
        'png' => imagepng($lienzo),
        'gif' => imagegif($lienzo),
        'webp' => imagewebp($lienzo),
        default => imagejpeg($lienzo, null, 90),
    };

    $binario = (string) ob_get_clean();

    imagedestroy($lienzo);

    return $binario;
}

/**
 * Lo que contestan las Fuentes durante la prueba. Reemplaza el resolvedor del
 * contenedor, de modo que nada sale a internet y la pantalla recibe el
 * desenlace que la prueba quiera.
 *
 * @param  Respuesta|(callable(Isbn): Respuesta)  $respuesta
 */
function lasFuentesContestan(Respuesta|callable $respuesta): void
{
    $fuente = new class($respuesta) implements Fuente
    {
        /** @param  Respuesta|(callable(Isbn): Respuesta)  $respuesta */
        public function __construct(private readonly mixed $respuesta) {}

        public function identificador(): Enums\Fuente
        {
            return Enums\Fuente::OpenLibrary;
        }

        public function porIsbn(Isbn $isbn): Respuesta
        {
            return $this->respuesta instanceof Respuesta
                ? $this->respuesta
                : ($this->respuesta)($isbn);
        }
    };

    app()->instance(ResolvedorDeFuentes::class, new ResolvedorDeFuentes([$fuente]));
}

/**
 * Una Ficha armada a mano, con la forma que tendría la de cualquier Fuente.
 *
 * @param  list<string>  $autores
 */
function fichaDe(
    string $isbn,
    string $titulo = 'Pedro Páramo',
    array $autores = ['Juan Rulfo'],
    ?string $portadaUrl = null,
    ?string $descripcion = null,
): Ficha {
    return new Ficha(
        isbn: Isbn::desde($isbn),
        titulo: $titulo,
        autores: $autores,
        editorial: 'Editorial RM',
        anio: 2005,
        edicion: null,
        portadaUrl: $portadaUrl,
        descripcion: $descripcion,
        fuente: Enums\Fuente::OpenLibrary,
    );
}

/**
 * Un ISBN-13 con su dígito de control correcto, para las ráfagas de prueba:
 * el Escaneo rechaza los números que no cuadran, así que no sirve cualquiera.
 */
function isbnDePrueba(int $numero): string
{
    $doce = '978'.str_pad((string) $numero, 9, '0', STR_PAD_LEFT);
    $suma = 0;

    foreach (str_split($doce) as $posicion => $digito) {
        $suma += (int) $digito * ($posicion % 2 === 0 ? 1 : 3);
    }

    return $doce.((10 - $suma % 10) % 10);
}
