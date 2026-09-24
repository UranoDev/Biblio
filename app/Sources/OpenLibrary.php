<?php

namespace App\Sources;

use App\Domain\InvalidIsbn;
use App\Domain\Isbn;
use App\Enums;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The first Source of the ISBN cascade and the fallback in the TitleLookup
 * (ADR-002).
 *
 * Open Library keeps the description on the work, not on the edition: the
 * record at `/isbn/<isbn>.json` only points at it with `works[0].key`, so a
 * second request is needed. That does not introduce the concept of a work —we
 * still do not have one—; it is only where the data happens to be stored.
 */
final class OpenLibrary implements Source
{
    /** How many titles are asked for in a TitleLookup. */
    public const MAX_CANDIDATES = 20;

    /**
     * What `/search.json` gets asked for. Without `fields` it answers the whole
     * document of each work —hundreds of edition keys in a single array— and
     * without `editions.*` nothing at the edition level comes down, which is the
     * only thing that helps pick one.
     */
    public const SEARCH_FIELDS = 'key,title,author_name,cover_i,'
        .'editions,editions.key,editions.title,editions.author_name,'
        .'editions.publisher,editions.publish_date,editions.isbn,editions.cover_i';

    /** Where a TitleLookup is asked. */
    public const SEARCH_PATH = '/search.json';

    public function __construct(
        private readonly string $url,
        private readonly string $coversUrl,
        private readonly int $timeout,
    ) {}

    public function identifier(): Enums\Source
    {
        return Enums\Source::OpenLibrary;
    }

    /**
     * What a TitleLookup sends. It is public and static because the recorder of
     * `sources:record` asks for it: a recording of a request the adapter does
     * not make is not a recording of anything.
     *
     * @return array<string, mixed>
     */
    public static function searchParameters(string $title, ?string $author = null): array
    {
        $parameters = [
            'title' => trim($title),
            'limit' => self::MAX_CANDIDATES,
            'fields' => self::SEARCH_FIELDS,
        ];

        $author = $author === null ? '' : trim($author);

        if ($author !== '') {
            $parameters['author'] = $author;
        }

        return $parameters;
    }

    public function byIsbn(Isbn $isbn): LookupResult
    {
        try {
            $edition = $this->json("/isbn/{$isbn->value()}.json");
        } catch (SourceUnavailable $failure) {
            return LookupResult::unavailable($failure->getMessage());
        }

        $title = $this->title($edition);

        if ($edition === null || $title === null) {
            return LookupResult::withoutRecord();
        }

        try {
            $authors = $this->authors($edition);
        } catch (SourceUnavailable $failure) {
            return LookupResult::unavailable($failure->getMessage());
        }

        return LookupResult::withRecord(new Record(
            isbn: $isbn,
            title: $title,
            authors: $authors,
            publisher: $this->text($this->first($edition['publishers'] ?? null)),
            year: $this->year($edition['publish_date'] ?? null),
            edition: $this->text($edition['edition_name'] ?? null),
            coverUrl: $this->cover($edition),
            description: $this->description($edition),
            source: $this->identifier(),
        ));
    }

    /**
     * The fallback of the TitleLookup, and that is precisely why it is the
     * fallback: `/search.json` searches **works**, not editions, and of each work
     * it returns a single edition —whichever it picks— with no way to ask for
     * more. Checked on 2026-09-23 against the real catalog: «Pedro Páramo» by
     * Rulfo gives 6 works with 134 editions among them, and answers 6
     * Candidates; the one from the first work has no ISBN and the one from the
     * second is a Portuguese translation. For the TitleLookup that is not
     * enough —the screen exists to tell the paperback from the 2015 one— but it
     * works as a safety net when the other Source could not answer.
     */
    public function byTitle(string $title, ?string $author = null): Candidates
    {
        $title = trim($title);

        if ($title === '') {
            return Candidates::none();
        }

        try {
            $body = $this->json(self::SEARCH_PATH, self::searchParameters($title, $author));
        } catch (SourceUnavailable $failure) {
            return Candidates::unavailable($failure->getMessage());
        }

        $works = is_array($body['docs'] ?? null) ? $body['docs'] : [];
        $candidates = [];

        foreach ($works as $work) {
            $candidate = is_array($work) ? $this->candidate($work) : null;

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates === [] ? Candidates::none() : Candidates::of($candidates);
    }

    /**
     * The Candidate comes out of the edition the work brought inside; whatever
     * is missing there is filled in from the work, which at least has a title
     * and authors.
     *
     * @param  array<string, mixed>  $work
     */
    private function candidate(array $work): ?Candidate
    {
        $editions = is_array($work['editions'] ?? null) ? $work['editions'] : [];
        $documents = is_array($editions['docs'] ?? null) ? $editions['docs'] : [];
        $firstDocument = $this->first($documents);
        $edition = is_array($firstDocument) ? $firstDocument : [];

        $title = $this->text($edition['title'] ?? null) ?? $this->text($work['title'] ?? null);

        if ($title === null) {
            return null;
        }

        $cover = $edition['cover_i'] ?? $work['cover_i'] ?? null;

        return new Candidate(
            isbn: $this->editionIsbn($edition),
            title: $title,
            authors: $this->credited($edition) ?: $this->credited($work),
            publisher: $this->text($this->first($edition['publisher'] ?? null)),
            // The year of the work is not the year of the edition: an edition
            // without a date is shown without a year, not with the year of the
            // first publication.
            year: $this->year($this->first($edition['publish_date'] ?? null)),
            edition: null,
            coverUrl: is_int($cover) && $cover > 0 ? "{$this->coversUrl}/b/id/{$cover}-L.jpg" : null,
            // The Description lives on the work and each one costs another
            // request; in a list of twenty they are not worth paying, and
            // whoever captures does not see it there.
            description: null,
            source: $this->identifier(),
            reference: $this->text($edition['key'] ?? null) ?? $this->text($work['key'] ?? null),
        );
    }

    /**
     * The first ISBN that adds up, preferring the thirteen-digit one. Open
     * Library publishes them mixed together without marking which is which, and
     * some come out wrong.
     *
     * @param  array<string, mixed>  $edition
     */
    private function editionIsbn(array $edition): ?Isbn
    {
        $published = is_array($edition['isbn'] ?? null) ? $edition['isbn'] : [];
        $byLength = [];

        foreach ($published as $one) {
            $value = $this->text($one);

            if ($value !== null) {
                $byLength[strlen($value) >= 13 ? 0 : 1][] = $value;
            }
        }

        foreach (array_merge($byLength[0] ?? [], $byLength[1] ?? []) as $value) {
            try {
                return Isbn::from($value);
            } catch (InvalidIsbn) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return list<string>
     */
    private function credited(array $document): array
    {
        $names = is_array($document['author_name'] ?? null) ? $document['author_name'] : [];
        $authors = [];

        foreach ($names as $name) {
            $clean = $this->text($name);

            if ($clean !== null) {
                $authors[] = $clean;
            }
        }

        return $authors;
    }

    /**
     * The authors come as links, so each name is one more request. If any of
     * them cannot be made, the whole lookup is taken as unanswered: a Record
     * silently missing authors is worse than retrying.
     *
     * @param  array<string, mixed>  $edition
     * @return list<string>
     */
    private function authors(array $edition): array
    {
        $authors = [];
        $links = is_array($edition['authors'] ?? null) ? $edition['authors'] : [];

        foreach ($links as $link) {
            $key = is_array($link) ? $this->text($link['key'] ?? null) : null;

            if ($key === null) {
                continue;
            }

            $author = $this->json("{$key}.json");
            $name = $this->text($author['name'] ?? $author['personal_name'] ?? null);

            if ($name !== null) {
                $authors[] = $name;
            }
        }

        return $authors;
    }

    /**
     * The Description lives on the work. If that second request cannot be made,
     * the Record comes out without a Description instead of failing: a Record
     * without a Description is valid and the AI writes it later (ADR-007).
     *
     * On the work, `description` is sometimes a string and sometimes an object
     * with `value`.
     *
     * @param  array<string, mixed>  $edition
     */
    private function description(array $edition): ?string
    {
        $works = is_array($edition['works'] ?? null) ? $edition['works'] : [];
        $first = $this->first($works);
        $key = is_array($first) ? $this->text($first['key'] ?? null) : null;

        if ($key === null) {
            return null;
        }

        try {
            $work = $this->json("{$key}.json");
        } catch (SourceUnavailable) {
            return null;
        }

        $description = $work['description'] ?? null;

        if (is_array($description)) {
            $description = $description['value'] ?? null;
        }

        return $this->text($description);
    }

    /** @param  array<string, mixed>|null  $edition */
    private function title(?array $edition): ?string
    {
        if ($edition === null) {
            return null;
        }

        $title = $this->text($edition['title'] ?? null);
        $subtitle = $this->text($edition['subtitle'] ?? null);

        if ($title === null) {
            return null;
        }

        return $subtitle === null ? $title : "{$title}: {$subtitle}";
    }

    /** @param  array<string, mixed>  $edition */
    private function cover(array $edition): ?string
    {
        $covers = is_array($edition['covers'] ?? null) ? $edition['covers'] : [];

        foreach ($covers as $identifier) {
            // Open Library marks the covers it does not have with -1.
            if (is_int($identifier) && $identifier > 0) {
                return "{$this->coversUrl}/b/id/{$identifier}-L.jpg";
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

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>|null null when Open Library answers it does not know the resource.
     *
     * @throws SourceUnavailable
     */
    private function json(string $path, array $parameters = []): ?array
    {
        try {
            $response = Http::acceptJson()
                ->timeout($this->timeout)
                ->get($this->url.$path, $parameters);
        } catch (ConnectionException $failure) {
            throw new SourceUnavailable('Open Library did not answer: '.$failure->getMessage(), previous: $failure);
        }

        if ($response->notFound()) {
            return null;
        }

        if (! $response->successful()) {
            throw new SourceUnavailable("Open Library answered HTTP {$response->status()}.");
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new SourceUnavailable('Open Library answered something that is not JSON.');
        }

        return $body;
    }

    private function first(mixed $list): mixed
    {
        if (! is_array($list) || $list === []) {
            return null;
        }

        return reset($list);
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
