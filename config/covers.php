<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where the Covers live
    |--------------------------------------------------------------------------
    |
    | The disk is whichever FILESYSTEM_DISK says and it is not named here: the
    | code always writes to the default disk (ADR-004). The only thing configured
    | is the folder inside that disk.
    |
    */

    'directory' => env('COVERS_DIRECTORY', 'covers'),

    /*
    |--------------------------------------------------------------------------
    | The scaled-down version
    |--------------------------------------------------------------------------
    |
    | The width, in pixels, of the thumbnail the grids use. The original is
    | stored exactly as it arrived.
    |
    */

    'thumbnail_width' => (int) env('COVERS_THUMBNAIL_WIDTH', 320),

    /*
    |--------------------------------------------------------------------------
    | Download limits
    |--------------------------------------------------------------------------
    |
    | A download taking too long does not hold up registering the Book: it is cut
    | short and left to be retried.
    |
    */

    'timeout' => (int) env('COVERS_TIMEOUT', 10),

    'max_weight' => (int) env('COVERS_MAX_WEIGHT', 5 * 1024 * 1024),

];
