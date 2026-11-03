<?php

namespace Database\Factories;

use App\Models\ResearchRole;
use App\Models\RoleAssignment;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class RoleAssignmentFactory extends Factory
{
    protected $model = RoleAssignment::class;

    public function definition(): array
    {
        return [
            'pengguna_id' => StaffUser::factory(),
            'role_id' => ResearchRole::factory(),
        ];
    }
}
