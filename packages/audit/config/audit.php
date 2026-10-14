<?php

/*
 * Additions on top of spatie/laravel-activitylog. Logging itself is
 * activitylog's: activity()->performedOn($order)->event('refunded')->log('...').
 */
return [

    /*
     * Keys whose values are replaced with "[redacted]" in an activity's
     * `properties` and `attribute_changes`, at any depth, before it is saved.
     */
    'redact' => [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'api_key',
        'otp',
        'card_number',
        'cvv',
        'iban',
    ],

    /*
     * Activities are facts about the past: updating or deleting one through
     * Eloquent throws. `php artisan activitylog:clean` still works (it deletes
     * with a query, not through model events).
     */
    'immutable' => true,

    /*
     * Log every lifecycle transition (event "transitioned", log "lifecycle"),
     * so the activity feed shows status changes next to everything else.
     */
    'record_transitions' => true,

];
