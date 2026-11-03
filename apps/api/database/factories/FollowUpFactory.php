<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\FollowUp;
use App\Models\FollowUpOption;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class FollowUpFactory extends Factory
{
    protected $model = FollowUp::class;

    public function definition(): array
    {
        return [
            'peringatan_id' => Alert::factory(),
            'perawat_id' => StaffUser::factory(),
            'waktu' => '2026-01-02 01:00:00.123456',
            'follow_up_option_id' => FollowUpOption::factory(),
            'keputusan' => fn (array $attributes) => FollowUpOption::findOrFail($attributes['follow_up_option_id'])->kode,
            'hasil_asesmen' => 'SYNTHETIC TEST ONLY',
            'vas_ulang' => null,
        ];
    }
}
