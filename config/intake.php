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

];
