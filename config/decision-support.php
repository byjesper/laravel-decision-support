<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Run step budget
    |--------------------------------------------------------------------------
    |
    | A safety rail for the resumable interpreter: the maximum number of node
    | transitions a single run may take before it terminates with an `unknown`
    | outcome instead of looping forever.
    |
    */

    'max_steps' => 200,

];
