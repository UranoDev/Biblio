<?php

namespace App\Providers;

use App\Covers\CoverStore;
use App\Sources\GoogleBooks;
use App\Sources\OpenLibrary;
use App\Sources\Source;
use App\Sources\SourceResolver;
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
            SourceResolver::class,
            fn (): SourceResolver => new SourceResolver(
                sources: $this->sources('sources.order'),
                titleOrder: $this->sources('sources.title_order'),
            ),
        );

        $this->app->singleton(
            CoverStore::class,
            fn (): CoverStore => new CoverStore(
                directory: (string) config('covers.directory', 'covers'),
                thumbnailWidth: (int) config('covers.thumbnail_width', 320),
                maximumWeight: (int) config('covers.max_weight', 5 * 1024 * 1024),
                minimumSide: (int) config('covers.minimum_side', 300),
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
     * The Sources in the configured order (ADR-002). There is one order per
     * operation: looking up an ISBN and searching a title do not suit the same
     * catalog equally well, and why is written down in `config/sources.php`.
     *
     * @return list<Source>
     */
    protected function sources(string $key = 'sources.order'): array
    {
        $timeout = (int) config('sources.timeout', 10);

        $sources = [];

        /** @var list<string> $order */
        $order = config($key, []);

        foreach ($order as $name) {
            $sources[] = match ($name) {
                'open_library' => new OpenLibrary(
                    url: (string) config('sources.open_library.url'),
                    coversUrl: (string) config('sources.open_library.covers_url'),
                    timeout: $timeout,
                ),
                'google_books' => new GoogleBooks(
                    url: (string) config('sources.google_books.url'),
                    key: config('sources.google_books.key') === null
                        ? null
                        : (string) config('sources.google_books.key'),
                    timeout: $timeout,
                ),
                default => throw new InvalidArgumentException("«{$name}» is not a known Source; check config/sources.php."),
            };
        }

        return $sources;
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
