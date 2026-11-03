<?php

namespace Database\Factories;

use App\Models\Outcome;
use App\Models\Participant;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class OutcomeFactory extends Factory
{
    protected $model = Outcome::class;

    public function definition(): array
    {
        return [
            'responden_id' => Participant::factory(),
            'vas_hari30' => null,
            'kekambuhan_a' => null,
            'kekambuhan_b' => null,
            'kekambuhan_c' => null,
            'kekambuhan_akhir' => null,
            'readmission' => null,
            'dinilai_oleh' => StaffUser::factory(),
            'tanggal_penilaian' => '2026-01-31 00:00:00.123456',
        ];
    }
}
