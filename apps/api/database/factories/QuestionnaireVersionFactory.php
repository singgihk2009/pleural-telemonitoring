<?php

namespace Database\Factories;

use App\Models\QuestionnaireVersion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class QuestionnaireVersionFactory extends Factory
{
    protected $model = QuestionnaireVersion::class;

    public function definition(): array
    {
        return [
            'versi' => 'SYN-'.Str::uuid(),
            'definisi_item' => [['code' => 'SYNTHETIC_ITEM', 'test_only' => true]],
            'aturan_skoring' => null,
        ];
    }
}
