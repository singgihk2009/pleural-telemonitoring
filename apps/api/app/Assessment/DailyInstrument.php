<?php

namespace App\Assessment;

use App\Auth\ResearchRoleCode;
use App\Models\ResearchSetting;
use App\Models\SymptomOption;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Technical adapter only. No default instrument, schedule, scoring or classification. */
class DailyInstrument
{
    public function current(): ?ResearchSetting
    {
        $versions = ResearchSetting::where('kunci', 'daily_assessment')
            ->whereNotNull('disetujui_oleh')->where('disetujui_pada', '<=', now()->format('Y-m-d H:i:s.u'))
            ->whereNotNull('berlaku_mulai')->where('berlaku_mulai', '<=', now()->format('Y-m-d H:i:s.u'))
            ->orderByDesc('berlaku_mulai')->limit(2)->get();
        $setting = $versions->first();
        // Ambiguous activation, invalid latest version or approval never falls back silently.
        if (! $setting || ($versions->count() > 1 && $versions[1]->berlaku_mulai->equalTo($setting->berlaku_mulai))) {
            return null;
        }
        $publication = DB::table('instrument_publications')->where('setting_id', $setting->id)->first();
        if ($publication && CarbonImmutable::parse($publication->expires_at, 'UTC')->lessThanOrEqualTo(now())) {
            return null;
        }
        $approver = $setting->approver;
        if (! $approver || $approver->status !== 'AKTIF' || ! $approver->hasRole(ResearchRoleCode::Researcher)
            || $approver->hasRole(ResearchRoleCode::Administrator) || $approver->hasRole(ResearchRoleCode::Assessor) || $approver->hasRole(ResearchRoleCode::Patient)) {
            return null;
        }
        $definition = $setting->nilai_json;
        $validator = Validator::make($definition, [
            'format' => ['required', 'in:telenapas.daily.v1'],
            'schedule' => ['required', 'array:timezone,first_day,last_day,late_policy'],
            'schedule.timezone' => ['required', 'in:Asia/Jakarta'],
            'schedule.first_day' => ['required', 'integer', 'between:1,30'],
            'schedule.last_day' => ['required', 'integer', 'between:1,30', 'gte:schedule.first_day'],
            'schedule.late_policy' => ['required', 'in:same_day_only'],
            'questions' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'questions.*' => ['required', 'array:code,label,required,options'],
            'questions.*.code' => ['required', 'string', 'regex:/\A[A-Z0-9_]{1,48}\z/', 'distinct:strict'],
            'questions.*.label' => ['required', 'string', 'max:300'],
            'questions.*.required' => ['required', 'boolean'],
            'questions.*.options' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'questions.*.options.*' => ['required', 'array:id,label'],
            'questions.*.options.*.id' => ['required', 'integer', 'min:1', 'distinct:strict'],
            'questions.*.options.*.label' => ['required', 'string', 'max:150'],
        ]);
        if ($validator->fails() || array_diff(array_keys($definition), ['format', 'schedule', 'questions'])) {
            return null;
        }
        if (! is_int($definition['schedule']['first_day']) || ! is_int($definition['schedule']['last_day'])) {
            return null;
        }
        $catalog = SymptomOption::where('pengaturan_id', $setting->id)->get()->keyBy('id');
        foreach ($definition['questions'] as $question) {
            if (! is_bool($question['required'])) {
                return null;
            }
            foreach ($question['options'] as $option) {
                if (! is_int($option['id']) || ($catalog[$option['id']]->kode_gejala ?? null) !== $question['code']) {
                    return null;
                }
            }
        }

        return $setting;
    }

    public function publicDefinition(ResearchSetting $setting): array
    {
        return ['version' => $setting->versi, 'schedule' => $setting->nilai_json['schedule'], 'questions' => $setting->nilai_json['questions']];
    }
}
