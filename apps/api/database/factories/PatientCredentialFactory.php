<?php

namespace Database\Factories;

use App\Models\Participant;
use App\Models\PatientCredential;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated data; never seed a research deployment. */
class PatientCredentialFactory extends Factory
{
    protected $model = PatientCredential::class;

    public function definition(): array
    {
        return [
            'responden_id' => Participant::factory(),
            'pin_hash' => '012345',
            'issued_by' => StaffUser::factory(),
            'issued_at' => now(),
        ];
    }
}
