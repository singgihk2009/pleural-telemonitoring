<?php

namespace Database\Factories;

use App\Models\DailyEntry;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** Wholly fabricated test data. Never use to populate a research deployment. */
class DailyEntryFactory extends Factory
{
    protected $model = DailyEntry::class;

    public function definition(): array
    {
        return [
            'responden_id' => Participant::factory(),
            'kelompok' => 'INTERVENSI',
            'tanggal_pulang' => fn (array $attributes) => Participant::findOrFail($attributes['responden_id'])->tanggal_pulang->toDateString(),
            'tanggal_wib' => fn (array $attributes) => Participant::findOrFail($attributes['responden_id'])->tanggal_pulang->addDay()->toDateString(),
            'vas_mm' => 0,
            'tipe' => 'HARIAN',
            'diisi_oleh' => 'PASIEN',
            'dibuat_di_perangkat_pada' => '2026-01-02 00:00:00.123456',
            'diterima_server_pada' => '2026-01-02 00:01:00.123456',
        ];
    }

    public function repeat(): static
    {
        return $this->state(fn () => ['tipe' => 'ULANG']);
    }
}
