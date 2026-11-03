<?php

namespace Database\Factories;

use App\Models\Participant;
use App\Models\QuestionnaireVersion;
use App\Models\UsabilityResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class UsabilityResponseFactory extends Factory
{
    protected $model = UsabilityResponse::class;

    public function definition(): array
    {
        return [
            'responden_id' => Participant::factory(),
            'kelompok' => 'INTERVENSI',
            'versi_kuesioner' => fn () => QuestionnaireVersion::factory()->create()->versi,
            'jawaban_item' => [null],
            'tanggal_pengisian' => '2026-01-31 00:00:00.123456',
            'pengisi' => 'PASIEN',
        ];
    }
}
