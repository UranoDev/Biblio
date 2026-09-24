<?php

namespace App\Enums;

/**
 * How big the Quote is drawn on its card.
 *
 * A Quote of six words and one of forty go on the same card, and one size does
 * not serve both: six words at the size of forty float in the middle of the
 * paper, and forty at the size of six push everything else out of the frame.
 * The size is not typed in by hand anywhere — it is read off the Quote itself
 * by `QuoteCard`.
 *
 * The three of them are the same type at three sizes, not three designs: the
 * face, the Rule and the air are the ones the direction sets.
 */
enum QuoteScale: string
{
    /** Up to a couple of lines: it gets the room it is not using. */
    case Large = 'large';

    /** The size of the direction, and where most Quotes land. */
    case Regular = 'regular';

    /** Long enough that the card would run off the screen at the other sizes. */
    case Small = 'small';
}
