<?php

namespace App\Covers;

use Illuminate\Support\Str;
use Illuminate\View\ComponentAttributeBag;

/**
 * The classes a Cover carries, whichever of the two it is.
 *
 * `$attributes->class()` adds to what the caller wrote instead of replacing it,
 * so a default `w-full` and a caller's `w-40` both end up on the element and the
 * winner is decided by their order in the compiled stylesheet, not by the order
 * they were written in. The default always won, which meant the component could
 * not be measured by whoever used it.
 *
 * The same Cover shows up in the sheet, in the grid of the Shelf, in the
 * Showcase and in the card of a Quote, each one with a size of its own, so the
 * width belongs to the caller. The default only fills in when the caller did
 * not bring one.
 */
final readonly class CoverAttributes
{
    /**
     * The classes of a Cover, with `w-full` left out when the caller set the
     * width itself.
     *
     * @return array<array-key, string|bool>
     */
    public static function classes(ComponentAttributeBag $attributes, string $shared): array
    {
        return [$shared, 'w-full' => ! self::setsWidth($attributes)];
    }

    /**
     * Whether any of the classes the caller passed sets the width.
     *
     * Only `w-*` and `size-*` do. `min-w-*` and `max-w-*` bound a width without
     * setting one, so they leave the default in place.
     */
    public static function setsWidth(ComponentAttributeBag $attributes): bool
    {
        $classes = preg_split('/\s+/', (string) $attributes->get('class', ''), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($classes as $class) {
            // What is left after the last colon is the utility: `md:w-40` and
            // `group-hover:w-40` set the width just as much as `w-40` does.
            $utility = Str::afterLast($class, ':');

            if (Str::startsWith($utility, ['w-', 'size-'])) {
                return true;
            }
        }

        return false;
    }
}
