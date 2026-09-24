<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where the images of the Quotes live
    |--------------------------------------------------------------------------
    |
    | The disk is whichever FILESYSTEM_DISK says and it is not named here: the
    | code always writes to the default disk (ADR-004), the same as the Covers.
    | The only thing configured is the folder inside that disk.
    |
    */

    'directory' => env('QUOTE_IMAGES_DIRECTORY', 'quotes'),

    /*
    |--------------------------------------------------------------------------
    | The renderer
    |--------------------------------------------------------------------------
    |
    | html2img turns the card into an image (ADR-009). It is a generator and not
    | a server: it answers with an address, the file is downloaded from there
    | and it is stored on our own disk, so the images already made survive the
    | day the service is swapped for a browser of our own.
    |
    | Without a key nothing is asked of it and the Quotes stay in the queue,
    | which is the same place the exhausted credits leave them.
    |
    */

    'renderer' => [
        'url' => env('HTML2IMG_URL', 'https://app.html2img.com/api/html'),

        'key' => env('HTML2IMG_API_KEY'),

        /*
         * A render is a browser starting up, laying out the page and taking the
         * picture, so the wait is measured in seconds and not in milliseconds.
         * It runs in the queue, where nobody is watching it.
         */
        'timeout' => (int) env('HTML2IMG_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | How long the waiting lasts
    |--------------------------------------------------------------------------
    |
    | A transient failure —a 429, a 503, a timeout— sends the render back to the
    | queue instead of failing it, waiting longer each time.
    |
    | The exhausted credits are not in here on purpose: they are not a wait.
    | They stop the queue, and it is picked up again from the screen once there
    | is something to pay with (ADR-009).
    |
    */

    'tries' => (int) env('QUOTE_IMAGES_TRIES', 4),

    'first_wait' => (int) env('QUOTE_IMAGES_FIRST_WAIT', 60),

    'longest_wait' => (int) env('QUOTE_IMAGES_LONGEST_WAIT', 900),

];
