<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registering on idle
    |--------------------------------------------------------------------------
    |
    | Almost every Scanner sends Enter at the end of the code, and those register
    | with the Enter. The ones that do not send it register on their own when the
    | field stops receiving keystrokes for these milliseconds (ADR-001). Pressing
    | a button per book is never asked for: that is the step that gets paid
    | twenty times over.
    |
    | A Scanner types the thirteen digits in under a hundred milliseconds, so
    | this wait does not cut a code in half.
    |
    */

    'idle' => (int) env('INTAKE_IDLE_MS', 600),

    /*
    |--------------------------------------------------------------------------
    | Reading the photograph of a Cover
    |--------------------------------------------------------------------------
    |
    | ManualEntry can start from a photograph of the Cover: an AI reads what is
    | printed on it and fills in the form (BIB-73). The model has to accept
    | images. Checked on 2026-10-19: `deepseek-chat` read «La estrella de
    | Ratner, Don DeLillo» off a cover. Its key is DEEPSEEK_API_KEY, the same
    | one the provider list in config/ai.php reads.
    |
    */

    'cover_reader' => [
        'provider' => env('COVER_READER_PROVIDER', 'deepseek'),
        'model' => env('COVER_READER_MODEL', 'deepseek-chat'),
        'timeout' => (int) env('COVER_READER_TIMEOUT', 60),
    ],

];
