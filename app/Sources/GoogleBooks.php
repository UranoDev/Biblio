<?php

namespace App\Sources;

use App\Domain\InvalidIsbn;
use App\Domain\Isbn;
use App\Enums;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The fallback of the ISBN cascade and the first one in the TitleLookup
 * (ADR-002), and the only one of the two that brings the Description in the
 * same request.
 *
 * The key is read from the environment. Without a key the adapter still works,
 * but it is exposed to the shared quota; when that one runs out Google answers
 * HTTP 429, and that translates into "the Source could not answer", never into
 * "it does not know it".
 */
final class GoogleBooks implements Source
{
    /** How many editions are asked for in a TitleLookup. */
    public const MAX_CANDIDATES = 20;

    /** Where the searches and the volumes of this Source are asked for. */
    public const VOLUMES_PATH = '/books/v1/volumes';

    /** Where the ISBN lookup that does not go through the search is asked for. */
    public const LOOKUP_PATH = '/books';

    public function __construct(
        private readonly string $url,
        private readonly ?string $key,
        private readonly int $timeout,
        private readonly string $lookupUrl = 'https://books.google.com',
    ) {}

    public function identifier(): Enums\Source
    {
        return Enums\Source::GoogleBooks;
    }

    /**
     * What an ISBN lookup asks for. Google has no route per ISBN: it is one
     * more term of the same search.
     *
     * @return array<string, mixed>
     */
    public static function isbnParameters(Isbn $isbn): array
    {
        return ['q' => "isbn:{$isbn->value()}"];
    }

    /**
     * What the ISBN lookup of books.google.com asks for: the same one behind
     * the "view on Google Books" buttons. It answers with the id of the volume
     * that carries the ISBN, and it answers for volumes the search has not
     * indexed yet —a book that came out weeks ago is the usual case.
     *
     * @return array<string, mixed>
     */
    public static function lookupParameters(Isbn $isbn): array
    {
        return ['bibkeys' => "ISBN:{$isbn->value()}", 'jscmd' => 'viewapi'];
    }

    /** Where one volume is asked for by its id. */
    public static function volumePath(string $id): string
    {
        return self::VOLUMES_PATH.'/'.rawurlencode($id);
    }

    /**
     * What a TitleLookup sends, with the key left out —`query()` adds it. It is
     * public and static because the recorder of `sources:record` asks for it: a
     * recording of a request the adapter does not make is not a recording of
     * anything.
     *
     * @return array<string, mixed>
     */
    public static function searchParameters(string $title, ?string $author = null): array
    {
        $query = 'intitle:'.self::quoted(trim($title));
        $author = $author === null ? '' : trim($author);

        if ($author !== '') {
            $query .= ' inauthor:'.self::quoted($author);
        }

        return [
            'q' => $query,
            'maxResults' => self::MAX_CANDIDATES,
            'printType' => 'books',
        ];
    }

    public function byIsbn(Isbn $isbn): LookupResult
    {
        try {
            $volume = $this->firstVolume($this->query(self::isbnParameters($isbn)))
                ?? $this->volumeFromLookup($isbn);
        } catch (SourceUnavailable $failure) {
            return LookupResult::unavailable($failure->getMessage());
        }

        $info = $this->info($volume);
        $title = $info === null ? null : $this->title($info);

        if ($info === null || $title === null) {
            return LookupResult::withoutRecord();
        }

        return LookupResult::withRecord(new Record(
            isbn: $isbn,
            title: $title,
            authors: $this->authors($info),
            publisher: $this->text($info['publisher'] ?? null),
            year: $this->year($info['publishedDate'] ?? null),
            // Google Books does not publish the name of the edition.
            edition: null,
            coverUrl: $this->cover($info),
            description: $this->description($info),
            source: $this->identifier(),
        ));
    }

    /**
     * `intitle:` and `inauthor:` pin each term to its field. Without that, the
     * author ends up bringing in everything that mentions them in the
     * Description, which is pure noise when the point is to pick an edition.
     */
    public function byTitle(string $title, ?string $author = null): Candidates
    {
        $title = trim($title);

        if ($title === '') {
            return Candidates::none();
        }

        try {
            $body = $this->query(self::searchParameters($title, $author));
        } catch (SourceUnavailable $failure) {
            return Candidates::unavailable($failure->getMessage());
        }

        $items = $body['items'] ?? null;

        if (! is_array($items) || $items === []) {
            return Candidates::none();
        }

        $candidates = [];

        foreach ($items as $item) {
            $candidate = is_array($item) ? $this->candidate($item) : null;

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates === [] ? Candidates::none() : Candidates::of($candidates);
    }

    /**
     * Each Google volume is an edition, with its own publisher, year and
     * identifiers: exactly what is needed to tell two Candidates of the same
     * title apart.
     *
     * @param  array<string, mixed>  $volume
     */
    private function candidate(array $volume): ?Candidate
    {
        $info = $this->info($volume);
        $title = $info === null ? null : $this->title($info);

        if ($info === null || $title === null) {
            return null;
        }

        return new Candidate(
            isbn: $this->isbn($info),
            title: $title,
            authors: $this->authors($info),
            publisher: $this->text($info['publisher'] ?? null),
            year: $this->year($info['publishedDate'] ?? null),
            edition: null,
            coverUrl: $this->cover($info),
            description: $this->description($info),
            source: $this->identifier(),
            reference: $this->text($volume['id'] ?? null),
        );
    }

    /**
     * The ISBN of a volume, or null when it publishes none or the one it
     * publishes does not add up. Google puts everything into
     * `industryIdentifiers` —ISSN, internal keys, ISBNs with a wrong check
     * digit— and a Candidate without an ISBN is valid: better that than storing
     * a number that identifies nothing.
     *
     * @param  array<string, mixed>  $info
     */
    private function isbn(array $info): ?Isbn
    {
        return $this->isbns($info)[0] ?? null;
    }

    /**
     * Every valid ISBN the volume publishes, the thirteen-digit ones first:
     * that is the one stored normalized.
     *
     * @param  array<string, mixed>  $info
     * @return list<Isbn>
     */
    private function isbns(array $info): array
    {
        $identifiers = is_array($info['industryIdentifiers'] ?? null)
            ? $info['industryIdentifiers']
            : [];

        $isbns = [];

        foreach (['ISBN_13', 'ISBN_10'] as $type) {
            foreach ($identifiers as $identifier) {
                if (! is_array($identifier) || ($identifier['type'] ?? null) !== $type) {
                    continue;
                }

                $value = $this->text($identifier['identifier'] ?? null);

                if ($value === null) {
                    continue;
                }

                try {
                    $isbns[] = Isbn::from($value);
                } catch (InvalidIsbn) {
                    continue;
                }
            }
        }

        return $isbns;
    }

    /**
     * The volume the ISBN lookup points to, when the search found none.
     *
     * The search of Google Books takes weeks to index a new book, while the
     * volume already exists and carries its ISBN. The lookup gets its id, and
     * the volume is read by that id. It is only kept when it publishes the same
     * ISBN that was asked for: the lookup answers with some volume even for
     * ISBNs that barely identify anything.
     *
     * @return array<string, mixed>|null
     *
     * @throws SourceUnavailable
     */
    private function volumeFromLookup(Isbn $isbn): ?array
    {
        $id = $this->lookedUpVolumeId($isbn);

        if ($id === null) {
            return null;
        }

        $volume = $this->get($this->url.self::volumePath($id), [], missingIsEmpty: true);
        $info = $volume === null ? null : $this->info($volume);

        if ($info === null) {
            return null;
        }

        foreach ($this->isbns($info) as $published) {
            if ($published->value() === $isbn->value()) {
                return $volume;
            }
        }

        return null;
    }

    /**
     * The id of the volume, read from the lookup. It does not answer JSON but
     * a line of JavaScript —`var _GBSBookInfo = {…};`— with the JSON inside.
     *
     * @throws SourceUnavailable
     */
    private function lookedUpVolumeId(Isbn $isbn): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get($this->lookupUrl.self::LOOKUP_PATH, self::lookupParameters($isbn));
        } catch (Throwable $failure) {
            throw new SourceUnavailable('Google Books did not answer the ISBN lookup: '.$failure->getMessage(), previous: $failure);
        }

        if (! $response->successful()) {
            throw new SourceUnavailable("Google Books answered HTTP {$response->status()} to the ISBN lookup.");
        }

        $body = $response->body();
        $start = strpos($body, '{');
        $end = strrpos($body, '}');

        if ($start === false || $end === false) {
            return null;
        }

        $books = json_decode(substr($body, $start, $end - $start + 1), true);
        $infoUrl = is_array($books) ? ($books["ISBN:{$isbn->value()}"]['info_url'] ?? null) : null;

        if (! is_string($infoUrl)) {
            return null;
        }

        parse_str((string) parse_url($infoUrl, PHP_URL_QUERY), $query);

        return $this->text($query['id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<mixed>
     *
     * @throws SourceUnavailable
     */
    private function query(array $parameters): array
    {
        return $this->get($this->url.self::VOLUMES_PATH, $parameters) ?? [];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  bool  $missingIsEmpty  a 404 means "there is no such volume", not a failure
     * @return array<mixed>|null
     *
     * @throws SourceUnavailable
     */
    private function get(string $url, array $parameters, bool $missingIsEmpty = false): ?array
    {
        if ($this->hasKey()) {
            $parameters['key'] = $this->key;
        }

        try {
            $response = Http::acceptJson()
                ->timeout($this->timeout)
                ->get($url, $parameters);
        } catch (Throwable $failure) {
            throw new SourceUnavailable('Google Books did not answer: '.$failure->getMessage(), previous: $failure);
        }

        if ($missingIsEmpty && $response->status() === 404) {
            return null;
        }

        if ($response->status() === 429) {
            throw new SourceUnavailable($this->hasKey()
                ? 'Google Books ran out of quota on the configured key (HTTP 429).'
                : 'Google Books ran out of the shared quota (HTTP 429); no key is configured.');
        }

        if (! $response->successful()) {
            throw new SourceUnavailable("Google Books answered HTTP {$response->status()}.");
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new SourceUnavailable('Google Books answered something that is not JSON.');
        }

        return $body;
    }

    /**
     * @param  array<mixed>  $body
     * @return array<string, mixed>|null
     */
    private function firstVolume(array $body): ?array
    {
        $items = $body['items'] ?? null;

        if (! is_array($items) || $items === []) {
            return null;
        }

        $first = reset($items);

        return is_array($first) ? $first : null;
    }

    /**
     * @param  array<string, mixed>|null  $volume
     * @return array<string, mixed>|null
     */
    private function info(?array $volume): ?array
    {
        $info = $volume === null ? null : ($volume['volumeInfo'] ?? null);

        return is_array($info) ? $info : null;
    }

    /** @param  array<string, mixed>  $info */
    private function title(array $info): ?string
    {
        $title = $this->text($info['title'] ?? null);
        $subtitle = $this->text($info['subtitle'] ?? null);

        if ($title === null) {
            return null;
        }

        return $subtitle === null ? $title : "{$title}: {$subtitle}";
    }

    /**
     * @param  array<string, mixed>  $info
     * @return list<string>
     */
    private function authors(array $info): array
    {
        $credited = is_array($info['authors'] ?? null) ? $info['authors'] : [];
        $authors = [];

        foreach ($credited as $one) {
            $name = $this->text($one);

            if ($name !== null) {
                $authors[] = $name;
            }
        }

        return $authors;
    }

    /**
     * Google delivers the description with HTML tags. The Record stores text, so
     * it is cleaned up here and not on every screen that shows it.
     *
     * @param  array<string, mixed>  $info
     */
    private function description(array $info): ?string
    {
        $description = $this->text($info['description'] ?? null);

        if ($description === null) {
            return null;
        }

        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $description));

        return $this->text(html_entity_decode($text, ENT_QUOTES | ENT_HTML5));
    }

    /** @param  array<string, mixed>  $info */
    private function cover(array $info): ?string
    {
        $images = is_array($info['imageLinks'] ?? null) ? $info['imageLinks'] : [];

        foreach (['extraLarge', 'large', 'medium', 'small', 'thumbnail', 'smallThumbnail'] as $size) {
            $url = $this->text($images[$size] ?? null);

            if ($url !== null) {
                return str_replace('http://', 'https://', $url);
            }
        }

        return null;
    }

    private function year(mixed $published): ?int
    {
        $text = $this->text($published);

        if ($text === null || preg_match('/\b(\d{4})\b/', $text, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /** The quotes keep the phrase together; the ones the text carries are in the way. */
    private static function quoted(string $term): string
    {
        return '"'.str_replace('"', '', $term).'"';
    }

    private function hasKey(): bool
    {
        return $this->key !== null && trim($this->key) !== '';
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
