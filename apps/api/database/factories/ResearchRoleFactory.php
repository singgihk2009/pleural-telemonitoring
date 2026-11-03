<?php

namespace Database\Factories;

use App\Models\ResearchRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class ResearchRoleFactory extends Factory
{
    protected $model = ResearchRole::class;

    public function definition(): array
    {
        return [
            'kode' => 'PENELITI_UTAMA',
        ];
    }
}
