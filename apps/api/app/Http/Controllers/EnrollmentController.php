<?php

namespace App\Http\Controllers;

use App\Auth\SecurityAudit;
use App\Models\Participant;
use App\Models\ParticipantContact;
use App\Models\PatientCredential;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnrollmentController extends Controller
{
    public function access(): mixed
    {
        Gate::authorize('create', Participant::class);

        return response()->json(['data' => ['can_manage_enrollment' => true]]);
    }

    public function search(Request $request): mixed
    {
        Gate::authorize('create', Participant::class);

        return $this->storage(function () use ($request) {
            $data = $request->validate([
                'search' => ['nullable', 'string', 'max:32'],
                'group' => ['nullable', Rule::in(['INTERVENSI', 'KONTROL'])],
                'status' => ['nullable', Rule::in(['AKTIF', 'SELESAI', 'DROP_OUT'])],
                'page' => ['sometimes', 'integer', 'min:1'],
            ]);

            return DB::transaction(function () use ($request, $data) {
                $query = Participant::query();
                if (isset($data['search']) && $data['search'] !== '') {
                    $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $data['search']);
                    $query->whereRaw("kode LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
                }
                if (! empty($data['group'])) {
                    $query->where('kelompok', $data['group']);
                }
                if (! empty($data['status'])) {
                    $query->where('status', $data['status']);
                }
                $counts = (clone $query)->selectRaw("COUNT(*) AS total, SUM(CASE WHEN kelompok = 'INTERVENSI' THEN 1 ELSE 0 END) AS intervensi, SUM(CASE WHEN kelompok = 'KONTROL' THEN 1 ELSE 0 END) AS kontrol, SUM(CASE WHEN status = 'AKTIF' THEN 1 ELSE 0 END) AS aktif")->first();
                $page = $query->orderByDesc('created_at')->orderBy('id')->paginate(25, ['*'], 'page', $data['page'] ?? 1);
                app(SecurityAudit::class)->record($request, 'enrollment.list', 'SUCCESS');

                return response()->json(['data' => $page->getCollection()->map(fn ($participant) => $this->resource($participant, false))->all(), 'meta' => [
                    'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => (int) $counts->total,
                    'intervensi' => (int) $counts->intervensi, 'kontrol' => (int) $counts->kontrol, 'aktif' => (int) $counts->aktif,
                ]]);
            });
        });
    }

    public function show(Request $request, Participant $participant): mixed
    {
        Gate::authorize('viewEnrollment', $participant);

        return $this->storage(function () use ($request, $participant) {
            app(SecurityAudit::class)->record($request, 'enrollment.view', 'SUCCESS');

            return response()->json(['data' => $this->resource($participant, true)]);
        });
    }

    public function store(Request $request): mixed
    {
        Gate::authorize('create', Participant::class);

        return $this->storage(function () use ($request) {
            $data = $this->validated($request);

            return DB::transaction(function () use ($request, $data) {
                $participant = Participant::create(collect($data)->except('contact')->all());
                $this->contact($participant, $data);
                // No participant identifiers, dates, notes, contacts or clinical values in the event.
                app(SecurityAudit::class)->record($request, 'participant.enrolled', 'SUCCESS');

                return response()->json(['data' => $this->resource($participant->fresh(), true)], 201);
            }, 3);
        });
    }

    public function update(Request $request, Participant $participant): mixed
    {
        Gate::authorize('update', $participant);

        return $this->storage(function () use ($request, $participant) {
            return DB::transaction(function () use ($request, $participant) {
                $participant = Participant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
                Gate::authorize('update', $participant);
                $data = $this->validated($request, $participant);
                $expected = $data['expected_updated_at'] === null ? null : CarbonImmutable::parse($data['expected_updated_at'])->utc()->toISOString();
                if ($participant->updated_at?->toISOString() !== $expected) {
                    throw new HttpException(409);
                }
                if ($this->history($participant)) {
                    foreach (['vas_baseline_mm', 'tanggal_pulang'] as $field) {
                        $current = $field === 'tanggal_pulang' ? $participant->tanggal_pulang->toDateString() : $participant->vas_baseline_mm;
                        if (array_key_exists($field, $data) && $data[$field] !== $current) {
                            throw ValidationException::withMessages([$field => ['Baseline dan hari 0 terkunci setelah ada catatan penelitian.']]);
                        }
                    }
                }
                $participant->fill(collect($data)->except(['contact', 'expected_updated_at'])->all());
                $next = now();
                if ($participant->updated_at && ! $next->greaterThan($participant->updated_at)) {
                    $next = $participant->updated_at->copy()->addMicrosecond();
                }
                $participant->updated_at = $next;
                $participant->save();
                if ($participant->wasChanged('status') && $participant->status !== 'AKTIF') {
                    $credential = PatientCredential::whereKey($participant->id)->lockForUpdate()->first();
                    $credential?->tokens()->delete();
                }
                $this->contact($participant, $data);
                app(SecurityAudit::class)->record($request, 'participant.updated', 'SUCCESS');

                return response()->json(['data' => $this->resource($participant->fresh(), true)]);
            }, 3);
        });
    }

    private function validated(Request $request, ?Participant $participant = null): array
    {
        if ($participant && ($request->exists('kode') || $request->exists('kelompok'))) {
            throw ValidationException::withMessages(['kelompok' => ['Kode dan kelompok penelitian tidak dapat diubah.']]);
        }
        $required = $participant ? 'sometimes' : 'required';
        $state = $request->input('status', $participant?->status ?? 'AKTIF');
        $data = $request->validate([
            'kode' => $participant ? ['prohibited'] : ['required', 'string', 'max:32', Rule::unique('responden', 'kode')],
            'kelompok' => $participant ? ['prohibited'] : ['required', Rule::in(['INTERVENSI', 'KONTROL'])],
            'tanggal_pulang' => [$required, 'required', 'date_format:Y-m-d'],
            'vas_baseline_mm' => [$required, 'required', 'integer', 'between:0,100', function ($attribute, $value, $fail) {
                if (! is_int($value)) {
                    $fail('VAS harus berupa bilangan bulat 0–100 mm.');
                }
            }],
            'tanggal_consent' => [$required, 'required', 'date_format:Y-m-d'],
            'versi_consent' => [$required, 'required', 'string', 'max:40'],
            'status' => ['sometimes', 'required', Rule::in(['AKTIF', 'SELESAI', 'DROP_OUT'])],
            'alasan_drop_out' => ['nullable', 'string', 'max:1000'],
            'expected_updated_at' => $participant ? ['present', 'nullable', 'date'] : ['prohibited'],
            'contact' => ['nullable', 'array:initials,patient_contact,family_contact'],
            'contact.initials' => ['nullable', 'string', 'max:20'],
            'contact.patient_contact' => ['nullable', 'string', 'max:40'],
            'contact.family_contact' => ['nullable', 'string', 'max:40'],
            'id' => ['prohibited'], 'pin' => ['prohibited'], 'credentials' => ['prohibited'],
            'created_at' => ['prohibited'], 'updated_at' => ['prohibited'],
        ]);
        $reason = $request->exists('alasan_drop_out') ? ($data['alasan_drop_out'] ?? null) : $participant?->alasan_drop_out;
        if ($state === 'DROP_OUT' && (! is_string($reason) || trim($reason) === '')) {
            throw ValidationException::withMessages(['alasan_drop_out' => ['Alasan wajib diisi untuk DROP_OUT.']]);
        }
        if ($state !== 'DROP_OUT' && $request->filled('alasan_drop_out')) {
            throw ValidationException::withMessages(['alasan_drop_out' => ['Alasan hanya diisi untuk DROP_OUT.']]);
        }
        $data['status'] = $state;
        $data['alasan_drop_out'] = $state === 'DROP_OUT' ? $reason : null;

        return $data;
    }

    private function contact(Participant $participant, array $data): void
    {
        if (! array_key_exists('contact', $data)) {
            return;
        }
        $input = $data['contact'] ?? ['initials' => null, 'patient_contact' => null, 'family_contact' => null];
        $attributes = [];
        foreach (['initials' => 'inisial_encrypted', 'patient_contact' => 'no_hp_pasien_encrypted', 'family_contact' => 'no_hp_keluarga_encrypted'] as $key => $column) {
            if (array_key_exists($key, $input)) {
                $attributes[$column] = $input[$key];
            }
        }
        if ($attributes && ($participant->contact()->exists() || count(array_filter($attributes, fn ($value) => $value !== null)) > 0)) {
            ParticipantContact::updateOrCreate(['responden_id' => $participant->id], $attributes);
        }
    }

    private function history(Participant $participant): bool
    {
        return $participant->entries()->exists() || $participant->alerts()->exists() || $participant->outcome()->exists() || $participant->usabilityResponses()->exists();
    }

    private function resource(Participant $participant, bool $editor): array
    {
        $data = $participant->only(['id', 'kode', 'kelompok', 'status', 'vas_baseline_mm', 'versi_consent']);
        $data['tanggal_pulang'] = $participant->tanggal_pulang->toDateString();
        $data['tanggal_consent'] = $participant->tanggal_consent->toDateString();
        $data['updated_at'] = $participant->updated_at?->toISOString();
        if ($editor) {
            // Authorize before loading the relationship or reading encrypted casts.
            Gate::authorize('viewContact', $participant);
            $contact = $participant->contact;
            $data['alasan_drop_out'] = $participant->alasan_drop_out;
            $data['baseline_locked'] = $this->history($participant);
            $data['contact'] = $contact ? ['initials' => $contact->inisial_encrypted, 'patient_contact' => $contact->no_hp_pasien_encrypted, 'family_contact' => $contact->no_hp_keluarga_encrypted] : null;
        }

        return $data;
    }

    private function storage(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['kode' => ['Kode responden sudah digunakan.']]);
        } catch (QueryException) {
            // Do not report SQL/bindings containing codes, contact details or baseline values.
            throw new HttpException(503);
        }
    }
}
