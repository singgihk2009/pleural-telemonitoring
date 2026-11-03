<?php

return [
    // Operational security settings, not clinical research rules.
    'token_minutes' => max(5, min(1440, (int) env('AUTH_TOKEN_TTL_MINUTES', 60))),
    'blinded_assessment' => env('AUTH_BLINDED_ASSESSMENT', true),
    'grant_max_hours' => max(1, min(24, (int) env('AUTH_GRANT_MAX_HOURS', 24))),
];
