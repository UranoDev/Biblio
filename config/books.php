<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where the shareable card of a Book lives
    |--------------------------------------------------------------------------
    |
    | The disk is whichever FILESYSTEM_DISK says and it is not named here: the
    | code always writes to the default disk (ADR-004), the same as the Covers
    | and the images of the Quotes. The only thing configured is the folder
    | inside that disk.
    |
    | What draws it is configured next to the Quotes, in `config/quotes.php`:
    | the renderer is one service with one key and one monthly quota, and it is
    | the same one whatever is being drawn (ADR-009, BIB-50).
    |
    */

    'share_image' => [
        'directory' => env('BOOK_SHARE_IMAGES_DIRECTORY', 'books'),
    ],

];
