<?php

namespace Database\Factories;

use App\Models\Participant;
use App\Models\ParticipantContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class ParticipantContactFactory extends Factory
{
    protected $model = ParticipantContact::class;

    public function definition(): array
    {
        return [
            'responden_id' => Participant::factory(),
            'inisial_encrypted' => 'SYNTHETIC',
            'no_hp_pasien_encrypted' => 'SYNTHETIC-NOT-A-PHONE',
            'no_hp_keluarga_encrypted' => null,
        ];
    }
}
