<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The cascade order
    |--------------------------------------------------------------------------
    |
    | They are queried one by one until one returns the Record. Changing the
    | order or removing a Source is done here and touches no screen (ADR-002).
    | The keys are the ones of the arrays below.
    |
    */

    'order' => ['open_library', 'google_books'],

    /*
    |--------------------------------------------------------------------------
    | The TitleLookup order
    |--------------------------------------------------------------------------
    |
    | It is the reverse of the one above, and not on a whim. A Candidate is an
    | edition (ADR-005) and has to bring Cover, authors, publisher and year,
    | because without those there is no way to tell the paperback from the 2015
    | one.
    |
    | Checked on 2026-09-23 against the real catalogs, searching for «Pedro
    | Páramo» by Rulfo:
    |
    | - Open Library searches WORKS, not editions. It returned 6 works adding up
    |   to 134 editions among them, and of each one it delivers ONE alone, of its
    |   own choosing: the one from the first work has no ISBN, and the one from
    |   the second is a Portuguese translation. `editions.limit` is ignored, so
    |   there is no way to ask for the rest; enumerating them would cost one
    |   request per work.
    | - Google Books searches VOLUMES, and each volume is an edition with its
    |   `industryIdentifiers`, its publisher and its year in the same response.
    |   It is the shape this screen needs, and in a single request.
    |
    | That is why Google Books goes first here and Open Library is left as the
    | safety net: it helps when the other one could not answer, which with the
    | title search is the likelier case —it is the operation that exhausts a
    | quota fastest.
    |
    | The author narrows without emptying, measured the same day against Open
    | Library: the title alone gives 48 works, with «Rulfo» it gives 6, and with
    | «juan rulfo» it gives the same 6 —neither the capitals nor the full name
    | break it.
    |
    */

    'title_order' => ['google_books', 'open_library'],

    'timeout' => (int) env('SOURCES_TIMEOUT', 10),

    'open_library' => [
        'url' => env('OPEN_LIBRARY_URL', 'https://openlibrary.org'),
        'covers_url' => env('OPEN_LIBRARY_COVERS_URL', 'https://covers.openlibrary.org'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Books
    |--------------------------------------------------------------------------
    |
    | Without a key the fallback works all the same, but it shares a global quota
    | that runs out and returns HTTP 429. That is reported as "the Source could
    | not answer", never as "it does not know it".
    |
    */

    'google_books' => [
        'url' => env('GOOGLE_BOOKS_URL', 'https://www.googleapis.com'),
        'key' => env('GOOGLE_BOOKS_API_KEY'),
    ],

];
