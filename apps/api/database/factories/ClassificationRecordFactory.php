<?php

namespace Database\Factories;

use App\Models\ClassificationRecord;
use App\Models\DailyEntry;
use App\Models\ResearchSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class ClassificationRecordFactory extends Factory
{
    protected $model = ClassificationRecord::class;

    public function definition(): array
    {
        return [
            'entri_id' => DailyEntry::factory(),
            'level' => 'HIJAU',
            'aturan_terpicu' => [],
            'ruleset_key' => 'classification',
            'ruleset_version' => fn () => ResearchSetting::factory()->create(['kunci' => 'classification'])->versi,
        ];
    }
}
