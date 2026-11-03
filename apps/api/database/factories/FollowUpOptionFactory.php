<?php

namespace Database\Factories;

use App\Models\FollowUpOption;
use App\Models\ResearchSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class FollowUpOptionFactory extends Factory
{
    protected $model = FollowUpOption::class;

    public function definition(): array
    {
        return [
            'pengaturan_id' => ResearchSetting::factory(),
            'kode' => 'SYNTHETIC_OPTION',
        ];
    }
}
