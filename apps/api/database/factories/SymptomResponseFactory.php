<?php

namespace Database\Factories;

use App\Models\DailyEntry;
use App\Models\SymptomOption;
use App\Models\SymptomResponse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class SymptomResponseFactory extends Factory
{
    protected $model = SymptomResponse::class;

    public function definition(): array
    {
        return [
            'entri_id' => DailyEntry::factory(),
            'symptom_option_id' => SymptomOption::factory(),
            'kode_gejala' => fn (array $attributes) => SymptomOption::findOrFail($attributes['symptom_option_id'])->kode_gejala,
            'jawaban' => fn (array $attributes) => SymptomOption::findOrFail($attributes['symptom_option_id'])->jawaban,
        ];
    }
}
