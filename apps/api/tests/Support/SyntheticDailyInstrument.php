<?php

namespace Tests\Support;

use App\Models\ResearchRole;
use App\Models\ResearchSetting;
use App\Models\StaffUser;
use App\Models\SymptomOption;

/** Fabricated technical fixture. Never approve or seed this instrument in research/UAT. */
class SyntheticDailyInstrument
{
    public static function create(array $overrides = []): ResearchSetting
    {
        $staff = StaffUser::factory()->create();
        $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'PENELITI_UTAMA']));
        $optionId = random_int(1000000, 2000000000);
        $setting = ResearchSetting::factory()->create(array_merge([
            'kunci' => 'daily_assessment', 'disetujui_oleh' => $staff->id,
            'disetujui_pada' => now()->subDays(2), 'berlaku_mulai' => now()->subDay(),
            'nilai_json' => ['format' => 'telenapas.daily.v1',
                'schedule' => ['timezone' => 'Asia/Jakarta', 'first_day' => 1, 'last_day' => 30, 'late_policy' => 'same_day_only'],
                'questions' => [['code' => 'SYNTHETIC_ITEM', 'label' => 'Item sintetis untuk pengujian, bukan gejala klinis', 'required' => true,
                    'options' => [['id' => $optionId, 'label' => 'Pilihan sintetis A'], ['id' => $optionId + 1, 'label' => 'Pilihan sintetis B']]]]],
        ], $overrides));
        foreach ([$optionId => 'SYNTHETIC_A', $optionId + 1 => 'SYNTHETIC_B'] as $id => $value) {
            $option = new SymptomOption;
            $option->forceFill(['id' => $id, 'pengaturan_id' => $setting->id, 'kode_gejala' => 'SYNTHETIC_ITEM', 'jawaban' => $value])->save();
        }

        return $setting;
    }
}
