<?php

namespace App\Ai;

use App\Enums\ModelRole;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Providers\TextProvider;
use LogicException;

/**
 * The Providers the Settings screen can offer: the ones that write text.
 *
 * The list is not written here. It is every Provider configured in
 * `config/ai.php` that the SDK answers with a text provider, so a Provider that
 * only makes embeddings or transcribes audio never reaches the screen, and one
 * the SDK adds later shows up without anybody editing this file.
 */
final class Providers
{
    /**
     * The Providers that run on the machine this is installed on.
     *
     * Ollama is the only one on the list that does not talk to an outside
     * service: its server listens on the local network and authenticates
     * nothing, so it reads no key (BIB-27). `OLLAMA_API_KEY` exists in the SDK
     * for putting it behind an authenticating proxy, or for Ollama Cloud, and
     * neither of those is what happens when somebody runs it at home.
     */
    private const LOCAL = ['ollama'];

    /** @return list<Provider> */
    public static function all(): array
    {
        /** @var array<string, array<string, mixed>> $configured */
        $configured = config('ai.providers', []);

        $providers = [];

        foreach (array_keys($configured) as $name) {
            $text = self::textProviderNamed($name);

            if (! $text instanceof TextProvider) {
                continue;
            }

            $local = in_array($name, self::LOCAL, true);

            $providers[] = new Provider(
                name: $name,
                label: self::labelOf($name),
                // A Provider that runs here waits for no variable, so naming
                // one would be pointing at something that is not missing.
                keyVariable: $local ? null : ApiKeyVariables::of($name),
                hasKey: filled($configured[$name]['key'] ?? null),
                models: self::modelsOf($text),
                local: $local,
                url: $local ? self::urlOf($configured[$name]) : null,
            );
        }

        return $providers;
    }

    /**
     * The Providers that can be chosen: their key is in the environment, or
     * they run here and read none.
     *
     * @return list<Provider>
     */
    public static function choosable(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (Provider $provider): bool => $provider->canBeChosen(),
        ));
    }

    /**
     * The Providers that cannot be chosen yet, for the screen to name the
     * variable each one is waiting for. The ones that run here are never among
     * them: they are not waiting for anything.
     *
     * @return list<Provider>
     */
    public static function waitingForKey(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (Provider $provider): bool => ! $provider->canBeChosen(),
        ));
    }

    public static function find(string $name): ?Provider
    {
        foreach (self::all() as $provider) {
            if ($provider->name === $name) {
                return $provider;
            }
        }

        return null;
    }

    /** How the Provider is called on the screen. */
    public static function labelOf(string $name): string
    {
        // How each one spells itself, where capitalizing the configuration key
        // does not get there. Anything else is the key capitalized, so a
        // Provider that is not listed still reads as a name.
        $spelling = [
            'azure' => 'Azure OpenAI',
            'bedrock' => 'Amazon Bedrock',
            'deepseek' => 'DeepSeek',
            'openai' => 'OpenAI',
            'openai-compatible' => 'Compatible con OpenAI',
            'openrouter' => 'OpenRouter',
            'xai' => 'xAI',
        ];

        return $spelling[$name] ?? Str::headline($name);
    }

    /**
     * Where the server of a Provider that runs here is reached, as
     * `config/ai.php` has it, or null when nothing is configured.
     *
     * @param  array<string, mixed>  $configuration
     */
    private static function urlOf(array $configuration): ?string
    {
        $url = $configuration['url'] ?? null;

        return is_string($url) && trim($url) !== '' ? trim($url) : null;
    }

    /**
     * The Provider by that name, when it writes text.
     */
    private static function textProviderNamed(string $name): ?TextProvider
    {
        try {
            return Ai::textProvider($name);
        } catch (LogicException) {
            // The Provider is configured but does not generate text: it embeds,
            // it reranks or it transcribes. It has nothing to do here.
            return null;
        }
    }

    /**
     * The AiModels the Provider names, each one with what it is named for.
     *
     * @return list<SuggestedAiModel>
     */
    private static function modelsOf(TextProvider $provider): array
    {
        $roles = [];

        foreach (ModelRole::cases() as $role) {
            try {
                $model = match ($role) {
                    ModelRole::Default => $provider->defaultTextModel(),
                    ModelRole::Cheapest => $provider->cheapestTextModel(),
                    ModelRole::Smartest => $provider->smartestTextModel(),
                };
            } catch (InvalidArgumentException) {
                // The Provider names no model for this role: one that talks to
                // whatever is behind a URL cannot know what is there. The model
                // then gets typed in by hand on the screen.
                continue;
            }

            $roles[$model][] = $role;
        }

        $models = [];

        foreach ($roles as $model => $itsRoles) {
            $models[] = new SuggestedAiModel((string) $model, $itsRoles);
        }

        return $models;
    }
}
