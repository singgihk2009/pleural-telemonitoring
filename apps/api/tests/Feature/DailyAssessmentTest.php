<?php

namespace Tests\Feature;

use App\Auth\SecurityAudit;
use App\Models\AuditLog;
use App\Models\DailyEntry;
use App\Models\IdentifiedAccessGrant;
use App\Models\Participant;
use App\Models\ParticipantAssignment;
use App\Models\PatientCredential;
use App\Models\ResearchRole;
use App\Models\StaffUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\SyntheticDailyInstrument;
use Tests\TestCase;

class DailyAssessmentTest extends TestCase
{
    private bool $transaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Daily assessment requires disposable MySQL.');
        }
        if (! preg_match('/^[a-z0-9_]+_test$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use a disposable _test schema.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        DB::beginTransaction();
        $this->transaction = true;
        $this->travelTo(now()->setDate(2026, 11, 7)->setTime(5, 0));
        config(['research_auth.blinded_assessment' => true]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        if ($this->transaction) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function patient(): PatientCredential
    {
        return PatientCredential::factory()->create(['responden_id' => Participant::factory()->create(['tanggal_pulang' => '2026-11-06'])->id]);
    }

    private function token($actor): string
    {
        return $actor->createToken('synthetic-assessment', ['api'], now()->addHour())->plainTextToken;
    }

    private function api(?string $token, string $method, string $path = '/patient/assessments', array $body = [])
    {
        Auth::forgetGuards();

        return $this->json($method, '/api/v1'.$path, $body, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function body($setting): array
    {
        return ['submission_id' => (string) Str::uuid(), 'version' => $setting->versi, 'date_wib' => '2026-11-07', 'vas_mm' => 0,
            'filled_by' => 'PASIEN', 'device_created_at' => '2026-11-07T04:55:00.123Z',
            'answers' => [['code' => 'SYNTHETIC_ITEM', 'option_id' => $setting->nilai_json['questions'][0]['options'][0]['id']]]];
    }

    public function test_research_history_filters_are_inclusive_scoped_and_use_the_linked_version_labels(): void
    {
        $setting = SyntheticDailyInstrument::create();
        $patient = $this->patient();
        $body = $this->body($setting);
        $this->api($this->token($patient), 'POST', body: $body)->assertCreated();
        $legacy = DailyEntry::factory()->repeat()->create(['responden_id' => $patient->responden_id, 'tanggal_wib' => '2026-11-07']);
        $legacy->symptoms()->create(['symptom_option_id' => $body['answers'][0]['option_id'], 'kode_gejala' => 'SYNTHETIC_ITEM', 'jawaban' => 'SYNTHETIC_A']);
        DailyEntry::factory()->create(['responden_id' => Participant::factory()->create(['tanggal_pulang' => '2026-11-05'])->id]);
        // Today's setting must not change the wording of yesterday's linked immutable version.
        $newDefinition = $setting->nilai_json;
        $newDefinition['questions'][0]['label'] = 'SYNTHETIC DIFFERENT NEW LABEL';
        SyntheticDailyInstrument::create(['berlaku_mulai' => now(), 'nilai_json' => $newDefinition]);
        $token = $this->token($setting->approver);
        $path = '/participants/'.$patient->responden_id.'/assessments';
        $response = $this->api($token, 'GET', $path.'?from=2026-11-07&to=2026-11-07&type=HARIAN')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $body['submission_id'])
            ->assertJsonPath('meta.filters', ['from' => '2026-11-07', 'to' => '2026-11-07', 'type' => 'HARIAN'])
            ->assertJsonPath('data.0.answers.0.question_label', $setting->nilai_json['questions'][0]['label'])
            ->assertJsonPath('data.0.answers.0.answer_label', $setting->nilai_json['questions'][0]['options'][0]['label']);
        $response->assertDontSee('pin_hash')->assertDontSee('submission_fingerprint')->assertDontSee('kontak');
        $this->api($token, 'GET', $path.'?type=ULANG')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.version', null)
            ->assertJsonPath('data.0.answers.0.question_label', null)->assertJsonPath('data.0.answers.0.answer_label', null);
        $this->api($token, 'GET', $path.'?from=2026-11-08')->assertOk()->assertJsonPath('meta.total', 0);
        $this->api($token, 'GET', $path.'?to=2026-11-06')->assertOk()->assertJsonPath('meta.total', 0);
        $this->api($token, 'GET', $path)->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.filters', ['from' => null, 'to' => null, 'type' => null]);
        $this->assertSame(1, AuditLog::where('aksi', 'assessment.submitted')->count());
        $this->assertGreaterThanOrEqual(5, AuditLog::where('aksi', 'privileged.access')->count());
        $this->assertDatabaseCount('klasifikasi', 0);
        $this->assertDatabaseCount('peringatan', 0);
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public function test_filters_validate_after_authorization_and_do_not_allow_scope_or_role_bypasses(): void
    {
        $setting = SyntheticDailyInstrument::create();
        $patient = $this->patient();
        $path = '/participants/'.$patient->responden_id.'/assessments';
        $token = $this->token($setting->approver);
        foreach (['?from=tomorrow', '?to=2026-02-30', '?from=2026-11-08&to=2026-11-07', '?type=INVENTED'] as $query) {
            $this->api($token, 'GET', $path.$query)->assertUnprocessable();
        }
        foreach (['ADMIN_IT', 'PERAWAT_PEMANTAU', 'PENILAI_HASIL'] as $role) {
            $staff = StaffUser::factory()->create();
            $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => $role]));
            $this->api($this->token($staff), 'GET', $path.'?from=INVALID')->assertForbidden();
        }
        $this->api($this->token($patient), 'GET', $path.'?from=INVALID')->assertForbidden();
        $this->api(null, 'GET', $path.'?from=INVALID')->assertUnauthorized();
        $setting->approver->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'PENILAI_HASIL']));
        $this->api($this->token($setting->approver), 'GET', $path.'?type=HARIAN')->assertForbidden();
        $this->assertDatabaseCount('entri_harian', 0);
    }

    public function test_filtered_history_keeps_bounded_pagination_and_no_patient_projection_change(): void
    {
        $setting = SyntheticDailyInstrument::create();
        $patient = $this->patient();
        DailyEntry::factory()->repeat()->count(26)->create(['responden_id' => $patient->responden_id]);
        $path = '/participants/'.$patient->responden_id.'/assessments?type=ULANG';
        $token = $this->token($setting->approver);
        $this->api($token, 'GET', $path)->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 26)->assertJsonPath('meta.last_page', 2);
        $this->api($token, 'GET', $path.'&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.filters', ['from' => null, 'to' => null, 'type' => 'ULANG']);
        $this->api($this->token($patient), 'GET')->assertOk()->assertJsonMissingPath('meta.filters');
        $this->assertDatabaseCount('entri_harian', 26);
    }

    public function test_unapproved_catalog_is_unavailable_and_no_write_is_possible(): void
    {
        $token = $this->token($this->patient());
        $this->api($token, 'GET', '/patient/assessments/status')->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.questionnaire', null)->assertJsonPath('data.can_submit', false);
        $setting = SyntheticDailyInstrument::create(['disetujui_oleh' => null, 'disetujui_pada' => null, 'berlaku_mulai' => null]);
        $this->api($token, 'POST', body: $this->body($setting))->assertConflict();
        $this->assertDatabaseCount('entri_harian', 0);
    }

    public function test_success_replay_conflicting_reuse_and_immutable_history(): void
    {
        $setting = SyntheticDailyInstrument::create();
        $patient = $this->patient();
        $token = $this->token($patient);
        $body = $this->body($setting);
        $this->api($token, 'GET', '/patient/assessments/status')->assertOk()->assertJsonPath('data.can_submit', true)->assertJsonPath('data.study_day', 1)->assertJsonPath('data.questionnaire.version', $setting->versi)->assertDontSee('aturan_skoring');
        $first = $this->api($token, 'POST', body: $body)->assertCreated()->assertJsonPath('replayed', false)->assertJsonPath('data.vas_mm', 0)->assertJsonPath('data.version', $setting->versi);
        $this->api($token, 'POST', body: $body)->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('data', $first->json('data'));
        $this->api($token, 'POST', body: array_replace($body, ['vas_mm' => 1]))->assertConflict();
        $this->api($token, 'POST', body: array_replace($body, ['submission_id' => (string) Str::uuid()]))->assertConflict();
        $this->assertDatabaseCount('entri_harian', 1);
        $this->assertDatabaseCount('jawaban_gejala', 1);
        $this->assertSame(1, AuditLog::where('aksi', 'assessment.submitted')->count());
        $audit = AuditLog::where('aksi', 'assessment.submitted')->first();
        $this->assertSame(['outcome_code' => 'SUCCESS'], $audit->metadata_redacted);
        $this->assertSame('daily_entry', $audit->entity_type);
        $this->assertDatabaseCount('klasifikasi', 0);
        $this->assertDatabaseCount('peringatan', 0);
        $this->assertDatabaseCount('notification_deliveries', 0);
        $this->api($token, 'GET', '/patient/assessments/status')->assertJsonPath('data.can_submit', false)->assertJsonPath('data.submitted.id', $body['submission_id']);
        $this->api($token, 'GET')->assertOk()->assertJsonPath('meta.total', 1)->assertDontSee('submission_fingerprint')->assertDontSee('pin_hash');
        try {
            DailyEntry::whereKey($body['submission_id'])->update(['vas_mm' => 1]);
            $this->fail('Immutable trigger missing.');
        } catch (QueryException $e) {
            $this->assertSame('45000', $e->errorInfo[0]);
        }
    }

    public static function invalid(): array
    {
        return ['missing answer' => ['answers', []], 'unknown question' => ['answers', [['code' => 'INVENTED', 'option_id' => 1]]],
            'unknown option' => ['answers', [['code' => 'SYNTHETIC_ITEM', 'option_id' => 1]]],
            'negative VAS' => ['vas_mm', -1], 'high VAS' => ['vas_mm', 101], 'float VAS' => ['vas_mm', 1.5], 'numeric string' => ['vas_mm', '50'],
            'bad author' => ['filled_by', 'STAFF'], 'bad UUID' => ['submission_id', 'NOT-A-UUID'], 'bad timestamp' => ['device_created_at', 'yesterday']];
    }

    #[DataProvider('invalid')]
    public function test_invalid_answers_are_rejected_without_partial_records(string $field, mixed $value): void
    {
        $setting = SyntheticDailyInstrument::create();
        $token = $this->token($this->patient());
        $this->api($token, 'POST', body: array_replace($this->body($setting), [$field => $value]))->assertUnprocessable();
        $this->assertDatabaseCount('entri_harian', 0);
        $this->assertDatabaseCount('jawaban_gejala', 0);
    }

    public function test_version_change_ineligible_date_and_foreign_identity_are_rejected(): void
    {
        $setting = SyntheticDailyInstrument::create();
        $patient = $this->patient();
        $token = $this->token($patient);
        $body = $this->body($setting);
        $this->api($token, 'POST', body: array_replace($body, ['version' => 'UNSUPPORTED']))->assertConflict();
        $this->api($token, 'POST', body: array_replace($body, ['date_wib' => '2026-11-06']))->assertConflict();
        $this->api($token, 'POST', body: $body + ['responden_id' => (string) Str::uuid()])->assertUnprocessable();
        $other = $this->patient();
        $otherToken = $this->token($other);
        $this->api($token, 'POST', body: $body)->assertCreated();
        $this->api($otherToken, 'GET')->assertJsonPath('meta.total', 0);
        $this->api($otherToken, 'POST', body: $body)->assertConflict()->assertDontSee($patient->responden_id);
        $this->api($otherToken, 'GET', '/participants/'.$patient->responden_id.'/assessments')->assertForbidden();
        $this->api($token, 'PATCH', '/patient/assessments/'.$body['submission_id'])->assertNotFound();
    }

    public function test_revoked_expired_inactive_control_and_staff_sessions_cannot_submit(): void
    {
        $setting = SyntheticDailyInstrument::create();
        $body = $this->body($setting);
        $this->api(null, 'POST', body: $body)->assertUnauthorized();
        $patient = $this->patient();
        $token = $this->token($patient);
        $patient->update(['revoked_at' => now()]);
        $this->api($token, 'POST', body: $body)->assertUnauthorized();
        $patient = $this->patient();
        $token = $this->token($patient);
        $patient->participant->update(['status' => 'SELESAI']);
        $this->api($token, 'POST', body: $body)->assertUnauthorized();
        $patient = $this->patient();
        $expired = $patient->createToken('synthetic-expired', ['api'], now()->subMinute())->plainTextToken;
        $this->api($expired, 'POST', body: $body)->assertUnauthorized();
        $staff = StaffUser::factory()->create();
        $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'PENELITI_UTAMA']));
        $this->api($this->token($staff), 'POST', body: $body)->assertForbidden();
        $control = Participant::factory()->control()->create();
        $this->api(null, 'POST', '/auth/patient/login', ['code' => $control->kode, 'pin' => '012345'])->assertUnauthorized();
        $this->assertDatabaseCount('entri_harian', 0);
    }

    public function test_day_zero_and_day_31_cannot_create_but_exact_replay_survives_date_rollover(): void
    {
        $setting = SyntheticDailyInstrument::create();
        $patient = $this->patient();
        $token = $this->token($patient);
        $body = $this->body($setting);
        $this->travelTo(now()->subDay());
        $this->api($token, 'POST', body: array_replace($body, ['date_wib' => '2026-11-06']))->assertConflict();
        $this->travelTo(now()->addDay());
        $this->api($token, 'POST', body: $body)->assertCreated();
        // New token at day 31, same active principal; lost-response receipt remains retrievable.
        $this->travelTo(now()->addDays(30));
        $token = $this->token($patient);
        $this->api($token, 'POST', body: $body)->assertOk()->assertJsonPath('replayed', true);
        $this->api($token, 'POST', body: array_replace($body, ['submission_id' => (string) Str::uuid(), 'date_wib' => '2026-12-07']))->assertConflict();
    }

    public function test_audit_database_failure_rolls_back_entire_submission_without_payload_exposure(): void
    {
        $setting = SyntheticDailyInstrument::create();
        $token = $this->token($this->patient());
        $body = $this->body($setting);
        $this->mock(SecurityAudit::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new QueryException('mysql', 'synthetic failure', [], new \PDOException('synthetic')));
        });
        $this->api($token, 'POST', body: $body)->assertStatus(503)->assertDontSee('synthetic failure')->assertDontSee('SYNTHETIC_A');
        $this->assertDatabaseCount('entri_harian', 0);
        $this->assertDatabaseCount('jawaban_gejala', 0);
    }

    public function test_researcher_read_only_access_denies_blinded_combinations_and_it_without_clinical_scope(): void
    {
        $setting = SyntheticDailyInstrument::create();
        $patient = $this->patient();
        $token = $this->token($patient);
        $this->api($token, 'POST', body: $this->body($setting))->assertCreated();
        $path = '/participants/'.$patient->responden_id.'/assessments';
        $researcher = $setting->approver;
        $this->api($this->token($researcher), 'GET', $path)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonStructure(['data' => [['received_at', 'answers', 'version']]]);
        $researcher->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'PENILAI_HASIL']));
        $this->api($this->token($researcher), 'GET', $path)->assertForbidden();
        foreach (['ADMIN_IT', 'PERAWAT_PEMANTAU'] as $role) {
            $staff = StaffUser::factory()->create();
            $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => $role]));
            $this->api($this->token($staff), 'GET', $path)->assertForbidden();
        }
        $this->api($this->token($setting->approver), 'POST', $path)->assertMethodNotAllowed();
    }

    public function test_it_scope_combinations_and_nurse_assignments_are_enforced_on_new_history_endpoint(): void
    {
        $setting = SyntheticDailyInstrument::create();
        $patient = $this->patient();
        $this->api($this->token($patient), 'POST', body: $this->body($setting))->assertCreated();
        $path = '/participants/'.$patient->responden_id.'/assessments';
        foreach ([['TECHNICAL'], ['CONTACT'], ['TECHNICAL', 'CONTACT'], ['CLINICAL_READ'], ['CONTACT', 'CLINICAL_READ']] as $scopes) {
            $admin = StaffUser::factory()->create();
            $admin->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'ADMIN_IT']));
            $grant = IdentifiedAccessGrant::create(['pengguna_id' => $admin->id, 'responden_id' => $patient->responden_id,
                'approved_by' => $setting->disetujui_oleh, 'purpose_code' => 'SYNTHETIC_TEST', 'starts_at' => now()->subMinute(), 'expires_at' => now()->addHour()]);
            foreach ($scopes as $scope) {
                $grant->scopes()->create(['scope' => $scope]);
            }
            $this->api($this->token($admin), 'GET', $path)->assertStatus(in_array('CLINICAL_READ', $scopes) ? 200 : 403);
            $grant->update(['revoked_at' => now()]);
            $this->api($this->token($admin), 'GET', $path)->assertForbidden();
        }
        $nurse = StaffUser::factory()->create();
        $nurse->roles()->attach(ResearchRole::firstOrCreate(['kode' => 'PERAWAT_PEMANTAU']));
        $this->api($this->token($nurse), 'GET', $path)->assertForbidden();
        ParticipantAssignment::factory()->create(['responden_id' => $patient->responden_id, 'pengguna_id' => $nurse->id, 'jenis' => 'PEMANTAU', 'mulai_pada' => now()->subMinute(), 'berakhir_pada' => now()->addMinute()]);
        $this->api($this->token($nurse), 'GET', $path)->assertOk();
    }

    public function test_patient_cannot_override_identity_or_submit_extra_duplicate_or_foreign_catalog_answers(): void
    {
        $setting = SyntheticDailyInstrument::create();
        $other = SyntheticDailyInstrument::create(['berlaku_mulai' => now()->addDay()]);
        $token = $this->token($this->patient());
        $body = $this->body($setting);
        $foreign = $other->nilai_json['questions'][0]['options'][0]['id'];
        $this->api($token, 'POST', body: array_replace($body, ['answers' => [['code' => 'SYNTHETIC_ITEM', 'option_id' => $foreign]]]))->assertUnprocessable();
        $this->api($token, 'POST', body: array_replace($body, ['answers' => [$body['answers'][0], $body['answers'][0]]]))->assertUnprocessable();
        $this->api($token, 'POST', body: array_replace($body, ['answers' => [$body['answers'][0] + ['jawaban' => 'INVENTED']]]))->assertUnprocessable();
        $this->api($token, 'POST', body: $body + ['kelompok' => 'KONTROL'])->assertUnprocessable();
        $this->assertDatabaseCount('entri_harian', 0);
    }

    public function test_provenance_pair_and_fk_constraints_remain_active(): void
    {
        $patient = $this->patient();
        $attributes = DailyEntry::factory()->make(['responden_id' => $patient->responden_id])->getAttributes();
        foreach ([['submission_fingerprint' => str_repeat('a', 64)], ['assessment_setting_id' => 999999999, 'submission_fingerprint' => str_repeat('a', 64)]] as $invalid) {
            try {
                $entry = new DailyEntry;
                $entry->forceFill(array_merge($attributes, $invalid));
                $entry->save();
                $this->fail('Invalid provenance accepted.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->errorInfo[1], [3819, 1452]);
            }
        }
        $this->assertDatabaseCount('entri_harian', 0);
    }

    public function test_assessment_requests_are_throttled_by_authenticated_principal(): void
    {
        $patient = $this->patient();
        $token = $this->token($patient);
        for ($i = 0; $i < 30; $i++) {
            $this->api($token, 'GET', '/patient/assessments/status')->assertOk();
        }
        $this->api($token, 'GET', '/patient/assessments/status')->assertTooManyRequests();
        $this->api($this->token($this->patient()), 'GET', '/patient/assessments/status')->assertOk();
    }

    public function test_malformed_future_ambiguous_and_wrong_catalog_definitions_fail_closed(): void
    {
        $token = $this->token($this->patient());
        $setting = SyntheticDailyInstrument::create(['berlaku_mulai' => now()->addDay()]);
        $this->api($token, 'GET', '/patient/assessments/status')->assertJsonPath('data.available', false);
        SyntheticDailyInstrument::create(['nilai_json' => ['format' => 'unsupported']]);
        $this->api($token, 'GET', '/patient/assessments/status')->assertJsonPath('data.available', false);
        $this->travelTo(now()->addHour());
        SyntheticDailyInstrument::create(['berlaku_mulai' => now(), 'nilai_json' => $setting->nilai_json]);
        $this->api($token, 'GET', '/patient/assessments/status')->assertJsonPath('data.available', false);
        SyntheticDailyInstrument::create(['berlaku_mulai' => now()]);
        $this->api($token, 'GET', '/patient/assessments/status')->assertJsonPath('data.available', false);
    }
}
