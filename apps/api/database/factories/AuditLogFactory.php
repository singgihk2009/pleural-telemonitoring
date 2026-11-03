<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'actor_id' => StaffUser::factory(),
            'aksi' => 'SYNTHETIC_TEST',
            'entity_type' => 'TEST_FIXTURE',
            'entity_id' => null,
            'terjadi_pada' => '2026-01-02 00:00:00.123456',
            'metadata_redacted' => ['purpose_code' => 'SYNTHETIC_TEST'],
        ];
    }
}
