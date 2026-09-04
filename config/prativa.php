<?php

return [

    /*
    |---------------------------------------------------------------------------
    | School identity
    |---------------------------------------------------------------------------
    |
    | Shown in the page header and printed across the top of every Excel export.
    | The school name is also stored as an app_setting so a Super Admin can
    | change it without touching a file; this is the fallback.
    |
    */

    'school_name' => env('SCHOOL_NAME', 'Prativa Secondary School'),

    'system_name' => 'Internal Stock Auditing & Procurement',

    /*
    |---------------------------------------------------------------------------
    | Seed password
    |---------------------------------------------------------------------------
    |
    | Every seeded staff account starts with this and is forced to change it on
    | first sign-in. A password reset by the Super Admin sets it back to this.
    |
    */

    'seed_password' => env('SEED_PASSWORD', 'Prativa@2026'),

    /*
    |---------------------------------------------------------------------------
    | Attachments
    |---------------------------------------------------------------------------
    |
    | Scanned bills and delivery photos. These sit on a private disk and are
    | served through a signed, auth-gated route — never straight from public/.
    |
    */

    'attachments' => [
        'disk' => env('ATTACHMENT_DISK', 'local'),
        'directory' => 'attachments',
        'max_kb' => 8192,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],

];
