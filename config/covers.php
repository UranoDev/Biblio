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

    /*
    |--------------------------------------------------------------------------
    | How small an image stops being a Cover
    |--------------------------------------------------------------------------
    |
    | The short side, in pixels, below which what arrived is refused: the
    | catalogs answer an ISBN they have no Cover for with a valid image —a 1×1
    | pixel, a thumbnail— and stored as it comes it looks like a bad Cover
    | instead of like the mistake it is (BIB-28).
    |
    | The bar is high on purpose. It also refuses real Covers that came too
    | small to read in the grid, and that costs nothing: a Book without a Cover
    | gets a generated one (ADR-008), which looks better than a 162 px scan
    | stretched to size.
    |
    */

    'minimum_side' => (int) env('COVERS_MINIMUM_SIDE', 300),

];
