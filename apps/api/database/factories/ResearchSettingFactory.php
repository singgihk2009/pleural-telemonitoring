<?php

namespace Database\Factories;

use App\Models\ResearchSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class ResearchSettingFactory extends Factory
{
    protected $model = ResearchSetting::class;

    public function definition(): array
    {
        return [
            'kunci' => 'SYNTHETIC_TEST',
            'nilai_json' => ['test_only' => true, 'enabled' => false],
            'versi' => 'SYN-'.Str::uuid(),
        ];
    }
}
