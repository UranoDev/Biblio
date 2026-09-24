<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The pace at which the AI is asked
    |--------------------------------------------------------------------------
    |
    | How many requests a minute leave for the provider, counted in the shared
    | cache so that several workers add up to one rhythm instead of one each.
    | The default is the free tier's, which is the plan the library starts on;
    | with billing turned on this goes up and nothing else has to change.
    |
    */

    'requests_per_minute' => env('AI_REQUESTS_PER_MINUTE', 5),

    /*
    |--------------------------------------------------------------------------
    | How long the waiting lasts
    |--------------------------------------------------------------------------
    |
    | A transient failure —a 429, a 503, a timeout— sends the request back to
    | the queue instead of failing it. The wait doubles from `first_wait` up to
    | `longest_wait`: retrying an overloaded provider at once pushes in the same
    | direction as the cause.
    |
    | `give_up_after` is the cap, in hours. Something that has been retrying all
    | morning without getting through is not transient any more, and from there
    | on it is a failure that deserves to be seen.
    |
    */

    'first_wait' => env('AI_FIRST_WAIT', 60),

    'longest_wait' => env('AI_LONGEST_WAIT', 900),

    'give_up_after' => env('AI_GIVE_UP_AFTER', 6),

    /*
    |--------------------------------------------------------------------------
    | How long the mark of the queue is believed
    |--------------------------------------------------------------------------
    |
    | A Book is marked as waiting its turn when its request is queued, and the
    | mark is cleared when the answer arrives or when the job gives up. A worker
    | that is killed, a flushed queue or a deploy in the middle clears nothing,
    | and the mark is left claiming a wait that ended hours ago (BIB-46).
    |
    | Past this many hours the mark is not believed any more: the job cannot
    | still be alive, because `give_up_after` is its own cap. It is set to twice
    | that cap so that the queue has as long again to reach a Book at the back
    | of two hundred before its wait stops being read as one.
    |
    */

    'queue_mark_lasts' => env('AI_QUEUE_MARK_LASTS', 12),

];
