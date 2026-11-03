<?php

namespace Tests\Feature;

use App\Auth\ResearchRoleCode;
use App\Auth\SecurityAudit;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\DailyEntry;
use App\Models\IdentifiedAccessGrant;
use App\Models\Outcome;
use App\Models\Participant;
use App\Models\ParticipantContact;
use App\Models\PatientCredential;
use App\Models\ResearchRole;
use App\Models\StaffUser;
use App\Models\UsabilityResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResearchEnrollmentTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Enrollment tests require disposable MySQL.');
        }
        if (! preg_match('/^[a-z0-9_]+_test$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use a dedicated disposable database ending in _test.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        DB::beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function staff(ResearchRoleCode ...$roles): StaffUser
    {
        $staff = StaffUser::factory()->create();
        foreach ($roles as $role) {
            $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => $role->value]));
        }

        return $staff;
    }

    private function callAs(StaffUser|PatientCredential|null $actor, string $method, string $path, array $body = [])
    {
        Auth::forgetGuards();
        $token = $actor?->createToken('synthetic-enrollment-test', ['api'], now()->addHour())->plainTextToken;

        return $this->json($method, '/api/v1/research'.$path, $body, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function input(): array
    {
        return ['kode' => 'SYN-ENROLL-001', 'kelompok' => 'INTERVENSI', 'tanggal_pulang' => '2026-01-01', 'vas_baseline_mm' => 0, 'tanggal_consent' => '2026-01-01', 'versi_consent' => 'SYNTHETIC-v1'];
    }

    private function version(Participant $participant): array
    {
        return ['expected_updated_at' => $participant->fresh()->updated_at->toISOString()];
    }

    public function test_researcher_enrolls_both_groups_with_encrypted_optional_contacts_and_no_app_credentials(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $this->callAs($pi, 'GET', '/access')->assertOk();
        foreach (['INTERVENSI', 'KONTROL'] as $index => $group) {
            $body = array_replace($this->input(), ['kode' => 'SYN-GROUP-'.$index, 'kelompok' => $group, 'vas_baseline_mm' => $index === 0 ? 0 : 100, 'contact' => ['initials' => 'SYN', 'patient_contact' => 'SYNTHETIC-NOT-A-PHONE']]);
            $result = $this->callAs($pi, 'POST', '/participants', $body)->assertCreated()->assertJsonPath('data.kelompok', $group)->assertJsonPath('data.vas_baseline_mm', $index === 0 ? 0 : 100)->assertJsonPath('data.contact.patient_contact', 'SYNTHETIC-NOT-A-PHONE')->assertJsonMissingPath('data.pin_hash');
            $id = $result->json('data.id');
            $this->assertNotSame('SYNTHETIC-NOT-A-PHONE', DB::table('kontak_responden')->where('responden_id', $id)->value('no_hp_pasien_encrypted'));
            $this->assertSame('SYNTHETIC-NOT-A-PHONE', ParticipantContact::findOrFail($id)->no_hp_pasien_encrypted);
            $this->assertFalse(PatientCredential::whereKey($id)->exists());
        }
        $this->assertSame(0, DailyEntry::count());
        $this->assertSame(0, Alert::count());
        $this->assertSame(2, AuditLog::where('aksi', 'participant.enrolled')->count());
    }

    #[DataProvider('invalidEnrollment')]
    public function test_invalid_enrollment_is_rejected_without_partial_records(array $override): void
    {
        $this->callAs($this->staff(ResearchRoleCode::Researcher), 'POST', '/participants', array_replace($this->input(), $override))->assertUnprocessable();
        $this->assertSame(0, Participant::count());
        $this->assertSame(0, ParticipantContact::count());
        $this->assertSame(0, AuditLog::where('aksi', 'participant.enrolled')->count());
    }

    public static function invalidEnrollment(): array
    {
        return [
            [['vas_baseline_mm' => -1]], [['vas_baseline_mm' => 101]], [['vas_baseline_mm' => 50.5]], [['vas_baseline_mm' => '50']], [['vas_baseline_mm' => true]], [['vas_baseline_mm' => null]],
            [['kode' => '']], [['kode' => str_repeat('S', 33)]], [['kelompok' => 'OTHER']], [['tanggal_pulang' => '2026-02-30']], [['tanggal_consent' => 'not-a-date']], [['versi_consent' => '']],
            [['status' => 'DROP_OUT']], [['status' => 'DROP_OUT', 'alasan_drop_out' => '   ']], [['status' => 'OTHER']],
            [['contact' => ['unexpected' => 'SYNTHETIC']]], [['contact' => ['patient_contact' => str_repeat('S', 41)]]], [['pin' => '012345']], [['credentials' => ['pin' => '012345']]],
        ];
    }

    public function test_duplicate_study_codes_are_rejected_without_echoing_the_code(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $this->callAs($pi, 'POST', '/participants', $this->input())->assertCreated();
        $error = $this->callAs($pi, 'POST', '/participants', $this->input())->assertUnprocessable();
        $this->assertStringNotContainsString('SYN-ENROLL-001', $error->getContent());
        $this->assertSame(1, Participant::count());
    }

    #[DataProvider('deniedRoles')]
    public function test_non_researchers_cannot_use_any_enrollment_endpoint(array $roles): void
    {
        $actor = $this->staff(...$roles);
        $participant = Participant::factory()->create();
        $this->assertDenied($actor, $participant, 403);
    }

    public static function deniedRoles(): array
    {
        return [
            [[]], [[ResearchRoleCode::Nurse]], [[ResearchRoleCode::BackupNurse]], [[ResearchRoleCode::Assessor]], [[ResearchRoleCode::Administrator]],
            [[ResearchRoleCode::Administrator, ResearchRoleCode::Researcher]], [[ResearchRoleCode::Assessor, ResearchRoleCode::Researcher]],
        ];
    }

    public function test_patients_and_unauthenticated_requests_cannot_change_or_read_enrollment(): void
    {
        $credential = PatientCredential::factory()->create();
        $this->assertDenied($credential, $credential->participant, 403);
        $this->assertDenied(null, $credential->participant, 401);
    }

    private function assertDenied(StaffUser|PatientCredential|null $actor, Participant $participant, int $status): void
    {
        $before = $participant->fresh()->getRawOriginal();
        $this->callAs($actor, 'GET', '/access')->assertStatus($status);
        $this->callAs($actor, 'POST', '/participants/search')->assertStatus($status);
        $this->callAs($actor, 'POST', '/participants', $this->input())->assertStatus($status);
        $this->callAs($actor, 'GET', '/participants/'.$participant->id)->assertStatus($status);
        $this->callAs($actor, 'PATCH', '/participants/'.$participant->id, ['vas_baseline_mm' => 70] + $this->version($participant))->assertStatus($status);
        $this->assertSame($before, $participant->fresh()->getRawOriginal());
    }

    public function test_all_it_read_scopes_do_not_grant_enrollment_authority(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $admin = $this->staff(ResearchRoleCode::Administrator);
        $participant = Participant::factory()->create();
        $grant = IdentifiedAccessGrant::create(['pengguna_id' => $admin->id, 'responden_id' => $participant->id, 'approved_by' => $pi->id, 'purpose_code' => 'SYNTHETIC', 'starts_at' => now()->subMinute(), 'expires_at' => now()->addHour()]);
        foreach (['TECHNICAL', 'CONTACT', 'CLINICAL_READ'] as $scope) {
            $grant->scopes()->create(['scope' => $scope]);
        }
        $this->assertDenied($admin, $participant, 403);
    }

    public function test_search_filters_pagination_literal_wildcards_and_counts(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        Participant::factory()->count(27)->create(['kelompok' => 'INTERVENSI']);
        $control = Participant::factory()->control()->create(['kode' => 'SYN-LITERAL_%', 'status' => 'SELESAI']);
        $first = $this->callAs($pi, 'POST', '/participants/search')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 28)->assertJsonPath('meta.intervensi', 27)->assertJsonPath('meta.kontrol', 1)->assertJsonPath('meta.aktif', 27)->assertJsonPath('meta.last_page', 2);
        $second = $this->callAs($pi, 'POST', '/participants/search', ['page' => 2])->assertOk()->assertJsonCount(3, 'data');
        $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->callAs($pi, 'POST', '/participants/search', ['search' => '_%', 'group' => 'KONTROL', 'status' => 'SELESAI'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $control->id)->assertJsonPath('meta.total', 1);
        $this->callAs($pi, 'POST', '/participants/search', ['group' => 'KONTROL', 'status' => 'AKTIF'])->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        $this->callAs($pi, 'POST', '/participants/search', ['group' => 'OTHER'])->assertUnprocessable();
        $this->callAs($pi, 'POST', '/participants/search', ['page' => 0])->assertUnprocessable();
        $this->assertArrayNotHasKey('contact', $first->json('data.0'));
        $this->assertArrayNotHasKey('alasan_drop_out', $first->json('data.0'));
    }

    public function test_researcher_updates_baseline_before_history_and_status_with_drop_out_reason(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $participant = Participant::factory()->create();
        $result = $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['vas_baseline_mm' => 69, 'tanggal_pulang' => '2026-01-02', 'contact' => ['family_contact' => 'SYNTHETIC-FAMILY']] + $this->version($participant))->assertOk()->assertJsonPath('data.vas_baseline_mm', 69)->assertJsonPath('data.tanggal_pulang', '2026-01-02');
        $this->assertNotSame('SYNTHETIC-FAMILY', DB::table('kontak_responden')->value('no_hp_keluarga_encrypted'));
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['status' => 'DROP_OUT'] + $this->version($participant))->assertUnprocessable();
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['status' => 'DROP_OUT', 'alasan_drop_out' => 'SYNTHETIC REASON'] + $this->version($participant))->assertOk();
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['versi_consent' => 'SYNTHETIC-v2'] + $this->version($participant))->assertOk()->assertJsonPath('data.alasan_drop_out', 'SYNTHETIC REASON');
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['status' => 'SELESAI'] + $this->version($participant))->assertOk()->assertJsonPath('data.alasan_drop_out', null);
        $this->assertNotSame($result->json('data.updated_at'), $participant->fresh()->updated_at->toISOString());
    }

    public function test_group_and_code_are_immutable_including_null_or_same_value_attempts(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $participant = Participant::factory()->control()->create();
        foreach ([['kelompok' => 'INTERVENSI'], ['kelompok' => null], ['kelompok' => 'KONTROL'], ['kode' => 'SYN-RENAME'], ['kode' => null]] as $change) {
            $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, $change + $this->version($participant))->assertUnprocessable();
        }
        $this->assertSame('KONTROL', $participant->fresh()->kelompok);
        $this->assertSame(0, PatientCredential::count());
    }

    #[DataProvider('historyKinds')]
    public function test_baseline_and_day_zero_are_locked_after_any_research_history(string $kind): void
    {
        $participant = Participant::factory()->create();
        match ($kind) {
            'entry' => DailyEntry::factory()->create(['responden_id' => $participant->id]),
            'alert' => Alert::factory()->create(['responden_id' => $participant->id]),
            'outcome' => Outcome::factory()->create(['responden_id' => $participant->id]),
            'usability' => UsabilityResponse::factory()->create(['responden_id' => $participant->id]),
        };
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $this->callAs($pi, 'GET', '/participants/'.$participant->id)->assertJsonPath('data.baseline_locked', true);
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['vas_baseline_mm' => 75] + $this->version($participant))->assertUnprocessable();
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['tanggal_pulang' => '2026-01-03'] + $this->version($participant))->assertUnprocessable();
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['status' => 'SELESAI'] + $this->version($participant))->assertOk();
        $this->assertSame(0, $participant->fresh()->vas_baseline_mm);
        $this->assertSame('2026-01-01', $participant->fresh()->tanggal_pulang->toDateString());
    }

    public static function historyKinds(): array
    {
        return [['entry'], ['alert'], ['outcome'], ['usability']];
    }

    public function test_stale_update_is_rejected_and_contact_changes_advance_record_version(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $participant = Participant::factory()->create();
        $original = $this->version($participant);
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['contact' => ['initials' => 'SYN']] + $original)->assertOk();
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['vas_baseline_mm' => 50] + $original)->assertStatus(409);
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['vas_baseline_mm' => 50])->assertUnprocessable();
        $this->assertSame(0, $participant->fresh()->vas_baseline_mm);
    }

    public function test_legacy_null_timestamp_has_a_safe_first_update_and_cannot_be_reused(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $participant = Participant::factory()->create();
        DB::table('responden')->where('id', $participant->id)->update(['updated_at' => null]);
        $this->callAs($pi, 'GET', '/participants/'.$participant->id)->assertJsonPath('data.updated_at', null);
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['status' => 'SELESAI', 'expected_updated_at' => null])->assertOk();
        $this->assertNotNull($participant->fresh()->updated_at);
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['status' => 'AKTIF', 'expected_updated_at' => null])->assertStatus(409);
    }

    public function test_inactive_participant_status_revokes_prior_patient_tokens_even_after_reactivation(): void
    {
        $credential = PatientCredential::factory()->create();
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $token = $credential->createToken('synthetic-patient', ['api'], now()->addHour())->plainTextToken;
        $participant = $credential->participant;
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['status' => 'SELESAI'] + $this->version($participant))->assertOk();
        $this->assertSame(0, $credential->tokens()->count());
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['status' => 'AKTIF'] + $this->version($participant))->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    public function test_zero_search_is_not_treated_as_an_empty_filter(): void
    {
        Participant::factory()->create(['kode' => 'SYN-ZERO-0']);
        Participant::factory()->create(['kode' => 'SYN-NONE']);
        $this->callAs($this->staff(ResearchRoleCode::Researcher), 'POST', '/participants/search', ['search' => '0'])->assertJsonCount(1, 'data')->assertJsonPath('data.0.kode', 'SYN-ZERO-0');
    }

    public function test_audit_and_storage_failures_roll_back_without_logging_sensitive_values(): void
    {
        $pi = $this->staff(ResearchRoleCode::Researcher);
        $body = $this->input() + ['contact' => ['patient_contact' => 'SYNTHETIC-CONTACT']];
        $this->callAs($pi, 'POST', '/participants', $body)->assertCreated();
        $participant = Participant::firstOrFail();
        $this->callAs($pi, 'PATCH', '/participants/'.$participant->id, ['status' => 'DROP_OUT', 'alasan_drop_out' => 'SYNTHETIC PRIVATE REASON'] + $this->version($participant))->assertOk();
        $events = AuditLog::whereIn('aksi', ['participant.enrolled', 'participant.updated'])->get();
        $this->assertCount(2, $events);
        foreach ($events as $event) {
            $this->assertNull($event->entity_id);
            $this->assertSame(['outcome_code' => 'SUCCESS'], $event->metadata_redacted);
        }
        foreach ([$participant->id, $participant->kode, 'SYNTHETIC-CONTACT', 'SYNTHETIC PRIVATE REASON'] as $secret) {
            $this->assertStringNotContainsString($secret, $events->toJson());
        }
        Log::spy();
        $failure = new QueryException('mysql', 'INSERT synthetic secret SQL', ['SYNTHETIC-CONTACT'], new \PDOException('SYNTHETIC-CONTACT'));
        $this->instance(SecurityAudit::class, Mockery::mock(SecurityAudit::class)->shouldReceive('record')->once()->andThrow($failure)->getMock());
        $this->callAs($pi, 'POST', '/participants', array_replace($body, ['kode' => 'SYN-ROLLBACK']))->assertStatus(503)->assertExactJson(['message' => 'Service Unavailable.']);
        $this->assertSame(1, Participant::count());
        $this->assertSame(1, ParticipantContact::count());
        Log::shouldNotHaveReceived('error');
    }
}
