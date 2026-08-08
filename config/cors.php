<?php

return [

    'paths' => [
        'api/admin/*',
        'api/hospital/*',
        'api/doctor/*',
        'api/landing',
        'api/legal/*',
        'api/contact-info',
        'api/provinces',
        'api/site-content/*',
    ],

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
