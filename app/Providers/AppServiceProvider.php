<?php

namespace App\Providers;

use App\Ai\LocalServer;
use App\Covers\CoverStore;
use App\Jobs\ProposeForBook;
use App\Sources\GoogleBooks;
use App\Sources\OpenLibrary;
use App\Sources\Source;
use App\Sources\SourceResolver;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
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

        // One instance for the whole request, so that the Settings screen asks
        // the local server whether it is up once and not once per question.
        $this->app->singleton(LocalServer::class);

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
        $this->configureTheAiPace();
    }

    /**
     * How fast the AI gets asked (BIB-33).
     *
     * Every provider puts a ceiling on it, and capturing the shelf is two
     * hundred Books in one sitting. The count lives in the cache and not in the
     * memory of a process, which is what makes several workers add up to one
     * rhythm instead of one each.
     */
    protected function configureTheAiPace(): void
    {
        RateLimiter::for(
            ProposeForBook::LIMITER,
            fn (): Limit => Limit::perMinute(
                (int) config('proposals.requests_per_minute', 5)
            )->by(ProposeForBook::LIMITER),
        );
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

        /*
         * Relative dates follow the app locale too (BIB-26). Laravel points the
         * translator at it and stops there, so without this a passkey added an
         * hour ago reads «1 hour ago» on a screen that is otherwise in Spanish.
         */
        Carbon::setLocale(config('app.locale'));
        CarbonImmutable::setLocale(config('app.locale'));

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
