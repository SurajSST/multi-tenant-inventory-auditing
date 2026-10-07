<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Outbound notification delivery
    |--------------------------------------------------------------------------
    |
    | The database bell is written during the request. Email and browser push
    | are dispatched to a durable database queue. A supervised worker or the
    | scheduled short-running worker must drain it for delivery and retries.
    |
    */

    'connection' => env('NOTIFICATION_QUEUE_CONNECTION', 'database'),

    'queue' => env('NOTIFICATION_QUEUE', 'notifications'),
];
