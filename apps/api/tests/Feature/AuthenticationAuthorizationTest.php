<?php

namespace Tests\Feature;

use App\Auth\ResearchRoleCode;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\ClassificationRecord;
use App\Models\DailyEntry;
use App\Models\FollowUp;
use App\Models\Outcome;
use App\Models\Participant;
use App\Models\ParticipantAssignment;
use App\Models\ParticipantContact;
use App\Models\PatientCredential;
use App\Models\ResearchRole;
use App\Models\StaffUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationAuthorizationTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Authentication security suite requires disposable MySQL.');
        }
        if (! preg_match('/^[a-z0-9_]+_test$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use a disposable database ending in _test.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        DB::beginTransaction();
        $this->transactionStarted = true;
        config(['research_auth.blinded_assessment' => true]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function staff(ResearchRoleCode ...$roles): StaffUser
    {
        $user = StaffUser::factory()->create();
        foreach ($roles as $role) {
            $user->roles()->attach(ResearchRole::firstOrCreate(['kode' => $role->value]));
        }

        return $user;
    }

    private function token(StaffUser|PatientCredential $actor): string
    {
        return $actor->createToken('synthetic-test', ['api'], now()->addHour())->plainTextToken;
    }

    private function callAs(?string $token, string $method, string $path, array $data = [])
    {
        Auth::forgetGuards();

        return $this->json($method, '/api/v1'.$path, $data, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function loginPatient(PatientCredential $credential, string $pin = '012345')
    {
        return $this->callAs(null, 'POST', '/auth/patient/login', ['code' => $credential->participant->kode, 'pin' => $pin]);
    }

    public function test_staff_login_issues_hashed_expiring_sanctum_token_and_no_cookie(): void
    {
        $staff = $this->staff(ResearchRoleCode::Researcher);
        $response = $this->callAs(null, 'POST', '/auth/staff/login', ['email' => $staff->email, 'password' => 'synthetic-test-only-not-login'])
            ->assertOk()->assertJsonPath('token_type', 'Bearer')->assertHeader('Cache-Control', 'no-store, private');
        $token = $response->json('access_token');
        $stored = PersonalAccessToken::findToken($token);
        $this->assertSame('staff', $stored->tokenable_type);
        $this->assertNotSame($token, $stored->token);
        $this->assertNotNull($stored->expires_at);
        $this->assertFalse($response->headers->has('Set-Cookie'));
        $this->callAs($token, 'GET', '/auth/me')->assertOk()->assertJsonPath('data.kind', 'staff')->assertJsonPath('data.roles.0', 'PENELITI_UTAMA');
        $this->assertDatabaseHas('audit_log', ['aksi' => 'login.success', 'actor_id' => $staff->id]);
    }

    public function test_patient_pin_keeps_leading_zero_is_hashed_and_credentials_are_separate(): void
    {
        $credential = PatientCredential::factory()->create();
        $this->assertTrue(Hash::check('012345', $credential->fresh()->pin_hash));
        $this->assertNotSame('012345', DB::table('patient_credentials')->value('pin_hash'));
        $this->assertArrayNotHasKey('pin_hash', $credential->toArray());
        $response = $this->loginPatient($credential)->assertOk();
        $token = $response->json('access_token');
        $this->assertSame('patient', PersonalAccessToken::findToken($token)->tokenable_type);
        $this->callAs($token, 'GET', '/auth/me')->assertOk()->assertJsonPath('data.roles.0', 'PASIEN_KELUARGA');
        $this->callAs(null, 'POST', '/auth/staff/login', ['email' => $credential->issuer->email, 'password' => '012345'])->assertUnauthorized();
    }

    #[DataProvider('protectedResources')]
    public function test_unauthenticated_requests_are_rejected(string $method, string $path): void
    {
        $participant = Participant::factory()->create();
        $this->callAs(null, $method, str_replace('{id}', $participant->id, $path))->assertUnauthorized();
        $this->assertDatabaseHas('audit_log', ['aksi' => 'permission.denied']);
    }

    public static function protectedResources(): array
    {
        return array_map(fn ($pair) => $pair, [
            ['GET', '/auth/me'], ['POST', '/auth/logout'], ['POST', '/auth/logout-all'],
            ['GET', '/participants'], ['GET', '/participants/{id}'], ['GET', '/participants/{id}/contact'],
            ['GET', '/participants/{id}/entries'], ['GET', '/participants/{id}/alerts'], ['GET', '/participants/{id}/classifications'],
            ['GET', '/participants/{id}/follow-ups'], ['GET', '/participants/{id}/outcome'],
            ['POST', '/participants/{id}/credentials'], ['DELETE', '/participants/{id}/credentials'],
            ['POST', '/participants/{id}/identified-access'], ['DELETE', '/participants/{id}/identified-access/1'], ['DELETE', '/staff/1/tokens'],
        ]);
    }

    public function test_patient_cannot_access_another_participant_or_staff_only_resources(): void
    {
        $credential = PatientCredential::factory()->create();
        $other = Participant::factory()->create();
        $token = $this->loginPatient($credential)->assertOk()->json('access_token');
        $own = $credential->responden_id;
        foreach (['', '/contact', '/entries', '/classifications', '/outcome'] as $path) {
            $this->callAs($token, 'GET', '/participants/'.$own.$path)->assertOk();
            $this->callAs($token, 'GET', '/participants/'.$other->id.$path)->assertForbidden();
        }
        foreach (['alerts', 'follow-ups'] as $path) {
            $this->callAs($token, 'GET', '/participants/'.$own.'/'.$path)->assertForbidden();
        }
        $this->callAs($token, 'GET', '/participants')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own)->assertJsonPath('meta.total', 1);
        $this->callAs($token, 'POST', '/participants/'.$own.'/credentials', ['pin' => '123456'])->assertForbidden();
        $this->callAs($token, 'DELETE', '/staff/'.$credential->issued_by.'/tokens')->assertForbidden();
    }

    #[DataProvider('nurseRoles')]
    public function test_nurses_need_active_matching_assignments(ResearchRoleCode $role, string $kind): void
    {
        $nurse = $this->staff($role);
        $token = $this->token($nurse);
        $assigned = Participant::factory()->create();
        $unassigned = Participant::factory()->create();
        $control = Participant::factory()->control()->create();
        $assignment = ParticipantAssignment::factory()->create(['pengguna_id' => $nurse->id, 'responden_id' => $assigned->id, 'jenis' => $kind, 'mulai_pada' => now()->subMinute(), 'berakhir_pada' => now()->addHour()]);
        ParticipantAssignment::factory()->create(['pengguna_id' => $nurse->id, 'responden_id' => $control->id, 'jenis' => $kind, 'mulai_pada' => now()->subMinute()]);
        foreach (['', '/contact', '/entries', '/alerts', '/classifications', '/follow-ups', '/outcome'] as $path) {
            $this->callAs($token, 'GET', '/participants/'.$assigned->id.$path)->assertOk();
            $this->callAs($token, 'GET', '/participants/'.$unassigned->id.$path)->assertForbidden();
            $this->callAs($token, 'GET', '/participants/'.$control->id.$path)->assertForbidden();
        }
        $this->callAs($token, 'GET', '/participants')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
        $assignment->update(['berakhir_pada' => now()->subSecond()]);
        $this->callAs($token, 'GET', '/participants/'.$assigned->id)->assertForbidden();
        $assignment->update(['berakhir_pada' => null, 'mulai_pada' => now()->addHour()]);
        $this->callAs($token, 'GET', '/participants/'.$assigned->id)->assertForbidden();
    }

    public static function nurseRoles(): array
    {
        return [[ResearchRoleCode::Nurse, 'PEMANTAU'], [ResearchRoleCode::BackupNurse, 'CADANGAN']];
    }

    public function test_backup_role_does_not_inherit_monitoring_nurse_assignments(): void
    {
        $staff = $this->staff(ResearchRoleCode::BackupNurse);
        $assignment = ParticipantAssignment::factory()->create(['pengguna_id' => $staff->id, 'jenis' => 'PEMANTAU', 'mulai_pada' => now()->subMinute()]);
        $this->callAs($this->token($staff), 'GET', '/participants/'.$assignment->responden_id)->assertForbidden();
    }

    public function test_blinded_assessor_cannot_read_monitoring_or_contacts_even_with_another_role(): void
    {
        $assessor = $this->staff(ResearchRoleCode::Assessor, ResearchRoleCode::Researcher);
        $token = $this->token($assessor);
        foreach (['INTERVENSI', 'KONTROL'] as $group) {
            $participant = Participant::factory()->create(['kelompok' => $group]);
            Outcome::factory()->create(['responden_id' => $participant->id]);
            $this->callAs($token, 'GET', '/participants/'.$participant->id)->assertOk()->assertJsonMissingPath('data.kelompok')->assertJsonMissingPath('data.kode')->assertJsonMissingPath('data.tanggal_pulang');
            $this->callAs($token, 'GET', '/participants/'.$participant->id.'/outcome')->assertOk();
            foreach (['contact', 'entries', 'classifications', 'alerts', 'follow-ups'] as $path) {
                $this->callAs($token, 'GET', '/participants/'.$participant->id.'/'.$path)->assertForbidden();
            }
        }
        $this->callAs($token, 'GET', '/participants')->assertOk()->assertJsonMissingPath('data.0.kelompok');
        config(['research_auth.blinded_assessment' => false]);
        $this->callAs($token, 'GET', '/participants/'.$participant->id.'/alerts')->assertOk();
        $this->callAs($token, 'GET', '/participants/'.$participant->id.'/contact')->assertForbidden();
    }

    public function test_administrator_cannot_bypass_clinical_restrictions_with_combined_roles_or_self_grant(): void
    {
        $admin = $this->staff(ResearchRoleCode::Administrator, ResearchRoleCode::Researcher);
        $participant = Participant::factory()->create();
        $token = $this->token($admin);
        foreach (['', '/contact', '/entries', '/classifications', '/alerts', '/follow-ups', '/outcome'] as $path) {
            $this->callAs($token, 'GET', '/participants/'.$participant->id.$path)->assertForbidden();
        }
        $this->callAs($token, 'GET', '/participants')->assertOk()->assertJsonCount(0, 'data');
        $this->callAs($token, 'POST', '/participants/'.$participant->id.'/identified-access', ['staff_id' => $admin->id, 'purpose_code' => 'SYNTHETIC_TEST', 'expires_at' => now()->addHour()->toISOString()])->assertForbidden();
        $this->callAs($token, 'POST', '/participants/'.$participant->id.'/credentials', ['pin' => '012345'])->assertForbidden();
    }

    public function test_separately_authorized_it_access_is_participant_scoped_expiring_and_revocable(): void
    {
        $researcher = $this->staff(ResearchRoleCode::Researcher);
        $admin = $this->staff(ResearchRoleCode::Administrator);
        $participant = Participant::factory()->create();
        $other = Participant::factory()->create();
        ParticipantContact::factory()->create(['responden_id' => $participant->id]);
        $rToken = $this->token($researcher);
        $aToken = $this->token($admin);
        $grant = $this->callAs($rToken, 'POST', '/participants/'.$participant->id.'/identified-access', ['staff_id' => $admin->id, 'purpose_code' => 'SYNTHETIC_TEST', 'expires_at' => now()->addMinutes(10)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i:sP')])->assertCreated()->json('data.id');
        $this->callAs($aToken, 'GET', '/participants/'.$participant->id.'/contact')->assertOk()->assertJsonPath('data.patient_contact', 'SYNTHETIC-NOT-A-PHONE');
        $this->callAs($aToken, 'GET', '/participants/'.$other->id.'/contact')->assertForbidden();
        $this->callAs($aToken, 'GET', '/participants')->assertJsonCount(1, 'data');
        $this->travel(11)->minutes();
        $this->callAs($aToken, 'GET', '/participants/'.$participant->id)->assertForbidden();
        $this->travelBack();
        $this->callAs($rToken, 'DELETE', '/participants/'.$participant->id.'/identified-access/'.$grant)->assertOk();
        $this->callAs($aToken, 'GET', '/participants/'.$participant->id)->assertForbidden();
        $this->assertDatabaseHas('audit_log', ['aksi' => 'identified.access.granted', 'actor_id' => $researcher->id]);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'privileged.access', 'actor_id' => $admin->id]);
    }

    public function test_researcher_issues_resets_and_revokes_patient_pin_and_control_is_rejected(): void
    {
        $researcher = $this->staff(ResearchRoleCode::Researcher);
        $token = $this->token($researcher);
        $participant = Participant::factory()->create();
        $control = Participant::factory()->control()->create();
        $this->callAs($token, 'POST', '/participants/'.$control->id.'/credentials', ['pin' => '012345'])->assertForbidden();
        $this->callAs($token, 'POST', '/participants/'.$participant->id.'/credentials', ['pin' => '012345'])->assertCreated()->assertJsonMissingPath('pin');
        $credential = PatientCredential::findOrFail($participant->id);
        $oldToken = $this->loginPatient($credential)->assertOk()->json('access_token');
        $this->callAs($token, 'POST', '/participants/'.$participant->id.'/credentials', ['pin' => '654321'])->assertCreated();
        $this->callAs($oldToken, 'GET', '/auth/me')->assertUnauthorized();
        $this->loginPatient($credential->fresh(), '012345')->assertUnauthorized();
        $newToken = $this->loginPatient($credential->fresh(), '654321')->assertOk()->json('access_token');
        $this->callAs($token, 'DELETE', '/participants/'.$participant->id.'/credentials')->assertOk();
        $this->callAs($newToken, 'GET', '/auth/me')->assertUnauthorized();
        $this->loginPatient($credential->fresh(), '654321')->assertUnauthorized();
        $this->assertNull(PatientCredential::find($control->id));
        $this->expectException(QueryException::class);
        PatientCredential::factory()->create(['responden_id' => $control->id]);
    }

    #[DataProvider('invalidPins')]
    public function test_only_six_ascii_digit_string_pins_are_accepted(mixed $pin): void
    {
        $credential = PatientCredential::factory()->create();
        $this->callAs(null, 'POST', '/auth/patient/login', ['code' => $credential->participant->kode, 'pin' => $pin])->assertUnprocessable();
    }

    public static function invalidPins(): array
    {
        return [['12345'], ['1234567'], [123456], ['abcdef'], ["012345\n"], ['１２３４５６']];
    }

    public function test_pin_brute_force_is_limited_across_ips_and_application_cache_instances(): void
    {
        $credential = PatientCredential::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($i + 1)]);
            $this->loginPatient($credential, '999999')->assertUnauthorized()->assertExactJson(['message' => 'Invalid credentials.']);
        }
        // Reset cache objects, not the durable counters: limits must survive a worker restart.
        app('cache')->forgetDriver('auth_limits');
        $this->loginPatient($credential)->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGreaterThan(0, DB::table('auth_rate_limits')->count());
        $stored = json_encode(DB::table('auth_rate_limits')->pluck('key'));
        $this->assertStringNotContainsString($credential->participant->kode, $stored);
        $this->travel(16)->minutes();
        $this->loginPatient($credential)->assertOk();
    }

    public function test_failed_logins_do_not_reveal_account_existence_and_staff_rate_limits_apply(): void
    {
        $staff = $this->staff(ResearchRoleCode::Administrator);
        $unknown = $this->callAs(null, 'POST', '/auth/staff/login', ['email' => 'synthetic-missing@example.invalid', 'password' => 'wrong'])->assertUnauthorized()->json();
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($unknown, $this->callAs(null, 'POST', '/auth/staff/login', ['email' => $staff->email, 'password' => 'wrong'])->assertUnauthorized()->json());
        }
        $this->callAs(null, 'POST', '/auth/staff/login', ['email' => $staff->email, 'password' => 'synthetic-test-only-not-login'])->assertStatus(429);
    }

    public function test_logout_revocation_expiration_inactive_principals_and_role_changes_are_effective(): void
    {
        $staff = $this->staff(ResearchRoleCode::Researcher);
        $a = $this->token($staff);
        $b = $this->token($staff);
        $this->callAs($a, 'POST', '/auth/logout')->assertOk();
        $this->callAs($a, 'GET', '/auth/me')->assertUnauthorized();
        $this->callAs($b, 'GET', '/auth/me')->assertOk();
        $this->callAs($b, 'POST', '/auth/logout-all')->assertOk();
        $this->callAs($b, 'GET', '/auth/me')->assertUnauthorized();
        $expired = $staff->createToken('synthetic-expired', ['api'], now()->subSecond())->plainTextToken;
        $this->callAs($expired, 'GET', '/auth/me')->assertUnauthorized();
        $current = $this->token($staff);
        $staff->update(['status' => 'NONAKTIF']);
        $this->callAs($current, 'GET', '/auth/me')->assertUnauthorized();
        $staff->update(['status' => 'AKTIF']);
        $current = $this->token($staff);
        $staff->roles()->detach();
        $this->callAs($current, 'GET', '/participants/'.Participant::factory()->create()->id)->assertForbidden();
        $credential = PatientCredential::factory()->create();
        $pToken = $this->token($credential);
        $credential->participant->update(['status' => 'SELESAI']);
        $this->callAs($pToken, 'GET', '/auth/me')->assertUnauthorized();
    }

    public function test_admin_can_revoke_staff_sessions_without_clinical_access(): void
    {
        $admin = $this->staff(ResearchRoleCode::Administrator);
        $staff = $this->staff(ResearchRoleCode::Nurse);
        $token = $this->token($staff);
        $this->callAs($token, 'DELETE', '/staff/'.$admin->id.'/tokens')->assertForbidden();
        $this->callAs($this->token($admin), 'DELETE', '/staff/'.$staff->id.'/tokens')->assertOk();
        $this->callAs($token, 'GET', '/auth/me')->assertUnauthorized();
    }

    public function test_authorized_researcher_reads_are_projected_and_audited_without_clinical_engine(): void
    {
        $entry = DailyEntry::factory()->create();
        $classification = ClassificationRecord::factory()->create(['entri_id' => $entry->id]);
        $alert = Alert::factory()->create(['responden_id' => $entry->responden_id, 'entri_id' => $entry->id]);
        $followUp = FollowUp::factory()->create(['peringatan_id' => $alert->id]);
        $token = $this->token($this->staff(ResearchRoleCode::Researcher));
        $prefix = '/participants/'.$entry->responden_id;
        $this->callAs($token, 'GET', $prefix.'/entries')->assertOk()->assertJsonPath('data.0.id', $entry->id);
        $this->callAs($token, 'GET', $prefix.'/classifications')->assertOk()->assertJsonPath('data.0.id', $classification->id);
        $this->callAs($token, 'GET', $prefix.'/alerts')->assertOk()->assertJsonPath('data.0.id', $alert->id);
        $this->callAs($token, 'GET', $prefix.'/follow-ups')->assertOk()->assertJsonPath('data.0.hasil_asesmen', 'SYNTHETIC TEST ONLY');
        $this->assertSame(1, ClassificationRecord::count());
        $this->assertSame(1, Alert::count());
    }

    public function test_ip_limit_blocks_rotating_account_names(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->callAs(null, 'POST', '/auth/patient/login', ['code' => 'SYN-MISSING-'.$i, 'pin' => 'invalid'])->assertUnprocessable();
        }
        $this->callAs(null, 'POST', '/auth/patient/login', ['code' => 'SYN-MISSING-NEXT', 'pin' => 'invalid'])->assertStatus(429);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'login.failed']);
    }

    public function test_patient_logout_all_expiration_and_direct_credential_revocation(): void
    {
        $credential = PatientCredential::factory()->create();
        $a = $this->loginPatient($credential)->assertOk()->json('access_token');
        $b = $this->loginPatient($credential)->assertOk()->json('access_token');
        $this->callAs($a, 'POST', '/auth/logout-all')->assertOk();
        $this->callAs($a, 'GET', '/auth/me')->assertUnauthorized();
        $this->callAs($b, 'GET', '/auth/me')->assertUnauthorized();
        $token = $this->loginPatient($credential)->assertOk()->json('access_token');
        $this->travel(61)->minutes();
        $this->callAs($token, 'GET', '/auth/me')->assertUnauthorized();
        $this->travelBack();
        $token = $this->token($credential);
        $credential->update(['revoked_at' => now()]);
        $this->callAs($token, 'GET', '/auth/me')->assertUnauthorized();
    }

    public function test_bearer_token_cannot_be_supplied_as_query_body_or_cookie_and_cannot_bypass_policy(): void
    {
        $admin = $this->staff(ResearchRoleCode::Administrator);
        $token = $admin->createToken('synthetic-wildcard', ['*'], now()->addHour())->plainTextToken;
        $this->callAs(null, 'GET', '/auth/me?access_token='.urlencode($token))->assertUnauthorized();
        $this->withCookie('access_token', $token);
        $this->callAs(null, 'GET', '/auth/me', ['access_token' => $token])->assertUnauthorized();
        $this->callAs($token, 'GET', '/participants/'.Participant::factory()->create()->id)->assertForbidden();
        $wrongAbility = $admin->createToken('synthetic-wrong-scope', ['other'], now()->addHour())->plainTextToken;
        $this->callAs($wrongAbility, 'GET', '/auth/me')->assertUnauthorized();
    }

    public function test_grants_cannot_override_blinding_or_outlive_researcher_authority(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $admin = $this->staff(ResearchRoleCode::Administrator, ResearchRoleCode::Assessor);
        $participant = Participant::factory()->create();
        $piToken = $this->token($pi);
        $adminToken = $this->token($admin);
        $this->callAs($piToken, 'POST', '/participants/'.$participant->id.'/identified-access', ['staff_id' => $admin->id, 'purpose_code' => 'SYNTHETIC_TEST', 'expires_at' => now()->addDays(2)->toISOString()])->assertUnprocessable();
        $this->callAs($piToken, 'POST', '/participants/'.$participant->id.'/identified-access', ['staff_id' => $admin->id, 'purpose_code' => 'SYNTHETIC_TEST', 'expires_at' => now()->addHour()->toISOString()])->assertCreated();
        $this->callAs($adminToken, 'GET', '/participants/'.$participant->id)->assertOk()->assertJsonMissingPath('data.kelompok');
        foreach (['entries', 'classifications', 'alerts', 'follow-ups', 'contact'] as $resource) {
            $this->callAs($adminToken, 'GET', '/participants/'.$participant->id.'/'.$resource)->assertForbidden();
        }
        $pi->roles()->detach();
        $this->callAs($adminToken, 'GET', '/participants/'.$participant->id)->assertForbidden();
    }

    public function test_disabled_accounts_and_patient_role_on_staff_do_not_authenticate(): void
    {
        $staff = $this->staff(ResearchRoleCode::Researcher);
        $staff->update(['status' => 'NONAKTIF']);
        $this->callAs(null, 'POST', '/auth/staff/login', ['email' => $staff->email, 'password' => 'synthetic-test-only-not-login'])->assertUnauthorized();
        $staff->update(['status' => 'AKTIF']);
        $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => ResearchRoleCode::Patient->value]));
        $this->callAs(null, 'POST', '/auth/staff/login', ['email' => $staff->email, 'password' => 'synthetic-test-only-not-login'])->assertUnauthorized();
        $this->callAs($this->token($staff), 'GET', '/auth/me')->assertUnauthorized();
        $credential = PatientCredential::factory()->create();
        $unknown = $this->callAs(null, 'POST', '/auth/patient/login', ['code' => 'SYN-NONEXISTENT', 'pin' => '999999'])->assertUnauthorized()->json();
        $this->assertSame($unknown, $this->loginPatient($credential, '999999')->assertUnauthorized()->json());
    }

    public function test_staff_login_does_not_accept_collation_aliases_that_would_split_account_rate_limits(): void
    {
        $staff = $this->staff(ResearchRoleCode::Researcher);
        $staff->update(['email' => 'synthetic-user@example.invalid']);
        $this->callAs(null, 'POST', '/auth/staff/login', ['email' => 'sýnthetic-user@example.invalid', 'password' => 'synthetic-test-only-not-login'])->assertUnauthorized();
        $this->callAs(null, 'POST', '/auth/staff/login', ['email' => 'SYNTHETIC-USER@example.invalid', 'password' => 'synthetic-test-only-not-login'])->assertOk();
    }

    public function test_staff_disable_and_password_rotation_revoke_persisted_tokens(): void
    {
        $staff = $this->staff(ResearchRoleCode::Researcher);
        $token = $this->token($staff);
        $staff->update(['status' => 'NONAKTIF']);
        $staff->update(['status' => 'AKTIF']);
        $this->callAs($token, 'GET', '/auth/me')->assertUnauthorized();
        $token = $this->token($staff);
        $staff->update(['password_hash' => 'synthetic-rotated-password']);
        $this->callAs($token, 'GET', '/auth/me')->assertUnauthorized();
        $this->assertSame(0, $staff->tokens()->count());
    }

    public function test_denials_and_errors_remain_sanitized_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        $this->callAs(null, 'GET', '/auth/me')->assertUnauthorized()->assertExactJson(['message' => 'Unauthorized.']);
        $staff = $this->staff(ResearchRoleCode::Administrator);
        $token = $this->token($staff);
        $this->callAs($token, 'GET', '/participants/'.Participant::factory()->create()->id)->assertForbidden()->assertExactJson(['message' => 'Forbidden.']);
        $this->callAs($token, 'GET', '/participants/00000000-0000-0000-0000-000000000000')->assertNotFound()->assertExactJson(['message' => 'Not Found.']);
    }

    public function test_cors_allows_bearer_login_only_for_the_documented_origin_without_cookies(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'Authorization, Content-Type'])
            ->options('/api/v1/auth/staff/login')->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Headers', 'Accept, Content-Type, Authorization')->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_plaintext_pin_is_rejected_even_for_direct_database_updates(): void
    {
        $credential = PatientCredential::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('patient_credentials')->where('responden_id', $credential->responden_id)->update(['pin_hash' => '012345']);
    }

    public function test_console_provisioning_hashes_password_and_has_no_public_registration_route(): void
    {
        $this->artisan('telenapas:create-staff')
            ->expectsQuestion('Staff email', 'synthetic-provisioned@example.invalid')
            ->expectsQuestion('Staff name', 'SYNTHETIC CONSOLE TEST')
            ->expectsChoice('Research role', 'PENELITI_UTAMA', ['PERAWAT_PEMANTAU', 'PERAWAT_CADANGAN', 'PENELITI_UTAMA', 'PENILAI_HASIL', 'ADMIN_IT'])
            ->expectsQuestion('Password (12–72 characters)', 'synthetic-console-password')
            ->expectsQuestion('Confirm password', 'synthetic-console-password')
            ->assertExitCode(0);
        $staff = StaffUser::where('email', 'synthetic-provisioned@example.invalid')->firstOrFail();
        $this->assertTrue(Hash::check('synthetic-console-password', $staff->password_hash));
        $this->assertTrue($staff->hasRole(ResearchRoleCode::Researcher));
        $this->assertDatabaseHas('audit_log', ['aksi' => 'staff.provisioned']);
        $this->callAs(null, 'POST', '/auth/register')->assertNotFound();
    }

    public function test_security_audit_has_no_pins_passwords_codes_contacts_tokens_or_request_payloads(): void
    {
        $credential = PatientCredential::factory()->create();
        $token = $this->loginPatient($credential)->assertOk()->json('access_token');
        $this->loginPatient($credential, '999999')->assertUnauthorized();
        $this->callAs($token, 'GET', '/participants/'.Participant::factory()->create()->id)->assertForbidden();
        $this->callAs($token, 'POST', '/auth/logout')->assertOk();
        $json = AuditLog::all()->toJson();
        foreach (['012345', '999999', $token, $credential->participant->kode, 'SYNTHETIC-NOT-A-PHONE', 'synthetic-test-only-not-login'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, $json);
        }
        foreach (['login.success', 'login.failed', 'permission.denied', 'logout'] as $action) {
            $this->assertDatabaseHas('audit_log', ['aksi' => $action]);
        }
        $this->expectException(QueryException::class);
        AuditLog::query()->update(['aksi' => 'tampered']);
    }
}
