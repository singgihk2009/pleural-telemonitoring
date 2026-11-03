<?php

namespace App\Http\Controllers;

use App\Auth\ResearchRoleCode;
use App\Auth\SecurityAudit;
use App\Models\IdentifiedAccessGrant;
use App\Models\Participant;
use App\Models\StaffUser;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AccessGrantController extends Controller
{
    public function store(Request $request, Participant $participant): mixed
    {
        Gate::authorize('grantIdentifiedAccess', $participant);
        $data = $request->validate([
            'staff_id' => ['required', 'integer'],
            'purpose_code' => ['required', 'string', 'regex:/\A[A-Z0-9_]{1,80}\z/'],
            'expires_at' => ['required', 'date', 'after:now', 'before_or_equal:'.now()->addHours(config('research_auth.grant_max_hours'))->toISOString()],
        ]);

        return DB::transaction(function () use ($request, $participant, $data) {
            $staff = StaffUser::whereKey($data['staff_id'])->lockForUpdate()->firstOrFail();
            abort_unless($staff->id !== $request->user()->id && $staff->status === 'AKTIF' && $staff->hasRole(ResearchRoleCode::Administrator), 403);
            $grant = IdentifiedAccessGrant::create([
                'pengguna_id' => $staff->id, 'responden_id' => $participant->id,
                'approved_by' => $request->user()->id, 'purpose_code' => $data['purpose_code'],
                'starts_at' => now(), 'expires_at' => CarbonImmutable::parse($data['expires_at'])->utc(),
            ]);
            app(SecurityAudit::class)->record($request, 'identified.access.granted', 'SUCCESS', $participant->id);

            return response()->json(['data' => ['id' => $grant->id, 'expires_at' => $grant->expires_at->toISOString()]], 201);
        });
    }

    public function destroy(Request $request, Participant $participant, int $grant): mixed
    {
        Gate::authorize('grantIdentifiedAccess', $participant);

        return DB::transaction(function () use ($request, $participant, $grant) {
            $record = IdentifiedAccessGrant::where('responden_id', $participant->id)->whereKey($grant)->lockForUpdate()->firstOrFail();
            $record->update(['revoked_at' => now()]);
            app(SecurityAudit::class)->record($request, 'identified.access.revoked', 'SUCCESS', $participant->id);

            return response()->json(['message' => 'Access grant revoked.']);
        });
    }
}
