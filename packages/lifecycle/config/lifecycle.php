<?php

return [

    'table' => 'state_transitions',

    /*
     * Strict mode: assigning the state attribute directly ($order->status = ...)
     * and saving throws DirectStateMutation. Every change must go through
     * transitionTo(), so it is validated, locked and recorded. Seeders and data
     * fixes opt out explicitly with Model::withoutLifecycleGuard(fn () => ...).
     */
    'strict' => true,

    /*
     * Record a history row (from = null) when a model is created, so a
     * record's timeline starts at its birth rather than at its first change.
     */
    'record_initial' => true,

];
