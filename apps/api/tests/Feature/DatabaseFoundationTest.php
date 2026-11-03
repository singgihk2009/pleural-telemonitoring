<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\ClassificationRecord;
use App\Models\DailyEntry;
use App\Models\FollowUp;
use App\Models\FollowUpOption;
use App\Models\NotificationDelivery;
use App\Models\Outcome;
use App\Models\Participant;
use App\Models\ParticipantAssignment;
use App\Models\ParticipantContact;
use App\Models\QuestionnaireVersion;
use App\Models\ResearchRole;
use App\Models\ResearchSetting;
use App\Models\RoleAssignment;
use App\Models\StaffUser;
use App\Models\SymptomResponse;
use App\Models\UsabilityResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Research integrity requires MySQL 8.4; legacy health tests also support SQLite.');
        }
        if (! preg_match('/^[a-z0-9_]+_test$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use a dedicated disposable MySQL database ending in _test.');
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

    public function test_baseline_contact_separation_encryption_and_relationships(): void
    {
        $participant = Participant::factory()->create(['vas_baseline_mm' => 100]);
        $contact = ParticipantContact::factory()->for($participant, 'participant')->create();
        $this->assertTrue($participant->contact->is($contact));
        $this->assertTrue($contact->participant->is($participant));
        $this->assertFalse(Schema::hasColumn('responden', 'no_hp_pasien_encrypted'));
        $raw = DB::table('kontak_responden')->where('responden_id', $participant->id)->first();
        $this->assertNotSame('SYNTHETIC-NOT-A-PHONE', $raw->no_hp_pasien_encrypted);
        $this->assertSame('SYNTHETIC-NOT-A-PHONE', $contact->fresh()->no_hp_pasien_encrypted);
        $this->assertArrayNotHasKey('no_hp_pasien_encrypted', $contact->toArray());
        $this->assertStringStartsWith('SYN-', $participant->kode);
        $this->assertSame(100, $participant->vas_baseline_mm);
        $this->reject(fn () => ParticipantContact::factory()->create(['responden_id' => $participant->id]));
        $this->reject(fn () => $participant->delete());
    }

    public function test_research_codes_are_unique_and_foreign_keys_cannot_be_orphaned(): void
    {
        $participant = Participant::factory()->create();
        $this->reject(fn () => Participant::factory()->create(['kode' => $participant->kode]));
        $this->reject(fn () => ParticipantContact::factory()->create(['responden_id' => (string) Str::uuid()]));
    }

    #[DataProvider('invalidParticipantValues')]
    public function test_participant_validation_at_database_boundary(array $attributes): void
    {
        $this->expectException(QueryException::class);
        Participant::factory()->create($attributes);
    }

    public static function invalidParticipantValues(): array
    {
        return [
            'negative baseline' => [['vas_baseline_mm' => -1]],
            'baseline above 100' => [['vas_baseline_mm' => 101]],
            'unknown group' => [['kelompok' => 'OTHER']],
            'wrong case group' => [['kelompok' => 'intervensi']],
            'unknown status' => [['status' => 'OTHER']],
            'dropout requires reason' => [['status' => 'DROP_OUT']],
        ];
    }

    #[DataProvider('validEntryDays')]
    public function test_entry_day_uses_wib_calendar_date_not_client_or_receive_time(string $date, int $day): void
    {
        $participant = Participant::factory()->create();
        $entry = DailyEntry::factory()->for($participant, 'participant')->create([
            'tanggal_wib' => $date,
            // Untrusted device clock and late receipt must not define study day.
            'dibuat_di_perangkat_pada' => '2030-01-01 00:00:00.654321',
            'diterima_server_pada' => '2026-03-01 17:00:00.123456',
        ])->fresh();
        $this->assertSame($day, $entry->hari_ke);
        $this->assertSame('123456', $entry->diterima_server_pada->format('u'));
        $this->assertTrue($entry->participant->is($participant));
    }

    public static function validEntryDays(): array
    {
        return [['2026-01-02', 1], ['2026-01-31', 30]];
    }

    #[DataProvider('invalidEntryValues')]
    public function test_entry_constraints_reject_invalid_direct_sql(array $attributes): void
    {
        $entry = DailyEntry::factory()->raw();
        $this->expectException(QueryException::class);
        DB::table('entri_harian')->insert(array_replace($entry, ['id' => (string) Str::uuid()], $attributes));
    }

    public static function invalidEntryValues(): array
    {
        return [
            'day zero' => [['tanggal_wib' => '2026-01-01']],
            'day 31' => [['tanggal_wib' => '2026-02-01']],
            'VAS negative' => [['vas_mm' => -1]],
            'VAS too large' => [['vas_mm' => 101]],
            'invalid UUID' => [['id' => 'not-a-uuid']],
            'invalid author' => [['diisi_oleh' => 'OTHER']],
            'invalid entry type' => [['tipe' => 'OTHER']],
            'wrong case entry type' => [['tipe' => 'harian']],
            'group spoof' => [['kelompok' => 'KONTROL']],
            'discharge spoof' => [['tanggal_pulang' => '2026-01-02']],
        ];
    }

    public function test_only_intervention_participants_can_have_entries_usability_or_alerts(): void
    {
        $control = Participant::factory()->control()->create();
        $this->reject(fn () => DailyEntry::factory()->create(['responden_id' => $control->id]));
        $this->reject(fn () => UsabilityResponse::factory()->create(['responden_id' => $control->id]));
        $this->reject(fn () => Alert::factory()->create(['responden_id' => $control->id]));
        $this->assertSame(0, $control->entries()->count());
    }

    public function test_daily_uniqueness_allows_multiple_repeats_and_uuid_retry_cannot_overwrite_payload(): void
    {
        $entry = DailyEntry::factory()->create(['vas_mm' => 0]);
        $this->reject(fn () => DailyEntry::factory()->create(['responden_id' => $entry->responden_id]));
        DailyEntry::factory()->repeat()->count(2)->create(['responden_id' => $entry->responden_id]);
        $this->assertSame(3, $entry->participant->entries()->count());
        $raw = DB::table('entri_harian')->where('id', $entry->id)->first();
        $payload = (array) $raw;
        unset($payload['hari_ke'], $payload['tanggal_harian']);
        $this->reject(fn () => DB::table('entri_harian')->insert($payload));
        $this->reject(fn () => DB::table('entri_harian')->insert(array_replace($payload, ['vas_mm' => 100])));
        $this->reject(fn () => DB::table('entri_harian')->where('id', $entry->id)->update(['vas_mm' => 100]));
        $this->reject(fn () => DB::table('entri_harian')->where('id', $entry->id)->delete());
        $this->reject(fn () => $entry->participant->update(['kelompok' => 'KONTROL']));
        $this->reject(fn () => $entry->participant->update(['tanggal_pulang' => '2026-01-02']));
        $this->assertSame(0, $entry->fresh()->vas_mm);
    }

    public function test_symptoms_reference_versioned_options_and_cannot_be_rewritten(): void
    {
        $response = SymptomResponse::factory()->create();
        $this->assertTrue($response->entry->symptoms->first()->is($response));
        $this->assertInstanceOf(ResearchSetting::class, $response->option->setting);
        $this->reject(fn () => SymptomResponse::factory()->create(['entri_id' => $response->entri_id]));
        $this->reject(fn () => SymptomResponse::factory()->create(['jawaban' => 'UNLISTED']));
        $this->reject(fn () => $response->update(['jawaban' => 'REWRITTEN']));
        $this->reject(fn () => $response->option->update(['jawaban' => 'CHANGED']));
    }

    public function test_classifications_are_storage_only_versioned_and_append_only(): void
    {
        $entry = DailyEntry::factory()->create(['vas_mm' => 100]);
        $this->assertSame(0, $entry->classifications()->count());
        $this->assertSame(0, $entry->alerts()->count());
        // Explicit synthetic classification, deliberately not derived from VAS.
        $record = ClassificationRecord::factory()->create(['entri_id' => $entry->id, 'level' => 'HIJAU']);
        $this->assertTrue($record->ruleset->is(ResearchSetting::where('kunci', 'classification')->where('versi', $record->ruleset_version)->first()));
        $this->reject(fn () => ClassificationRecord::factory()->create(['entri_id' => $entry->id, 'ruleset_version' => $record->ruleset_version]));
        ClassificationRecord::factory()->create(['entri_id' => $entry->id]);
        $this->assertSame(2, $entry->classifications()->count());
        $this->reject(fn () => $record->update(['level' => 'MERAH']));
        $this->reject(fn () => $record->delete());
        $this->reject(fn () => ClassificationRecord::factory()->create(['ruleset_version' => 'MISSING']));
        $this->reject(fn () => ClassificationRecord::factory()->create(['level' => 'OTHER']));
        $this->reject(fn () => $record->ruleset->update(['nilai_json' => ['changed' => true]]));
    }

    public function test_alert_participant_integrity_status_and_follow_up_relationships(): void
    {
        $entry = DailyEntry::factory()->create();
        $this->reject(fn () => Alert::factory()->create(['entri_id' => $entry->id]));
        $alert = Alert::factory()->create(['responden_id' => $entry->responden_id, 'entri_id' => $entry->id]);
        $this->assertTrue($alert->entry->is($entry));
        $this->reject(fn () => Alert::factory()->create(['status' => 'SELESAI']));
        $this->reject(fn () => Alert::factory()->create(['level' => 'HIJAU']));
        $staff = StaffUser::factory()->create();
        $alert->update(['status' => 'DIKONFIRMASI', 'dikonfirmasi_oleh' => $staff->id, 'dikonfirmasi_pada' => '2026-01-02 00:03:00.123456']);
        $followUp = FollowUp::factory()->create(['peringatan_id' => $alert->id]);
        $this->assertTrue($alert->followUps->first()->is($followUp));
        $this->assertTrue($followUp->alert->is($alert));
        $this->assertTrue($alert->fresh()->acknowledgedBy->is($staff));
        $this->assertInstanceOf(StaffUser::class, $followUp->nurse);
        $this->assertInstanceOf(FollowUpOption::class, $followUp->option);
        $this->reject(fn () => FollowUp::factory()->create(['vas_ulang' => 101]));
        $this->reject(fn () => FollowUp::factory()->create(['keputusan' => 'UNAPPROVED_CHOICE']));
        $this->assertNotSame('SYNTHETIC TEST ONLY', DB::table('tindak_lanjut')->where('id', $followUp->id)->value('hasil_asesmen'));
    }

    public function test_outcome_for_both_groups_preserves_missing_vs_negative_and_one_final_form(): void
    {
        foreach (['INTERVENSI', 'KONTROL'] as $group) {
            $participant = Participant::factory()->create(['kelompok' => $group]);
            $outcome = Outcome::factory()->create(['responden_id' => $participant->id, 'kekambuhan_a' => false, 'kekambuhan_b' => null]);
            $this->assertFalse($outcome->fresh()->kekambuhan_a);
            $this->assertNull($outcome->fresh()->kekambuhan_b);
            $this->assertTrue($participant->outcome->is($outcome));
            $this->assertInstanceOf(StaffUser::class, $outcome->assessor);
            $this->reject(fn () => Outcome::factory()->create(['responden_id' => $participant->id]));
        }
        $this->reject(fn () => Outcome::factory()->create(['vas_hari30' => 101]));
        $this->reject(fn () => Outcome::factory()->create(['readmission' => false, 'tanggal_readmission' => '2026-01-20']));
        $this->reject(fn () => Outcome::factory()->create(['readmission' => null, 'tanggal_readmission' => '2026-01-20']));
        $this->reject(fn () => DB::table('formulir_hasil')->where('id', $outcome->id)->update(['kekambuhan_a' => 2]));
    }

    public function test_usability_is_versioned_and_does_not_calculate_unapproved_scores(): void
    {
        $response = UsabilityResponse::factory()->create();
        $this->assertSame([null], $response->jawaban_item);
        $this->assertNull($response->skor_total);
        $this->assertInstanceOf(QuestionnaireVersion::class, $response->questionnaire);
        $this->assertTrue($response->participant->usabilityResponses->first()->is($response));
        $this->reject(fn () => UsabilityResponse::factory()->create(['responden_id' => $response->responden_id, 'versi_kuesioner' => $response->versi_kuesioner]));
        $this->reject(fn () => UsabilityResponse::factory()->create(['versi_kuesioner' => 'MISSING']));
        $this->reject(fn () => $response->questionnaire->update(['definisi_item' => []]));
    }

    public function test_roles_and_explicit_nurse_assignments_are_storage_not_authorization(): void
    {
        $assignment = ParticipantAssignment::factory()->create();
        $role = RoleAssignment::factory()->create(['pengguna_id' => $assignment->pengguna_id]);
        $this->assertTrue($assignment->user->roles->first()->is($role->role));
        $this->assertTrue($role->role->users->first()->is($assignment->user));
        $this->assertTrue($assignment->participant->assignments->first()->is($assignment));
        $this->assertTrue(Hash::check('synthetic-test-only-not-login', $assignment->user->password_hash));
        $this->assertArrayNotHasKey('password_hash', $assignment->user->toArray());
        $this->reject(fn () => RoleAssignment::factory()->create(['pengguna_id' => $role->pengguna_id, 'role_id' => $role->role_id]));
        $this->reject(fn () => ResearchRole::factory()->create(['kode' => 'UNAPPROVED_ROLE']));
        $this->reject(fn () => ParticipantAssignment::factory()->create(['berakhir_pada' => '2025-01-01']));
    }

    public function test_audit_is_append_only_and_rejects_payload_keys(): void
    {
        $audit = AuditLog::factory()->create();
        $this->assertInstanceOf(StaffUser::class, $audit->actor);
        $this->reject(fn () => $audit->update(['aksi' => 'REWRITTEN']));
        $this->reject(fn () => $audit->delete());
        $this->reject(fn () => DB::table('audit_log')->where('id', $audit->id)->delete());
        $this->reject(fn () => AuditLog::factory()->create(['metadata_redacted' => ['phone' => 'SYNTHETIC-NOT-A-PHONE']]));
        $this->reject(fn () => AuditLog::factory()->create(['metadata_redacted' => ['purpose_code' => ['nested' => 'payload']]]));
        $this->reject(fn () => AuditLog::factory()->create(['metadata_redacted' => ['purpose_code' => 'free text is forbidden']]));
    }

    public function test_notification_metadata_is_inert_recipient_scoped_and_consent_aware(): void
    {
        $delivery = NotificationDelivery::factory()->create();
        $this->assertSame('PLANNED', $delivery->status);
        $this->assertSame(0, $delivery->attempts);
        $this->assertNull($delivery->delivered_at);
        $this->assertTrue($delivery->participant->notifications->first()->is($delivery));
        $this->assertFalse(Schema::hasColumn('notification_deliveries', 'payload'));
        $this->assertFalse(Schema::hasColumn('notification_deliveries', 'device_token'));
        $this->reject(fn () => NotificationDelivery::factory()->create(['pengguna_id' => StaffUser::factory()->create()->id]));
        $this->reject(fn () => NotificationDelivery::factory()->create(['channel' => 'WHATSAPP', 'status' => 'PENDING']));
        $this->reject(fn () => NotificationDelivery::factory()->create(['error_code' => 'free text is forbidden']));
        $this->reject(fn () => NotificationDelivery::factory()->create(['peringatan_id' => Alert::factory()->create()->id]));
        $this->assertSame(1, NotificationDelivery::count());
    }

    public function test_all_approved_vas_boundaries_can_be_stored_without_classification(): void
    {
        foreach ([0, 39, 40, 69, 70, 100] as $vas) {
            $entry = DailyEntry::factory()->create(['vas_mm' => $vas]);
            $this->assertSame($vas, $entry->fresh()->vas_mm);
            $this->assertSame(0, $entry->classifications()->count());
            $this->assertSame(0, $entry->alerts()->count());
        }
    }

    public function test_configuration_cannot_activate_without_signoff(): void
    {
        $this->reject(fn () => ResearchSetting::factory()->create(['berlaku_mulai' => '2026-01-01 00:00:00']));
        $setting = ResearchSetting::factory()->create();
        $this->assertNull($setting->berlaku_mulai);
        $this->assertFalse($setting->nilai_json['enabled']);
    }

    private function reject(callable $write): void
    {
        try {
            $write();
            $this->fail('Invalid database write unexpectedly succeeded.');
        } catch (QueryException $exception) {
            $this->assertNotEmpty($exception->getSql());
        }
    }
}
