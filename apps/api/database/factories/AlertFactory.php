<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    public function definition(): array
    {
        return [
            'responden_id' => Participant::factory(),
            'entri_id' => null,
            'level' => 'KUNING',
            'sebab' => 'SYNTHETIC_TEST_ONLY',
            'dibuat_pada' => '2026-01-02 00:02:00.123456',
            'status' => 'BARU',
        ];
    }
}
