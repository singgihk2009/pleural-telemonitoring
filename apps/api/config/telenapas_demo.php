<?php

return [
    'enabled' => env('TELENAPAS_DEMO_ENABLED', false),
    // File path only. Never put a password in source, an argument or console output.
    'password_file' => env('TELENAPAS_DEMO_PASSWORD_FILE'),
];
