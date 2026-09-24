<?php

namespace App\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

/**
 * Telling apart what waiting fixes from what it does not.
 *
 * A 429 is not a failure: it is the provider saying "not now, come back in a
 * moment", and so are a 503, a gateway error and a request that timed out. They
 * deserve to go back to the queue with a delay.
 *
 * An invalid key, a model that does not exist and an answer that does not meet
 * the schema are failures. Waiting does not fix any of them, and dressing them
 * up as waiting hides the real error for hours.
 *
 * Anything unknown counts as a failure, not as a transient one. Getting it
 * wrong that way shows the error too early; getting it wrong the other way
 * leaves it invisible until the cap runs out.
 */
final class ProviderFailure
{
    /**
     * The statuses that mean "not now". 408 and 425 are the request that never
     * landed; 429 is the quota; the 5xx are the provider falling over, and the
     * ones above 520 are the CDNs in front of it saying the same thing.
     *
     * @var list<int>
     */
    private const TRANSIENT_STATUSES = [408, 425, 429, 500, 502, 503, 504, 520, 522, 524];

    /**
     * Whether the request is worth sending again after a wait.
     */
    public static function isTransient(Throwable $failure): bool
    {
        foreach (self::chain($failure) as $each) {
            // 402, and the providers that say it in words instead of in a
            // status. Paying fixes it; waiting does not.
            if ($each instanceof InsufficientCreditsException) {
                return false;
            }

            if ($each instanceof RateLimitedException
                || $each instanceof ProviderOverloadedException
                || $each instanceof ProviderConnectionException
                || $each instanceof ConnectionException) {
                return true;
            }

            // The SDK translates these into the exceptions above, and not on
            // every path: the trace that opened BIB-33 carried a bare
            // RequestException with its 429 inside.
            if ($each instanceof RequestException
                && in_array($each->response->status(), self::TRANSIENT_STATUSES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The exception and the ones it wraps. The status that decides this lives
     * at the bottom of the chain as often as at the top.
     *
     * @return list<Throwable>
     */
    private static function chain(Throwable $failure): array
    {
        $chain = [];

        for ($each = $failure; $each instanceof Throwable; $each = $each->getPrevious()) {
            $chain[] = $each;
        }

        return $chain;
    }
}
