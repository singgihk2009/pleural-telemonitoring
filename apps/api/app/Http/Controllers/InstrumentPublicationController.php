<?php

namespace App\Http\Controllers;

use App\Assessment\DailyInstrument;
use App\Auth\SecurityAudit;
use App\Models\Participant;
use App\Models\ResearchSetting;
use App\Models\SymptomOption;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InstrumentPublicationController extends Controller
{
    public function index(Request $request, DailyInstrument $adapter): mixed
    {
        Gate::authorize('create', Participant::class);
        $page = ResearchSetting::where('kunci', 'daily_assessment')->orderByDesc('berlaku_mulai')->paginate(25);
        $ids = $page->getCollection()->pluck('id');
        $receipts = DB::table('instrument_publications')->whereIn('setting_id', $ids)->get()->keyBy('setting_id');

        return response()->json(['current_version' => $adapter->current()?->versi,
            'data' => $page->getCollection()->map(fn ($setting) => ['version' => $setting->versi,
                'approved_by' => $setting->disetujui_oleh, 'approved_at' => $setting->disetujui_pada?->toISOString(),
                'effective_at' => $setting->berlaku_mulai?->toISOString(),
                'approval_reference' => $receipts[$setting->id]->approval_reference ?? null,
                'expires_at' => isset($receipts[$setting->id]) ? CarbonImmutable::parse($receipts[$setting->id]->expires_at, 'UTC')->toISOString() : null,
                'definition' => $setting->nilai_json])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()]]);
    }

    public function publish(Request $request, DailyInstrument $adapter): mixed
    {
        Gate::authorize('create', Participant::class);
        $data = $request->validate([
            'version' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{1,40}\z/'],
            'expected_current_version' => ['present', 'nullable', 'string', 'max:40'],
            'confirm_publication' => ['required', 'accepted'],
            'approval_reference' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{1,80}\z/'],
            'effective_at' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z', 'after_or_equal:now'],
            'expires_at' => ['required', 'date_format:Y-m-d\TH:i:s.v\Z', 'after:effective_at'],
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
            'questions.*.options.*' => ['required', 'array:value,label'],
            'questions.*.options.*.value' => ['required', 'string', 'regex:/\A[A-Z0-9_-]{1,32}\z/'],
            'questions.*.options.*.label' => ['required', 'string', 'max:150'],
        ]);
        if (array_diff(array_keys($request->all()), array_keys($data)) || ! is_int($data['schedule']['first_day']) || ! is_int($data['schedule']['last_day'])) {
            throw ValidationException::withMessages(['definition' => ['Unexpected fields or noninteger schedule.']]);
        }
        foreach ($data['questions'] as $question) {
            if (! is_bool($question['required']) || count(array_unique(array_column($question['options'], 'value'))) !== count($question['options'])) {
                throw ValidationException::withMessages(['questions' => ['Use boolean required flags and distinct option values per question.']]);
            }
        }
        try {
            return DB::transaction(function () use ($request, $data, $adapter) {
                DB::table('user_roles')->where('kode', 'PENELITI_UTAMA')->lockForUpdate()->firstOrFail();
                $actor = $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $token = $actor->tokens()->whereKey($request->user()->currentAccessToken()->id)->first();
                abort_unless($token && $token->expires_at?->isFuture() && $token->can('api'), 401);
                Gate::forUser($actor)->authorize('create', Participant::class);
                abort_unless($adapter->current()?->versi === $data['expected_current_version'], 409);
                $effective = CarbonImmutable::parse($data['effective_at']);
                abort_if($effective->isPast(), 409); // Recheck after waiting for another publisher.
                $options = [];
                $questions = [];
                foreach ($data['questions'] as $question) {
                    $public = $question;
                    $public['options'] = [];
                    foreach ($question['options'] as $option) {
                        // Explicit random safe-integer IDs permit a single immutable JSON insert.
                        // PK/unique conflicts roll back, never rewrite an approved definition.
                        $id = random_int(1099511627776, 4503599627370495);
                        $options[] = ['id' => $id, 'kode_gejala' => $question['code'], 'jawaban' => $option['value']];
                        $public['options'][] = ['id' => $id, 'label' => $option['label']];
                    }
                    $questions[] = $public;
                }
                $setting = ResearchSetting::create(['kunci' => 'daily_assessment', 'versi' => $data['version'],
                    'nilai_json' => ['format' => 'telenapas.daily.v1', 'schedule' => $data['schedule'], 'questions' => $questions],
                    'disetujui_oleh' => $request->user()->id, 'disetujui_pada' => now(), 'berlaku_mulai' => $effective]);
                foreach ($options as $option) {
                    $model = new SymptomOption;
                    $model->forceFill($option + ['pengaturan_id' => $setting->id])->save();
                }
                DB::table('instrument_publications')->insert(['setting_id' => $setting->id, 'approval_reference' => $data['approval_reference'],
                    'effective_at' => $effective->format('Y-m-d H:i:s.u'), 'expires_at' => CarbonImmutable::parse($data['expires_at'])->format('Y-m-d H:i:s.u')]);
                app(SecurityAudit::class)->record($request, 'instrument.published', 'SUCCESS', (string) $setting->id, 'research_setting');

                return response()->json(['data' => ['version' => $setting->versi, 'approved_by' => $setting->disetujui_oleh,
                    'approved_at' => $setting->disetujui_pada->toISOString(), 'effective_at' => $effective->toISOString(),
                    'expires_at' => $data['expires_at'], 'approval_reference' => $data['approval_reference']]], 201);
            }, 3);
        } catch (QueryException $exception) {
            abort_if(($exception->errorInfo[1] ?? null) === 1062, 409);
            Log::error('instrument.publication_failed');
            abort(503);
        }
    }
}
