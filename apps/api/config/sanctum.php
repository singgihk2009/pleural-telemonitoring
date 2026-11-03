<?php

return [
    // Bearer tokens only: never fall back to a cookie/web session guard.
    'stateful' => [],
    'guard' => [],
    'expiration' => null, // Each token has a mandatory absolute expires_at.
    'token_prefix' => '',
];
