<?php

return [

    'table' => 'audit_logs',

    /*
     * Keys whose values are replaced with "[redacted]" anywhere inside
     * `metadata` or `changes` (matched case-insensitively, at any depth).
     */
    'redact' => [
        'password',
        'password_confirmation',
        'remember_token',
        'token',
        'secret',
        'api_key',
        'otp',
        'card_number',
        'cvv',
        'iban',
    ],

    /*
     * When mgamalhz/lifecycle is installed, record every state transition as a
     * `lifecycle.transitioned` entry, so the admin activity feed shows status
     * changes next to everything else without extra code.
     */
    'record_transitions' => true,

];
