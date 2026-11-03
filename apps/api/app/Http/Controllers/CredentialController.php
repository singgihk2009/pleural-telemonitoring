<?php

namespace App\Http\Controllers;

use App\Auth\ResearchRoleCode;
use App\Auth\SecurityAudit;
use App\Models\Participant;
use App\Models\PatientCredential;
use App\Models\StaffUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CredentialController extends Controller
{
    public function issue(Request $request, Participant $participant): mixed
    {
        Gate::authorize('manageCredentials', $participant);
        $data = $request->validate(['pin' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);

        return DB::transaction(function () use ($request, $participant, $data) {
            $participant = Participant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageCredentials', $participant);
            $credential = PatientCredential::whereKey($participant->id)->lockForUpdate()->first() ?? new PatientCredential(['responden_id' => $participant->id]);
            if ($credential->exists) {
                $credential->tokens()->delete();
            }
            $credential->fill(['pin_hash' => $data['pin'], 'issued_by' => $request->user()->id, 'issued_at' => now(), 'revoked_at' => null])->save();
            app(SecurityAudit::class)->record($request, 'credential.issued', 'SUCCESS', $participant->id);

            return response()->json(['message' => 'Credential issued. Previous sessions revoked.'], 201)->header('Cache-Control', 'no-store');
        });
    }

    public function revoke(Request $request, Participant $participant): mixed
    {
        Gate::authorize('manageCredentials', $participant);

        return DB::transaction(function () use ($request, $participant) {
            Participant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            $credential = PatientCredential::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            $credential->tokens()->delete();
            $credential->update(['revoked_at' => now()]);
            app(SecurityAudit::class)->record($request, 'credential.revoked', 'SUCCESS', $participant->id);

            return response()->json(['message' => 'Credential and sessions revoked.']);
        });
    }

    public function revokeStaffTokens(Request $request, StaffUser $staff): mixed
    {
        abort_unless($request->user() instanceof StaffUser && $request->user()->hasRole(ResearchRoleCode::Administrator), 403);

        return DB::transaction(function () use ($request, $staff) {
            StaffUser::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $staff->tokens()->delete();
            app(SecurityAudit::class)->record($request, 'staff.sessions.revoked', 'SUCCESS', (string) $staff->id);

            return response()->json(['message' => 'Staff sessions revoked.']);
        });
    }
}
