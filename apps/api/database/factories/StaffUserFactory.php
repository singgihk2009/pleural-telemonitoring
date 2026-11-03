<?php

namespace Database\Factories;

use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class StaffUserFactory extends Factory
{
    protected $model = StaffUser::class;

    public function definition(): array
    {
        return [
            'nama' => 'SYNTHETIC TEST STAFF',
            'email' => 'synthetic-'.Str::uuid().'@example.invalid',
            'password_hash' => 'synthetic-test-only-not-login',
            'status' => 'AKTIF',
        ];
    }
}
