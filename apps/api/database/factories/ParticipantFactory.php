<?php

namespace Database\Factories;

use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class ParticipantFactory extends Factory
{
    protected $model = Participant::class;

    public function definition(): array
    {
        return [
            'kode' => 'SYN-'.substr((string) Str::uuid(), 0, 24),
            'kelompok' => 'INTERVENSI',
            'tanggal_pulang' => '2026-01-01',
            'vas_baseline_mm' => 0,
            'status' => 'AKTIF',
            'alasan_drop_out' => null,
            'tanggal_consent' => '2026-01-01',
            'versi_consent' => 'SYNTHETIC-TEST-v1',
        ];
    }

    public function control(): static
    {
        return $this->state(fn () => ['kelompok' => 'KONTROL']);
    }
}
