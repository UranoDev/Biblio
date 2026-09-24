<?php

namespace App\Console\Commands;

use App\Domain\InvalidIsbn;
use App\Domain\Isbn;
use App\Sources\GoogleBooks;
use App\Sources\OpenLibrary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Asks a Source for one response and writes it to `tests/Fixtures/sources`
 * exactly as it arrived, byte for byte.
 *
 * It is run by hand when a new response is needed, and the file is committed.
 * It is not part of the suite and nothing calls it: a recording is only worth
 * anything if it is made against the live catalog, and the suite never goes out
 * to the internet.
 *
 * The request it sends is the one the adapter sends —the search parameters come
 * from the adapters themselves— because a recording of a request nobody makes
 * proves nothing about the adapter that reads it.
 */
final class RecordSourceResponse extends Command
{
    /** The Sources it knows how to ask. */
    private const OPEN_LIBRARY = 'open-library';

    private const GOOGLE_BOOKS = 'google-books';

    /**
     * What replaces anything that looks like a credential. It is deliberately
     * not a valid key: a recording with this inside has to fail loudly wherever
     * someone tries to use it as one.
     */
    public const REDACTED = 'REDACTED';

    protected $signature = 'sources:record
        {name : el nombre del archivo, sin la extensión}
        {--source=open-library : «open-library» o «google-books»}
        {--isbn= : el ISBN de la edición a pedir}
        {--resource= : una ruta de Open Library, como /works/OL274505W o /authors/OL27363A}
        {--title= : el título a buscar, para grabar el TitleLookup}
        {--author= : el autor que acompaña al título}
        {--without-key : no manda la llave de Google Books, para grabar lo que contesta la cuota compartida}
        {--expect=200 : el estado HTTP que se espera; con otro no se escribe nada}';

    protected $description = 'Graba la respuesta de una Fuente en tests/Fixtures/sources, tal cual llega';

    public function handle(): int
    {
        try {
            [$url, $parameters] = $this->request();
        } catch (Throwable $failure) {
            $this->components->error($failure->getMessage());

            return self::FAILURE;
        }

        $expected = (int) $this->option('expect');

        $this->components->info("Pidiendo {$url}");

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('sources.timeout', 10))
                ->get($url, $parameters);
        } catch (Throwable $failure) {
            $this->components->error('La Fuente no contestó: '.$failure->getMessage());

            return self::FAILURE;
        }

        if ($response->status() !== $expected) {
            $this->components->error(
                "Se esperaba HTTP {$expected} y contestó HTTP {$response->status()}; no se escribió nada."
            );

            return self::FAILURE;
        }

        $body = $this->withoutCredentials($response->body());

        if ($body === '') {
            $this->components->error('La Fuente contestó un cuerpo vacío; no se escribió nada.');

            return self::FAILURE;
        }

        $path = $this->path((string) $this->argument('name'));

        file_put_contents($path, $body);

        $this->components->info(sprintf(
            '%s — %s bytes, HTTP %d.',
            $path,
            number_format(strlen($body)),
            $response->status(),
        ));

        return self::SUCCESS;
    }

    /**
     * The URL and the parameters of the one request this run makes.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function request(): array
    {
        $source = (string) $this->option('source');

        return match ($source) {
            self::OPEN_LIBRARY => $this->openLibraryRequest(),
            self::GOOGLE_BOOKS => $this->googleBooksRequest(),
            default => throw new \InvalidArgumentException(
                "«{$source}» no es una Fuente conocida: usá «open-library» o «google-books»."
            ),
        };
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function openLibraryRequest(): array
    {
        $url = rtrim((string) config('sources.open_library.url'), '/');
        $resource = $this->text($this->option('resource'));
        $title = $this->text($this->option('title'));
        $isbn = $this->isbn();

        if ($isbn !== null) {
            return ["{$url}/isbn/{$isbn->value()}.json", []];
        }

        if ($resource !== null) {
            return [$url.'/'.trim($resource, '/').'.json', []];
        }

        if ($title !== null) {
            return [
                $url.OpenLibrary::SEARCH_PATH,
                OpenLibrary::searchParameters($title, $this->text($this->option('author'))),
            ];
        }

        throw new \InvalidArgumentException(
            'A Open Library hay que pedirle algo: --isbn, --resource o --title.'
        );
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function googleBooksRequest(): array
    {
        $url = rtrim((string) config('sources.google_books.url'), '/').GoogleBooks::VOLUMES_PATH;
        $isbn = $this->isbn();
        $title = $this->text($this->option('title'));

        $parameters = match (true) {
            $isbn !== null => GoogleBooks::isbnParameters($isbn),
            $title !== null => GoogleBooks::searchParameters($title, $this->text($this->option('author'))),
            default => throw new \InvalidArgumentException(
                'A Google Books hay que pedirle algo: --isbn o --title.'
            ),
        };

        $key = $this->key();

        if ($key !== null) {
            $parameters['key'] = $key;
        }

        return [$url, $parameters];
    }

    private function isbn(): ?Isbn
    {
        $value = $this->text($this->option('isbn'));

        if ($value === null) {
            return null;
        }

        try {
            return Isbn::from($value);
        } catch (InvalidIsbn $failure) {
            throw new \InvalidArgumentException("«{$value}» no es un ISBN: ".$failure->getMessage());
        }
    }

    /** The key of the environment, unless the run asked to go without it. */
    private function key(): ?string
    {
        if ($this->option('without-key') === true) {
            return null;
        }

        return $this->text(config('sources.google_books.key'));
    }

    /**
     * A response can echo the request back —Google puts the URL it answered in
     * `selfLink`— and with it the key. Nothing that looks like a credential
     * gets written to disk.
     */
    private function withoutCredentials(string $body): string
    {
        $key = $this->text(config('sources.google_books.key'));

        if ($key !== null) {
            $body = str_replace($key, self::REDACTED, $body);
        }

        // Whatever else has the shape of one, whether or not it is ours: a
        // Google key, and any `key=` in an echoed URL.
        $body = (string) preg_replace('/AIza[0-9A-Za-z_\-]{35}/', self::REDACTED, $body);

        return (string) preg_replace('/(["&?]key=)[^"&\s\\\\]{8,}/', '$1'.self::REDACTED, $body);
    }

    private function path(string $name): string
    {
        $clean = preg_replace('/[^a-z0-9\-]/', '', strtolower($name));

        if ($clean === null || $clean === '') {
            throw new \InvalidArgumentException("«{$name}» no sirve como nombre de archivo.");
        }

        return base_path("tests/Fixtures/sources/{$clean}.json");
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim($value);

        return $clean === '' ? null : $clean;
    }
}
