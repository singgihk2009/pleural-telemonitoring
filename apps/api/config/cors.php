<?php

return [
    // Development browser access only. This is not an authentication mechanism.
    'paths' => ['api/v1/*'],
    'allowed_methods' => ['GET', 'OPTIONS'],
    'allowed_origins' => ['http://localhost:5173'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Content-Type'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
