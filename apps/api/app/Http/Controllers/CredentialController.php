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
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CredentialController extends Controller
{
    public function status(Request $request, Participant $participant): mixed
    {
        Gate::authorize('manageCredentials', $participant);

        // One indexed, consistent snapshot. No credential hash, row lock or audit write.
        $state = DB::table('responden as p')->leftJoin('patient_credentials as c', 'c.responden_id', '=', 'p.id')
            ->where('p.id', $participant->id)->where('p.kelompok', 'INTERVENSI')->where('p.status', 'AKTIF')
            ->first(['c.responden_id', 'c.revoked_at', 'c.revision']);
        abort_unless($state, 403);
        $status = ! $state->responden_id ? 'BELUM_DITERBITKAN' : ($state->revoked_at === null ? 'AKTIF' : 'DICABUT');

        return response()->json(['data' => ['status' => $status, 'revision' => $state->revision]])->header('Cache-Control', 'no-store');
    }

    public function issue(Request $request, Participant $participant): mixed
    {
        Gate::authorize('manageCredentials', $participant);
        $data = $request->validate(['pin' => ['required', 'string', 'regex:/\A[0-9]{6}\z/'], 'expected_revision' => ['present', 'nullable', 'uuid']]);

        return DB::transaction(function () use ($request, $participant, $data) {
            $participant = Participant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageCredentials', $participant);
            $credential = PatientCredential::whereKey($participant->id)->lockForUpdate()->first() ?? new PatientCredential(['responden_id' => $participant->id]);
            abort_if($credential->revision !== $data['expected_revision'], 409, 'Credential changed. Reload status before retrying.');
            $reset = $credential->exists;
            if ($reset) {
                if (Hash::check($data['pin'], $credential->pin_hash)) {
                    throw ValidationException::withMessages(['pin' => ['Choose a different PIN.']]);
                }
                $credential->tokens()->delete();
            }
            $credential->fill(['pin_hash' => $data['pin'], 'issued_by' => $request->user()->id, 'issued_at' => now(), 'revoked_at' => null])->save();
            app(SecurityAudit::class)->record($request, $reset ? 'credential.reset' : 'credential.issued', 'SUCCESS', $participant->id);

            return response()->json(['message' => 'Credential issued. Previous sessions revoked.', 'data' => ['revision' => $credential->revision]], 201)->header('Cache-Control', 'no-store');
        });
    }

    public function revoke(Request $request, Participant $participant): mixed
    {
        Gate::authorize('manageCredentials', $participant);

        $data = $request->validate(['expected_revision' => ['present', 'nullable', 'uuid']]);

        return DB::transaction(function () use ($request, $participant, $data) {
            $participant = Participant::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('manageCredentials', $participant);
            $credential = PatientCredential::whereKey($participant->id)->lockForUpdate()->firstOrFail();
            abort_if($credential->revision !== $data['expected_revision'], 409, 'Credential changed. Reload status before retrying.');
            $credential->tokens()->delete();
            $credential->revoked_at = now();
            $credential->save();
            app(SecurityAudit::class)->record($request, 'credential.revoked', 'SUCCESS', $participant->id);

            return response()->json(['message' => 'Credential and sessions revoked.', 'data' => ['revision' => $credential->revision]])->header('Cache-Control', 'no-store');
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
