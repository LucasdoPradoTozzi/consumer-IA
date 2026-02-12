<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Job Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for job processing pipeline. All values are loaded from .env
    | and should be accessed via config('processing.key') in services.
    |
    */

    'score_threshold' => env('PROCESSING_SCORE_THRESHOLD', 70),

];
