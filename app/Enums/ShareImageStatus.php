<?php

namespace App\Enums;

/**
 * Where the two shareable images of one Quote stand (ADR-009).
 *
 * Three of the cases look identical from outside —there is no image to hand
 * over— and are not the same thing: one is on its way, one the renderer refused,
 * and one was never asked for at all. Saying «todavía no está lista» of the
 * third promises something nobody put in the queue, which is what every Quote
 * stored before the queue existed ended up announcing (BIB-48).
 *
 * `Queued` rests on the mark written when the render was requested and on
 * nothing else, the same way a Book's wait does (BIB-46).
 */
enum ShareImageStatus: string
{
    /** Drawn and stored: there is a file to download. */
    case Ready = 'ready';

    /** The mark of the queue is on, and nothing has come back yet. */
    case Queued = 'queued';

    /** The renderer gave up on it for a reason waiting does not fix. */
    case Failed = 'failed';

    /** Nothing was ever queued for it, so nothing is on its way. */
    case NotAsked = 'not_asked';

    /**
     * Longer than the canvas holds. There is no smaller legible size left, so
     * this one is never getting an image (ADR-009).
     */
    case TooLong = 'too_long';
}
