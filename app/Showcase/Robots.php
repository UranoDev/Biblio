<?php

namespace App\Showcase;

/**
 * What `robots.txt` says about one address (BIB-37).
 *
 * The file is written by hand, and that is on purpose: it is read before
 * anything else on the site and by machines that do not run PHP well, so it
 * stays a static file that works even with the application down. What cannot
 * stay by hand is the certainty that it still covers the back office — a screen
 * added tomorrow does not edit it, and nothing on the screen would say so.
 *
 * So this class reads the file and answers the one question a test can then ask
 * of every route in the application: is this address allowed through?
 *
 * The rule it applies is the one every crawler applies: of all the rules that
 * match the beginning of the address, the longest one wins, and a tie goes to
 * `Allow`. That is what makes `/book/1` public and `/books/1` closed with the
 * two of them one letter apart.
 */
final readonly class Robots
{
    /**
     * @param  list<array{allow: bool, path: string}>  $rules
     */
    private function __construct(private array $rules) {}

    /** The file the site serves. */
    public static function served(): self
    {
        return self::parse((string) file_get_contents(public_path('robots.txt')));
    }

    public static function parse(string $contents): self
    {
        $rules = [];

        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim(explode('#', $line, 2)[0]);

            if (! preg_match('/^(allow|disallow)\s*:\s*(\S*)$/i', $line, $found)) {
                continue;
            }

            // An empty `Disallow:` is the way the standard says «nothing is
            // closed». It is a rule about no address, so it matches none.
            if ($found[2] === '') {
                continue;
            }

            $rules[] = ['allow' => strtolower($found[1]) === 'allow', 'path' => $found[2]];
        }

        return new self($rules);
    }

    /** Whether a crawler may ask for this address. */
    public function allows(string $path): bool
    {
        $winner = null;

        foreach ($this->rules as $rule) {
            if (! str_starts_with($path, $rule['path'])) {
                continue;
            }

            $longer = $winner === null || strlen($rule['path']) > strlen($winner['path']);
            $tie = $winner !== null && strlen($rule['path']) === strlen($winner['path']);

            if ($longer || ($tie && $rule['allow'])) {
                $winner = $rule;
            }
        }

        // An address no rule names is open. That is the default of the
        // standard, and the reason this file lists what it closes.
        return $winner === null || $winner['allow'];
    }
}
