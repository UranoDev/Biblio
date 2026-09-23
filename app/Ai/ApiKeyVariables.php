<?php

namespace App\Ai;

/**
 * Which environment variable each Provider reads its API key from.
 *
 * The Settings screen has to name the variable that is missing, and the name is
 * not in the resolved configuration: by the time the configuration is an array,
 * an unset key is just null. So it is read from `config/ai.php` itself, which is
 * where it is written.
 *
 * Only the *name* of the variable is read here. No value of the environment is
 * ever read, and no key ever leaves the environment (ADR-006).
 */
final class ApiKeyVariables
{
    /**
     * The variable the Provider's key is read from, or null when the
     * configuration does not read it from one.
     */
    public static function of(string $provider): ?string
    {
        return self::all()[$provider] ?? null;
    }

    /**
     * The variable of every Provider in `config/ai.php`.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $file = config_path('ai.php');

        if (! is_file($file)) {
            return [];
        }

        /** @var array<string, mixed> $providers */
        $providers = config('ai.providers', []);

        $variables = [];
        $provider = null;

        foreach (file($file) ?: [] as $line) {
            if (preg_match("/^\s*'([\w-]+)' => \[/", $line, $matches) === 1) {
                // A nested array —the assumed role of Bedrock— is not a Provider.
                $provider = array_key_exists($matches[1], $providers) ? $matches[1] : $provider;

                continue;
            }

            if ($provider !== null && preg_match("/'key' => env\(\s*'([A-Z0-9_]+)'/", $line, $matches) === 1) {
                $variables[$provider] = $matches[1];
            }
        }

        return $variables;
    }
}
