<?php

use App\Models\StaffUser;

return [
    'defaults' => ['guard' => 'web', 'passwords' => 'staff'],
    'guards' => ['web' => ['driver' => 'session', 'provider' => 'staff']],
    'providers' => ['staff' => ['driver' => 'eloquent', 'model' => StaffUser::class]],
];
