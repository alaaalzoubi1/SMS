<?php

return [
    /*
    |--------------------------------------------------------------------------
    | "Remember me" token lifetime (minutes)
    |--------------------------------------------------------------------------
    |
    | Normal tokens live JWT_TTL minutes (15 by default), which forces the
    | apps to log in again constantly. When a login request carries
    | `remember_me = true` the issued token lives this long instead.
    |
    */
    'ttl' => (int) env('JWT_REMEMBER_TTL', 43200), // 30 days
];
