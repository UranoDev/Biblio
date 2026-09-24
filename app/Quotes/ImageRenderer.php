<?php

namespace App\Quotes;

use App\Enums\ShareFormat;

/**
 * What turns the card of a Quote into an image (ADR-009).
 *
 * This is the seam. Today behind it there is an external service, html2img;
 * tomorrow there can be a browser of our own on the VPS, and neither the card
 * nor the images already made get touched by the change. It is the same shape
 * the Sources have, and for the same reason.
 *
 * It takes the HTML of the card, already standing on its own —its stylesheet
 * inlined, its typefaces inlined, its Cover inlined—, and gives back the bytes
 * of a PNG. Nothing here knows about Quotes, about disks or about the queue.
 */
interface ImageRenderer
{
    /**
     * Whether there is anything to draw with right now.
     *
     * It is asked before every render because the answer is a deployment
     * question and not a failure: without a key configured there is nothing to
     * send the page to, and the Quotes stay waiting in the queue instead of
     * filling `failed_jobs` with the same sentence.
     */
    public function available(): bool;

    /**
     * Draws the page and returns the bytes of the image.
     *
     * @throws OutOfCredits when there is no quota left to draw it with
     * @throws RenderFailed for anything else the renderer answered
     */
    public function render(string $html, ShareFormat $format): string;
}
