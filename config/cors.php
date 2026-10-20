<?php

return [

    // `paths` = the routes that GET CORS headers. A path missing from this
    // list gets no CORS headers at all, and the browser then BLOCKS it (it is
    // not "open"). So every API route is covered here; who may call them is
    // controlled by the allowed origins below (only muafa-sy.com sites).
    // Mobile apps are not affected by CORS either way.
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [],

    'allowed_origins_patterns' => [
        '#^https?://(www\.)?muafa-sy\.com$#',
        '#^https?://[a-z0-9-]+\.muafa-sy\.com$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
