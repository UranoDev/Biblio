<?php

namespace App\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Whether the server of a Provider that runs on this machine is answering.
 *
 * A local Provider —Ollama— authenticates nothing, so asking whether its key is
 * in the environment answers a question nobody asked (BIB-27). What does
 * condition it is that something is listening at the URL it is configured with,
 * and that is what gets asked here.
 *
 * A server that is off is not a failure to report: it can be turned on later,
 * and the Settings screen keeps offering the Provider either way. So a refused
 * connection comes back as an answer —no— and never as an exception.
 *
 * Every URL is asked once per request. The Settings screen reads this from more
 * than one place while it renders, and drawing a screen is not a reason to
 * knock on the same door twice.
 */
final class LocalServer
{
    /** How long to wait: it is this machine, or one on the same network. */
    private const SECONDS = 2;

    /** @var array<string, bool> */
    private array $answers = [];

    public function responds(?string $url): bool
    {
        if ($url === null || trim($url) === '') {
            // Nothing configured to reach, so there is nowhere to ask.
            return false;
        }

        return $this->answers[$url] ??= self::ask($url);
    }

    private static function ask(string $url): bool
    {
        try {
            return Http::connectTimeout(self::SECONDS)
                ->timeout(self::SECONDS)
                ->get($url)
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }
}
