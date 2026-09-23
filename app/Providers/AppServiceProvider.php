<?php

namespace App\Providers;

use App\Fuentes\Fuente;
use App\Fuentes\GoogleBooks;
use App\Fuentes\OpenLibrary;
use App\Fuentes\ResolvedorDeFuentes;
use App\Portadas\AlmacenDePortadas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            ResolvedorDeFuentes::class,
            fn (): ResolvedorDeFuentes => new ResolvedorDeFuentes(
                fuentes: $this->fuentes('fuentes.orden'),
                porTitulo: $this->fuentes('fuentes.orden_por_titulo'),
            ),
        );

        $this->app->singleton(
            AlmacenDePortadas::class,
            fn (): AlmacenDePortadas => new AlmacenDePortadas(
                directorio: (string) config('portadas.directorio', 'portadas'),
                anchoDeLaMiniatura: (int) config('portadas.ancho_miniatura', 320),
                pesoMaximo: (int) config('portadas.peso_maximo', 5 * 1024 * 1024),
            ),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Las Fuentes en el orden configurado (ADR-002). Hay un orden por
     * operación: consultar un ISBN y buscar un título no se le dan igual de
     * bien al mismo catálogo, y por qué está anotado en `config/fuentes.php`.
     *
     * @return list<Fuente>
     */
    protected function fuentes(string $clave = 'fuentes.orden'): array
    {
        $timeout = (int) config('fuentes.timeout', 10);

        $fuentes = [];

        /** @var list<string> $orden */
        $orden = config($clave, []);

        foreach ($orden as $clave) {
            $fuentes[] = match ($clave) {
                'open_library' => new OpenLibrary(
                    url: (string) config('fuentes.open_library.url'),
                    portadasUrl: (string) config('fuentes.open_library.portadas_url'),
                    timeout: $timeout,
                ),
                'google_books' => new GoogleBooks(
                    url: (string) config('fuentes.google_books.url'),
                    llave: config('fuentes.google_books.llave') === null
                        ? null
                        : (string) config('fuentes.google_books.llave'),
                    timeout: $timeout,
                ),
                default => throw new InvalidArgumentException("«{$clave}» no es una Fuente conocida; revisá config/fuentes.php."),
            };
        }

        return $fuentes;
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
