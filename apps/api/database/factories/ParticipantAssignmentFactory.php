<?php

namespace Database\Factories;

use App\Models\Participant;
use App\Models\ParticipantAssignment;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class ParticipantAssignmentFactory extends Factory
{
    protected $model = ParticipantAssignment::class;

    public function definition(): array
    {
        return [
            'responden_id' => Participant::factory(),
            'pengguna_id' => StaffUser::factory(),
            'jenis' => 'PEMANTAU',
            'mulai_pada' => '2026-01-01 00:00:00.123456',
        ];
    }
}
