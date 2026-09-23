<?php

use App\Domain\Isbn;
use App\Enums;
use App\Sources\Candidate;
use App\Sources\Candidates;
use App\Sources\LookupResult;
use App\Sources\Record;
use App\Sources\Source;
use App\Sources\SourceResolver;
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
        // No test goes out to the internet. Http::fake() with URL patterns is
        // not enough: Laravel really executes the requests that match no
        // pattern (preventStrayRequests comes in false). This way, the day an
        // adapter asks for a new URL, the test fails saying so instead of
        // hitting the real catalog.
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
 * A recorded response from a Source. The adapter tests run against these files
 * and never go out to the internet.
 */
function recorded(string $name): string
{
    return file_get_contents(__DIR__."/Fixtures/sources/{$name}.json");
}

/**
 * A real image, made on the spot: the Cover tests do not go out to the internet
 * nor load binary files into the repo.
 */
function testImage(int $width = 400, int $height = 600, string $format = 'jpeg'): string
{
    $canvas = imagecreatetruecolor($width, $height);

    $color = imagecolorallocate($canvas, 190, 40, 40);

    if ($color !== false) {
        imagefilledrectangle($canvas, 0, 0, $width, $height, $color);
    }

    ob_start();

    match ($format) {
        'png' => imagepng($canvas),
        'gif' => imagegif($canvas),
        'webp' => imagewebp($canvas),
        default => imagejpeg($canvas, null, 90),
    };

    $binary = (string) ob_get_clean();

    imagedestroy($canvas);

    return $binary;
}

/**
 * What the Sources answer during the test. It replaces the resolver in the
 * container, so that nothing goes out to the internet and the screen receives
 * the outcome the test wants.
 *
 * @param  LookupResult|(callable(Isbn): LookupResult)  $result
 * @param  Candidates|(callable(string, ?string): Candidates)|null  $candidates  what the
 *                                                                               TitleLookup answers; no Candidates when not given.
 */
function sourcesAnswer(LookupResult|callable $result, Candidates|callable|null $candidates = null): void
{
    $source = new class($result, $candidates) implements Source
    {
        /**
         * @param  LookupResult|(callable(Isbn): LookupResult)  $result
         * @param  Candidates|(callable(string, ?string): Candidates)|null  $candidates
         */
        public function __construct(
            private readonly mixed $result,
            private readonly mixed $candidates,
        ) {}

        public function identifier(): Enums\Source
        {
            return Enums\Source::OpenLibrary;
        }

        public function byIsbn(Isbn $isbn): LookupResult
        {
            return $this->result instanceof LookupResult
                ? $this->result
                : ($this->result)($isbn);
        }

        public function byTitle(string $title, ?string $author = null): Candidates
        {
            if ($this->candidates === null) {
                return Candidates::none();
            }

            return $this->candidates instanceof Candidates
                ? $this->candidates
                : ($this->candidates)($title, $author);
        }
    };

    app()->instance(SourceResolver::class, new SourceResolver([$source]));
}

/**
 * What the Source answers in a TitleLookup, when the test does not need to say
 * anything about the Scan.
 *
 * @param  Candidates|(callable(string, ?string): Candidates)  $candidates
 */
function sourcesReturn(Candidates|callable $candidates): void
{
    sourcesAnswer(LookupResult::withoutRecord(), $candidates);
}

/**
 * A Candidate built by hand, with the shape the one from any Source would have.
 *
 * @param  list<string>  $authors
 */
function candidateOf(
    ?string $isbn = null,
    string $title = 'Pedro Páramo',
    array $authors = ['Juan Rulfo'],
    ?string $publisher = 'Editorial RM',
    ?int $year = 2005,
    ?string $coverUrl = null,
    ?string $reference = null,
): Candidate {
    return new Candidate(
        isbn: $isbn === null ? null : Isbn::from($isbn),
        title: $title,
        authors: $authors,
        publisher: $publisher,
        year: $year,
        edition: null,
        coverUrl: $coverUrl,
        description: null,
        source: Enums\Source::OpenLibrary,
        reference: $reference,
    );
}

/**
 * A Record built by hand, with the shape the one from any Source would have.
 *
 * @param  list<string>  $authors
 */
function recordOf(
    string $isbn,
    string $title = 'Pedro Páramo',
    array $authors = ['Juan Rulfo'],
    ?string $coverUrl = null,
    ?string $description = null,
): Record {
    return new Record(
        isbn: Isbn::from($isbn),
        title: $title,
        authors: $authors,
        publisher: 'Editorial RM',
        year: 2005,
        edition: null,
        coverUrl: $coverUrl,
        description: $description,
        source: Enums\Source::OpenLibrary,
    );
}

/**
 * An ISBN-13 with its correct check digit, for the test bursts: the Scan
 * rejects numbers that do not add up, so just any one will not do.
 */
function testIsbn(int $number): string
{
    $twelve = '978'.str_pad((string) $number, 9, '0', STR_PAD_LEFT);
    $sum = 0;

    foreach (str_split($twelve) as $position => $digit) {
        $sum += (int) $digit * ($position % 2 === 0 ? 1 : 3);
    }

    return $twelve.((10 - $sum % 10) % 10);
}
