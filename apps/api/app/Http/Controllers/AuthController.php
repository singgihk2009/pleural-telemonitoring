<?php

namespace App\Http\Controllers;

use App\Auth\ResearchRoleCode;
use App\Auth\SecurityAudit;
use App\Models\Participant;
use App\Models\PatientCredential;
use App\Models\StaffUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function staffLogin(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:190'], 'password' => ['required', 'string', 'max:72', function ($attribute, $value, $fail) {
            if (strlen($value) > 72) {
                $fail('The password must not exceed 72 bytes.');
            }
        }]]);

        $dummy = $this->dummyHash();

        return DB::transaction(function () use ($request, $data, $dummy) {
            $staff = StaffUser::where('email', mb_strtolower(trim($data['email'])))->lockForUpdate()->first();
            $correct = Hash::check($data['password'], $staff?->password_hash ?? $dummy);
            if (! $correct || ! $staff || mb_strtolower(trim($staff->email)) !== mb_strtolower(trim($data['email'])) || $staff->status !== 'AKTIF' || $staff->hasRole(ResearchRoleCode::Patient)) {
                return $this->invalid($request);
            }
            if (Hash::needsRehash($staff->password_hash)) {
                $staff->update(['password_hash' => $data['password']]);
            }

            return $this->issue($request, $staff);
        });
    }

    public function patientLogin(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32'], 'pin' => ['required', 'string', 'regex:/\A[0-9]{6}\z/']]);

        $dummy = $this->dummyHash();

        return DB::transaction(function () use ($request, $data, $dummy) {
            // Same lock order as credential reset/revocation: participant, credential, token.
            $participant = Participant::where('kode', $data['code'])->lockForUpdate()->first();
            $credential = $participant ? PatientCredential::whereKey($participant->id)->lockForUpdate()->first() : null;
            $correct = Hash::check($data['pin'], $credential?->pin_hash ?? $dummy);
            if (! $correct || ! $credential || $credential->revoked_at !== null || $participant->kelompok !== 'INTERVENSI' || $participant->status !== 'AKTIF') {
                return $this->invalid($request);
            }
            if (Hash::needsRehash($credential->pin_hash)) {
                $credential->update(['pin_hash' => $data['pin']]);
            }

            return $this->issue($request, $credential);
        });
    }

    public function me(Request $request): JsonResponse
    {
        $actor = $request->user();

        return response()->json(['data' => [
            'id' => (string) $actor->getKey(),
            'kind' => $actor instanceof StaffUser ? 'staff' : 'patient',
            'roles' => $actor instanceof StaffUser ? $actor->roleCodes() : [ResearchRoleCode::Patient->value],
            ...($actor instanceof PatientCredential ? ['credential_revision' => $actor->revision] : []),
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $request->user()->currentAccessToken()->delete();
            app(SecurityAudit::class)->record($request, 'logout', 'SUCCESS');

            return response()->json(['message' => 'Logged out.']);
        });
    }

    public function logoutAll(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            // Serialize with login/credential issuance using the same principal row lock.
            $actor = $request->user();
            if ($actor instanceof PatientCredential) {
                Participant::whereKey($actor->responden_id)->lockForUpdate()->firstOrFail();
            }
            $actor->newQuery()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $actor->tokens()->delete();
            app(SecurityAudit::class)->record($request, 'logout.all', 'SUCCESS');

            return response()->json(['message' => 'All sessions revoked.']);
        });
    }

    private function dummyHash(): string
    {
        // Same configured hasher/cost for unknown accounts; shared cache avoids extra
        // hash generation per failed request. This hash never belongs to an account.
        $key = 'auth-dummy-'.hash('sha256', json_encode(config('hashing')));

        return Cache::store('auth_limits')->rememberForever($key, fn () => Hash::make(bin2hex(random_bytes(32))));
    }

    private function issue(Request $request, StaffUser|PatientCredential $actor): JsonResponse
    {
        $expires = now()->addMinutes(config('research_auth.token_minutes'));
        $token = $actor->createToken('research-api', ['api'], $expires);
        $request->setUserResolver(fn () => $actor);
        app(SecurityAudit::class)->record($request, 'login.success', 'SUCCESS', (string) $actor->getKey());

        return response()->json(['token_type' => 'Bearer', 'access_token' => $token->plainTextToken, 'expires_at' => $expires->toISOString()])
            ->header('Cache-Control', 'no-store')->header('Pragma', 'no-cache');
    }

    private function invalid(Request $request): JsonResponse
    {
        app(SecurityAudit::class)->record($request, 'login.failed', 'INVALID_CREDENTIALS');

        return response()->json(['message' => 'Invalid credentials.'], 401)->header('Cache-Control', 'no-store');
    }
}
