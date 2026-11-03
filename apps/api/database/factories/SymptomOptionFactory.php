<?php

namespace Database\Factories;

use App\Models\ResearchSetting;
use App\Models\SymptomOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class SymptomOptionFactory extends Factory
{
    protected $model = SymptomOption::class;

    public function definition(): array
    {
        return [
            'pengaturan_id' => ResearchSetting::factory(),
            'kode_gejala' => 'SYNTHETIC_ITEM',
            'jawaban' => 'SYNTHETIC_RESPONSE',
        ];
    }
}
