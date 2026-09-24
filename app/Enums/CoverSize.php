<?php

namespace App\Enums;

/**
 * Which of the two stored versions of a Cover is being asked for.
 *
 * The original is kept exactly as it arrived, for the Book sheet; the
 * scaled-down version is what the grids ask for, because a grid of two hundred
 * originals is a slow page (ADR-004).
 *
 * The value travels in the URL, so it is also what makes the address of the
 * thumbnail different from the address of the original.
 */
enum CoverSize: string
{
    case Full = 'full';
    case Thumbnail = 'thumbnail';
}
