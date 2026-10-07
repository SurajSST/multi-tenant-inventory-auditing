<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Outbound notification delivery
    |--------------------------------------------------------------------------
    |
    | The database bell is written during the request. Email and browser push
    | are dispatched separately, after the response by default. Set this to
    | `database` when a supervised Laravel queue worker is available for
    | durable, retryable delivery.
    |
    */

    'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'deferred'),

    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),
];
