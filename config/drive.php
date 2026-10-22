<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Photographs from Google Drive
    |--------------------------------------------------------------------------
    |
    | A photograph of a Cover uploaded to the Drive folder becomes a CoverDraft
    | (BIB-74): it is read the way ManualEntry reads one, and waits there for the
    | owner to review it and register the Book.
    |
    | The application signs in as a service account. Its JSON key lives outside
    | the repository and outside the public folder, and the folder is shared
    | with the account's address as Editor: without Editor it can read the
    | photographs but cannot move them to the processed folder.
    |
    | While any of the three is empty nothing is imported, and the Book can still
    | be registered by hand.
    |
    */

    'credentials' => env('GOOGLE_DRIVE_CREDENTIALS'),

    'folder' => env('GOOGLE_DRIVE_FOLDER_ID'),

    'processed_folder' => env('GOOGLE_DRIVE_PROCESSED_FOLDER_ID'),

    /*
    | The longest side, in pixels, of the version Drive renders when the
    | original cannot be used as it is: a HEIC from an iPhone, which GD does not
    | read, or a photograph heavier than a Cover may weigh.
    */

    'thumbnail_size' => (int) env('GOOGLE_DRIVE_THUMBNAIL_SIZE', 1600),

    'timeout' => (int) env('GOOGLE_DRIVE_TIMEOUT', 30),

];
