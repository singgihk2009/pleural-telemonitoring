<?php

namespace App\Http\Controllers;

use App\Auth\AccessGrantScope;
use App\Auth\ParticipantAccess;
use App\Auth\SecurityAudit;
use App\Models\Alert;
use App\Models\ClassificationRecord;
use App\Models\DailyEntry;
use App\Models\FollowUp;
use App\Models\Outcome;
use App\Models\Participant;
use App\Models\ParticipantContact;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ParticipantController extends Controller
{
    public function index(Request $request, ParticipantAccess $access): mixed
    {
        $records = $access->visible($request->user(), Participant::query())->orderBy('id')->paginate(25);
        $this->audit($request);

        return response()->json(['data' => $records->getCollection()->map(fn (Participant $p) => $this->summary($request, $p))->all(), 'meta' => ['current_page' => $records->currentPage(), 'last_page' => $records->lastPage(), 'total' => $records->total()]]);
    }

    public function show(Request $request, Participant $participant): mixed
    {
        Gate::authorize('view', $participant);
        $participant = app(ParticipantAccess::class)->visible($request->user(), Participant::query())->whereKey($participant->id)->firstOrFail();
        $this->audit($request, $participant);

        return response()->json(['data' => $this->summary($request, $participant)]);
    }

    public function contact(Request $request, Participant $participant): mixed
    {
        Gate::authorize('viewContact', $participant);
        $contact = ParticipantContact::where('responden_id', $participant->id)->whereIn('responden_id', app(ParticipantAccess::class)->visible($request->user(), Participant::query(), AccessGrantScope::Contact)->select('id'))->first();
        $this->audit($request, $participant);

        return response()->json(['data' => $contact ? ['initials' => $contact->inisial_encrypted, 'patient_contact' => $contact->no_hp_pasien_encrypted, 'family_contact' => $contact->no_hp_keluarga_encrypted] : null]);
    }

    public function entries(Request $request, Participant $participant): mixed
    {
        Gate::authorize('viewMonitoring', $participant);
        $this->audit($request, $participant);

        return $this->page(DailyEntry::where('responden_id', $participant->id)->whereIn('responden_id', $this->monitoringIds($request))->select(['id', 'responden_id', 'hari_ke', 'vas_mm', 'tipe'])->orderBy('tanggal_wib')->paginate(25));
    }

    public function classifications(Request $request, Participant $participant): mixed
    {
        Gate::authorize('viewMonitoring', $participant);
        $this->audit($request, $participant);

        return $this->page(ClassificationRecord::whereHas('entry', fn ($entry) => $entry->where('responden_id', $participant->id)->whereIn('responden_id', $this->monitoringIds($request)))->select(['id', 'entri_id', 'level', 'ruleset_version'])->orderBy('id')->paginate(25));
    }

    public function alerts(Request $request, Participant $participant): mixed
    {
        Gate::authorize('viewFollowUps', $participant);
        $this->audit($request, $participant);

        return $this->page(Alert::where('responden_id', $participant->id)->whereIn('responden_id', $this->monitoringIds($request, true))->select(['id', 'responden_id', 'level', 'status', 'dibuat_pada'])->orderBy('id')->paginate(25));
    }

    public function followUps(Request $request, Participant $participant): mixed
    {
        Gate::authorize('viewFollowUps', $participant);
        $this->audit($request, $participant);
        $records = FollowUp::whereHas('alert', fn ($alert) => $alert->where('responden_id', $participant->id)->whereIn('responden_id', $this->monitoringIds($request, true)))->orderBy('id')->paginate(25);

        // Explicit projection: never serialize arbitrary loaded Eloquent relationships.
        return $this->page($records, fn (FollowUp $row) => ['id' => $row->id, 'peringatan_id' => $row->peringatan_id, 'waktu' => $row->waktu->toISOString(), 'keputusan' => $row->keputusan, 'hasil_asesmen' => $row->hasil_asesmen, 'vas_ulang' => $row->vas_ulang]);
    }

    public function outcome(Request $request, Participant $participant): mixed
    {
        Gate::authorize('view', $participant);
        $this->audit($request, $participant);
        $outcome = Outcome::where('responden_id', $participant->id)->whereIn('responden_id', app(ParticipantAccess::class)->visible($request->user(), Participant::query())->select('id'))->first();

        return response()->json(['data' => $outcome ? $outcome->only(['id', 'vas_hari30', 'kekambuhan_a', 'kekambuhan_b', 'kekambuhan_c', 'kekambuhan_akhir', 'readmission']) : null]);
    }

    private function monitoringIds(Request $request, bool $staffOnly = false): Builder
    {
        return app(ParticipantAccess::class)->monitoringVisible($request->user(), Participant::query(), $staffOnly)->select('id');
    }

    private function summary(Request $request, Participant $participant): array
    {
        $data = $participant->only(['id', 'status', 'vas_baseline_mm']);
        if (! app(ParticipantAccess::class)->blinded($request->user())) {
            $data += $participant->only(['kode', 'kelompok', 'tanggal_pulang']);
        }

        return $data;
    }

    private function audit(Request $request, ?Participant $participant = null): void
    {
        if ($request->user() instanceof StaffUser) {
            app(SecurityAudit::class)->record($request, 'privileged.access', 'AUTHORIZED', $participant?->id);
        }
    }

    private function page($paginator, ?callable $map = null): mixed
    {
        $data = $map ? $paginator->getCollection()->map($map)->all() : $paginator->items();

        return response()->json(['data' => $data, 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()]]);
    }
}
