<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where the version is written down
    |--------------------------------------------------------------------------
    |
    | The file `scripts/release.ps1` regenerates and commits when it cuts a
    | release. Its most recent heading is the version that is running, and it is
    | a path and not a constant so that a test can point somewhere else without
    | touching the changelog of the repository.
    |
    */

    'changelog' => base_path('CHANGELOG.md'),

];
