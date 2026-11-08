<?php

namespace App\Http\Controllers;

use App\Assessment\DailyInstrument;
use App\Auth\ParticipantAccess;
use App\Auth\SecurityAudit;
use App\Models\DailyEntry;
use App\Models\Participant;
use App\Models\PatientCredential;
use App\Models\SymptomOption;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    private function patient(Request $request): PatientCredential
    {
        abort_unless($request->user() instanceof PatientCredential, 403);

        return $request->user();
    }

    public function status(Request $request, DailyInstrument $instruments): mixed
    {
        $patient = $this->patient($request);
        $participant = $patient->participant;
        $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
        $day = (int) $participant->tanggal_pulang->diffInDays(CarbonImmutable::parse($today, 'UTC'), false);
        $setting = $instruments->current();
        $entry = DailyEntry::where('responden_id', $participant->id)->where('tanggal_harian', $today)->first();
        $schedule = $setting?->nilai_json['schedule'];
        $eligible = $schedule && $day >= $schedule['first_day'] && $day <= $schedule['last_day'];

        return response()->json(['data' => [
            'available' => (bool) $setting, 'can_submit' => (bool) ($eligible && ! $entry),
            'date_wib' => $today, 'study_day' => $day, 'submission_id' => (string) Str::uuid(),
            'questionnaire' => $setting ? $instruments->publicDefinition($setting) : null,
            'submitted' => $entry ? $this->projection($entry) : null,
        ]]);
    }

    public function store(Request $request, DailyInstrument $instruments): mixed
    {
        $patient = $this->patient($request);
        $data = $request->validate([
            'submission_id' => ['required', 'uuid'], 'version' => ['required', 'string', 'max:40'],
            'date_wib' => ['required', 'date_format:Y-m-d'],
            'vas_mm' => ['required', 'integer', 'between:0,100', fn ($attribute, $value, $fail) => is_int($value) ?: $fail('Use an integer.')],
            'filled_by' => ['required', 'in:PASIEN,KELUARGA'],
            'device_created_at' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z'],
            'answers' => ['present', 'array', 'list', 'max:50'],
            'answers.*' => ['required', 'array:code,option_id'],
            'answers.*.code' => ['required', 'string', 'max:48', 'distinct:strict'],
            'answers.*.option_id' => ['required', 'integer', 'min:1', fn ($attribute, $value, $fail) => is_int($value) ?: $fail('Use an integer.')],
        ]);
        if (array_diff(array_keys($request->all()), array_keys($data))) {
            throw ValidationException::withMessages(['request' => ['Unexpected fields. Participant identity is server derived.']]);
        }
        $data['submission_id'] = strtolower($data['submission_id']);
        usort($data['answers'], fn ($a, $b) => strcmp($a['code'], $b['code']));
        // Explicit field order makes retries independent of JSON object/answer order.
        $canonical = [$data['submission_id'], $data['version'], $data['date_wib'], $data['vas_mm'], $data['filled_by'], $data['device_created_at'], array_map(fn ($a) => [$a['code'], $a['option_id']], $data['answers'])];
        $fingerprint = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($request, $patient, $data, $fingerprint, $instruments) {
                // Same lock order as PR-10: resets/revocations and submissions serialize.
                $participant = Participant::whereKey($patient->responden_id)->lockForUpdate()->firstOrFail();
                $credential = PatientCredential::whereKey($participant->id)->lockForUpdate()->firstOrFail();
                $token = $credential->tokens()->whereKey($patient->currentAccessToken()->id)->first();
                abort_unless($credential->revoked_at === null && $participant->kelompok === 'INTERVENSI' && $participant->status === 'AKTIF'
                    && $token && $token->expires_at?->isFuture() && $token->can('api'), 401);
                $existing = DailyEntry::whereKey($data['submission_id'])->first();
                if ($existing) {
                    abort_unless($existing->responden_id === $participant->id && $existing->submission_fingerprint !== null
                        && hash_equals($existing->submission_fingerprint, $fingerprint), 409, 'Submission identity already used.');

                    return response()->json(['data' => $this->projection($existing), 'replayed' => true]);
                }
                $setting = $instruments->current();
                abort_unless($setting, 409, 'Assessment unavailable.');
                abort_unless($setting->versi === $data['version'], 409, 'Questionnaire changed. Reload before submitting.');
                $today = CarbonImmutable::now('Asia/Jakarta')->toDateString();
                $day = (int) $participant->tanggal_pulang->diffInDays(CarbonImmutable::parse($today, 'UTC'), false);
                $schedule = $setting->nilai_json['schedule'];
                abort_unless($data['date_wib'] === $today && $day >= $schedule['first_day'] && $day <= $schedule['last_day'], 409, 'Assessment date is not currently eligible.');
                abort_if(DailyEntry::where('responden_id', $participant->id)->where('tanggal_harian', $today)->exists(), 409, 'Daily assessment already submitted.');
                $answers = collect($data['answers'])->keyBy('code');
                $questions = collect($setting->nilai_json['questions'])->keyBy('code');
                foreach ($answers as $code => $answer) {
                    if (! isset($questions[$code]) || ! in_array($answer['option_id'], array_column($questions[$code]['options'], 'id'), true)) {
                        throw ValidationException::withMessages(['answers' => ['Unknown question or answer option.']]);
                    }
                }
                foreach ($questions as $code => $question) {
                    if ($question['required'] && ! isset($answers[$code])) {
                        throw ValidationException::withMessages(['answers' => ['Required response missing.']]);
                    }
                }
                $entry = new DailyEntry;
                $entry->forceFill([
                    'id' => $data['submission_id'], 'responden_id' => $participant->id, 'kelompok' => 'INTERVENSI',
                    'tanggal_pulang' => $participant->tanggal_pulang, 'tanggal_wib' => $today, 'vas_mm' => $data['vas_mm'],
                    'tipe' => 'HARIAN', 'diisi_oleh' => $data['filled_by'],
                    'dibuat_di_perangkat_pada' => CarbonImmutable::parse($data['device_created_at'])->utc(),
                    'diterima_server_pada' => now(), 'assessment_setting_id' => $setting->id, 'submission_fingerprint' => $fingerprint,
                ])->save();
                $catalog = SymptomOption::where('pengaturan_id', $setting->id)->whereIn('id', $answers->pluck('option_id'))->get()->keyBy('id');
                foreach ($answers as $answer) {
                    $option = $catalog[$answer['option_id']];
                    $entry->symptoms()->create(['symptom_option_id' => $option->id, 'kode_gejala' => $option->kode_gejala, 'jawaban' => $option->jawaban]);
                }
                app(SecurityAudit::class)->record($request, 'assessment.submitted', 'SUCCESS', $entry->id, 'daily_entry');

                return response()->json(['data' => $this->projection($entry->fresh()), 'replayed' => false], 201);
            }, 3);
        } catch (QueryException $exception) {
            // Never report SQL bindings containing assessment values. Transaction already rolled back.
            abort_if(($exception->errorInfo[1] ?? null) === 1062, 409, 'Submission conflict. Reload status.');
            Log::error('assessment.storage_failed');
            abort(503, 'Assessment could not be stored. Retry with the same submission identity.');
        }
    }

    public function history(Request $request): mixed
    {
        $patient = $this->patient($request);

        $filter = $request->validate(['submission_id' => ['sometimes', 'uuid']]);
        $query = DailyEntry::where('responden_id', $patient->responden_id);
        if (isset($filter['submission_id'])) {
            $query->whereKey(strtolower($filter['submission_id']));
        }

        return $this->page($query);
    }

    public function researcherHistory(Request $request, Participant $participant): mixed
    {
        Gate::authorize('viewFollowUps', $participant); // Existing staff-only monitoring denial, including blinded/IT scopes.
        $filters = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'type' => ['sometimes', 'in:HARIAN,ULANG'],
        ]);
        $filters = array_replace(['from' => null, 'to' => null, 'type' => null], $filters);
        $ids = app(ParticipantAccess::class)->monitoringVisible($request->user(), Participant::query(), true)->select('id');
        app(SecurityAudit::class)->record($request, 'privileged.access', 'AUTHORIZED', $participant->id);

        $query = DailyEntry::where('responden_id', $participant->id)->whereIn('responden_id', $ids);
        if ($filters['from'] !== null) {
            $query->where('tanggal_wib', '>=', $filters['from']);
        }
        if ($filters['to'] !== null) {
            $query->where('tanggal_wib', '<=', $filters['to']);
        }
        if ($filters['type'] !== null) {
            $query->where('tipe', $filters['type']);
        }

        return $this->page($query, $filters);
    }

    private function page($query, ?array $filters = null): mixed
    {
        $page = $query->with(['symptoms', 'assessmentSetting'])->orderByDesc('tanggal_wib')->orderByDesc('id')->paginate(25);

        return response()->json(['data' => $page->getCollection()->map(fn ($entry) => $this->projection($entry, $filters !== null))->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), ...($filters !== null ? ['filters' => $filters] : [])]]);
    }

    private function projection(DailyEntry $entry, bool $historicalLabels = false): array
    {
        return ['id' => $entry->id, 'date_wib' => $entry->tanggal_wib->toDateString(), 'study_day' => $entry->hari_ke,
            'vas_mm' => $entry->vas_mm, 'type' => $entry->tipe, 'filled_by' => $entry->diisi_oleh,
            'received_at' => $entry->diterima_server_pada->toISOString(),
            'device_created_at' => $entry->dibuat_di_perangkat_pada->toISOString(),
            'version' => $entry->assessmentSetting?->versi,
            'answers' => $entry->symptoms->map(fn ($a) => ['code' => $a->kode_gejala, 'option_id' => $a->symptom_option_id, 'value' => $a->jawaban,
                ...($historicalLabels ? $this->historicalLabels($entry, $a->kode_gejala, $a->symptom_option_id) : [])])->all()];
    }

    private function historicalLabels(DailyEntry $entry, string $code, ?int $optionId): array
    {
        $empty = ['question_label' => null, 'answer_label' => null];
        $setting = $entry->assessmentSetting;
        $definition = $setting?->nilai_json;
        if (! $setting || $setting->kunci !== 'daily_assessment' || ! is_array($definition)
            || ($definition['format'] ?? null) !== 'telenapas.daily.v1' || ! is_array($definition['questions'] ?? null)) {
            return $empty;
        }
        // Read the linked immutable version, never today's questionnaire or inferred wording.
        foreach ($definition['questions'] as $question) {
            if (! is_array($question) || ($question['code'] ?? null) !== $code || ! is_string($question['label'] ?? null)
                || ! is_array($question['options'] ?? null)) {
                continue;
            }
            foreach ($question['options'] as $option) {
                if (is_array($option) && ($option['id'] ?? null) === $optionId && is_string($option['label'] ?? null)) {
                    return ['question_label' => $question['label'], 'answer_label' => $option['label']];
                }
            }
        }

        return $empty;
    }
}
