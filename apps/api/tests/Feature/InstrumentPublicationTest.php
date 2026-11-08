<?php

namespace Tests\Feature;

use App\Assessment\DailyInstrument;
use App\Auth\SecurityAudit;
use App\Models\AuditLog;
use App\Models\PatientCredential;
use App\Models\ResearchRole;
use App\Models\ResearchSetting;
use App\Models\StaffUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InstrumentPublicationTest extends TestCase
{
    private bool $transaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Use isolated MySQL.');
        }
        if (! preg_match('/^[a-z0-9_]+_test$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use isolated _test schema.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        DB::beginTransaction();
        $this->transaction = true;
        $this->freezeTime();
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        if ($this->transaction) {
            DB::rollBack();
        } parent::tearDown();
    }

    private function staff(array $roles = ['PENELITI_UTAMA']): StaffUser
    {
        $staff = StaffUser::factory()->create();
        foreach ($roles as $role) {
            $staff->roles()->attach(ResearchRole::firstOrCreate(['kode' => $role]));
        }

        return $staff;
    }

    private function callApi($actor, string $method, array $body = [], string $path = '/research/daily-instruments/publish')
    {
        Auth::forgetGuards();
        $token = $actor?->createToken('synthetic-publication', ['api'], now()->addHour())->plainTextToken;

        return $this->json($method, '/api/v1'.$path, $body, $token ? ['Authorization' => 'Bearer '.$token] : []);
    }

    private function definition(): array
    {
        return ['version' => 'SYNTHETIC_PUBLICATION_V1', 'expected_current_version' => null, 'confirm_publication' => true,
            'approval_reference' => 'SYNTHETIC_TEST_ONLY', 'effective_at' => now()->addMinute()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'expires_at' => now()->addMinutes(2)->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'schedule' => ['timezone' => 'Asia/Jakarta', 'first_day' => 1, 'last_day' => 30, 'late_policy' => 'same_day_only'],
            'questions' => [['code' => 'SYNTHETIC_ITEM', 'label' => 'Synthetic item, not clinical', 'required' => true,
                'options' => [['value' => 'SYNTHETIC_A', 'label' => 'Synthetic option A']]]]];
    }

    public function test_explicit_atomic_publication_records_authority_is_future_then_available_then_expired(): void
    {
        $staff = $this->staff();
        $body = $this->definition();
        $this->callApi($staff, 'POST', $body)->assertCreated()->assertJsonPath('data.approved_by', $staff->id);
        $setting = ResearchSetting::where('versi', $body['version'])->firstOrFail();
        $this->assertNull(app(DailyInstrument::class)->current());
        $this->travel(61)->seconds();
        $this->assertSame($setting->id, app(DailyInstrument::class)->current()->id);
        $this->callApi($staff, 'GET', path: '/research/daily-instruments')->assertOk()->assertJsonPath('data.0.approval_reference', 'SYNTHETIC_TEST_ONLY');
        $this->assertDatabaseHas('audit_log', ['aksi' => 'instrument.published', 'actor_id' => $staff->id, 'entity_id' => (string) $setting->id]);
        $this->assertSame(['outcome_code' => 'SUCCESS'], AuditLog::where('aksi', 'instrument.published')->first()->metadata_redacted);
        $this->travel(60)->seconds();
        $this->assertNull(app(DailyInstrument::class)->current());
        try {
            DB::table('instrument_publications')->where('setting_id', $setting->id)->delete();
            $this->fail('Publication mutable');
        } catch (QueryException $e) {
            $this->assertSame('45000', $e->errorInfo[0]);
        }
        $this->assertDatabaseCount('entri_harian', 0);
        $this->assertDatabaseCount('peringatan', 0);
        $this->assertDatabaseCount('klasifikasi', 0);
    }

    public static function roles(): array
    {
        return [[['ADMIN_IT']], [['PENILAI_HASIL']], [['PERAWAT_PEMANTAU']], [['PENELITI_UTAMA', 'PENILAI_HASIL']], [['PENELITI_UTAMA', 'ADMIN_IT']]];
    }

    #[DataProvider('roles')]
    public function test_deny_first_roles_cannot_publish_or_list(array $roles): void
    {
        config(['research_auth.blinded_assessment' => false]);
        $staff = $this->staff($roles);
        $this->callApi($staff, 'POST', $this->definition())->assertForbidden();
        $this->callApi($staff, 'GET', path: '/research/daily-instruments')->assertForbidden();
        $this->assertDatabaseCount('pengaturan', 0);
    }

    public function test_patient_and_anonymous_cannot_publish(): void
    {
        $this->callApi(null, 'POST', $this->definition())->assertUnauthorized();
        $this->callApi(PatientCredential::factory()->create(), 'POST', $this->definition())->assertForbidden();
    }

    public function test_rejects_missing_approval_invalid_catalog_implicit_confirmation_and_extra_clinical_rules(): void
    {
        $staff = $this->staff();
        foreach ([['confirm_publication' => false], ['approval_reference' => ''], ['schedule' => ['late_policy' => 'allow_late']], ['questions' => []], ['expires_at' => now()->toISOString()], ['score' => 'UNAPPROVED']] as $override) {
            $this->callApi($staff, 'POST', array_replace($this->definition(), $override))->assertUnprocessable();
        }
        $this->assertDatabaseCount('pengaturan', 0);
        $this->assertDatabaseCount('symptom_options', 0);
    }

    public function test_duplicate_version_stale_snapshot_and_audit_failure_cannot_partially_publish(): void
    {
        $staff = $this->staff();
        $body = $this->definition();
        $this->callApi($staff, 'POST', $body)->assertCreated();
        $this->callApi($staff, 'POST', $body)->assertConflict();
        $body['expected_current_version'] = 'STALE';
        $body['version'] = 'SYNTHETIC_V2';
        $this->callApi($staff, 'POST', $body)->assertConflict();
        $this->assertDatabaseCount('pengaturan', 1);
        $this->assertDatabaseCount('symptom_options', 1);
        $body['expected_current_version'] = null;
        $body['effective_at'] = now()->addSeconds(90)->utc()->format('Y-m-d\TH:i:s.v\Z');
        $this->mock(SecurityAudit::class, fn ($mock) => $mock->shouldReceive('record')->once()->andThrow(new QueryException('mysql', 'synthetic audit failure', [], new \PDOException('synthetic'))));
        $this->callApi($staff, 'POST', $body)->assertStatus(503);
        $this->assertDatabaseCount('pengaturan', 1);
        $this->assertDatabaseCount('instrument_publications', 1);
    }
}
