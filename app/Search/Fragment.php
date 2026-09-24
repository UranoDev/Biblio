<?php

namespace App\Search;

/**
 * A piece of a Snippet: some text, and whether it is one of the words that
 * were searched.
 *
 * The Snippet hands the screen these and never a string with markup in it: the
 * text comes out of a Quote somebody typed, and a class that returned it with
 * its own `<mark>` around it would be a class that decides what the browser
 * runs.
 */
final readonly class Fragment
{
    public function __construct(
        public string $text,
        public bool $found,
    ) {}
}
