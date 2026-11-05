<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DailyEntry;
use App\Models\Participant;
use App\Models\ParticipantContact;
use App\Models\StaffUser;
use Database\Seeders\TeleNapasDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class TeleNapasDemoSeederTest extends TestCase
{
    private string $path;

    private string $password;

    private bool $transaction = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql' || ! preg_match('/^[a-z0-9_]+_test$/', DB::connection()->getDatabaseName())) {
            throw new RuntimeException('Demo tests require a new disposable MySQL _test schema.');
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        DB::beginTransaction();
        $this->transaction = true;
        $this->path = tempnam(sys_get_temp_dir(), 'telenapas-demo-test-');
        $this->password = Str::password(32);
        file_put_contents($this->path, $this->password.PHP_EOL);
        chmod($this->path, 0600);
        config(['telenapas_demo.enabled' => true, 'telenapas_demo.password_file' => $this->path]);
    }

    protected function tearDown(): void
    {
        if (isset($this->path) && is_file($this->path)) {
            unlink($this->path);
        }
        if ($this->transaction) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function seedDemo(): void
    {
        $this->artisan('db:seed', ['--class' => TeleNapasDemoSeeder::class, '--force' => true])->assertExitCode(0);
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['pengguna', 'role_assignments', 'responden', 'kontak_responden', 'participant_assignments', 'entri_harian', 'audit_log'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy($table === 'kontak_responden' ? 'responden_id' : 'id')->get()->toJson();
        }

        return $snapshot;
    }

    public function test_mysql_dataset_is_atomic_idempotent_and_does_not_rotate_or_repair_existing_data(): void
    {
        $this->assertStringStartsWith('8.4.', DB::selectOne('SELECT VERSION() AS version')->version);
        $this->seedDemo();
        $this->assertSame(5, StaffUser::count());
        $this->assertSame(4, Participant::count());
        $this->assertSame(4, ParticipantContact::count());
        $this->assertSame(6, DailyEntry::count());
        $this->assertSame(2, DB::table('participant_assignments')->count());
        $this->assertSame(18, AuditLog::count());
        $this->assertSame([1, 1, 2, 2, 3, 3], DailyEntry::orderBy('hari_ke')->pluck('hari_ke')->all());
        foreach (['patient_credentials', 'identified_access_grants', 'identified_access_grant_scopes', 'pengaturan', 'symptom_options', 'follow_up_options', 'jawaban_gejala', 'klasifikasi', 'peringatan', 'tindak_lanjut', 'formulir_hasil', 'usability', 'questionnaire_versions', 'notification_deliveries'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $researcher = StaffUser::where('email', 'researcher@telenapas-demo.invalid')->firstOrFail();
        $this->assertTrue(Hash::check($this->password, $researcher->password_hash));
        $this->assertNotSame($this->password, $researcher->getRawOriginal('password_hash'));
        $raw = DB::table('kontak_responden')->first();
        $this->assertNotSame('SYNTHETIC-NOT-A-PHONE', $raw->no_hp_pasien_encrypted);
        $this->assertSame('SYNTHETIC-NOT-A-PHONE', ParticipantContact::first()->no_hp_pasien_encrypted);
        foreach (AuditLog::all() as $audit) {
            $this->assertNull($audit->entity_id);
            $this->assertSame(['outcome_code' => 'SYNTHETIC_DEMO_V1'], $audit->metadata_redacted);
        }
        // A dashboard edit/addition must survive reruns as well as immutable observations.
        Participant::first()->update(['versi_consent' => 'SYNTHETIC-EDIT']);
        Participant::factory()->control()->create(['kode' => 'SYN-EXTRA-ENROLLMENT']);
        $before = $this->snapshot();
        config(['telenapas_demo.password_file' => '/missing-file-on-rerun']);
        $this->seedDemo();
        $this->assertSame($before, $this->snapshot());
        $this->assertTrue(Hash::check($this->password, $researcher->fresh()->password_hash));
    }

    private function token(string $account): string
    {
        Auth::forgetGuards();

        return $this->postJson('/api/v1/auth/staff/login', ['email' => $account.'@telenapas-demo.invalid', 'password' => $this->password])
            ->assertOk()->assertJsonPath('token_type', 'Bearer')->json('access_token');
    }

    public function test_seeded_staff_authentication_and_participant_level_permissions_remain_enforced(): void
    {
        $this->seedDemo();
        $participant = Participant::where('kode', 'SYN-DEMO-I-001')->firstOrFail();
        $other = Participant::where('kode', 'SYN-DEMO-I-002')->firstOrFail();
        $this->getJson('/api/v1/participants')->assertUnauthorized();
        $pi = $this->token('researcher');
        $this->withToken($pi)->postJson('/api/v1/research/participants/search')->assertOk()->assertJsonPath('meta.total', 4);
        $this->withToken($pi)->getJson('/api/v1/participants/'.$participant->id.'/contact')->assertOk()->assertJsonPath('data.initials', 'SYN-DEMO');
        $nurse = $this->token('nurse');
        $this->withToken($nurse)->getJson('/api/v1/participants')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $participant->id);
        $this->withToken($nurse)->getJson('/api/v1/participants/'.$participant->id.'/entries')->assertOk()->assertJsonCount(3, 'data');
        $this->withToken($nurse)->getJson('/api/v1/participants/'.$other->id)->assertForbidden();
        $this->withToken($nurse)->postJson('/api/v1/research/participants', [])->assertForbidden();
        $backup = $this->token('backup');
        $this->withToken($backup)->getJson('/api/v1/participants')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $other->id);
        $it = $this->token('it');
        $this->withToken($it)->getJson('/api/v1/participants')->assertOk()->assertJsonPath('meta.total', 0);
        $this->withToken($it)->getJson('/api/v1/participants/'.$participant->id.'/contact')->assertForbidden();
        $this->withToken($it)->getJson('/api/v1/participants/'.$participant->id.'/entries')->assertForbidden();
        $assessor = $this->token('assessor');
        $this->withToken($assessor)->getJson('/api/v1/participants/'.$participant->id.'/entries')->assertForbidden();
        config(['research_auth.blinded_assessment' => false]);
        $this->withToken($assessor)->getJson('/api/v1/participants/'.$participant->id.'/contact')->assertForbidden();
    }

    public function test_existing_database_constraints_remain_active_after_seeding(): void
    {
        $this->seedDemo();
        $entry = DailyEntry::firstOrFail();
        $audit = AuditLog::firstOrFail();
        $control = Participant::where('kode', 'SYN-DEMO-C-001')->firstOrFail();
        foreach ([
            fn () => DB::table('entri_harian')->where('id', $entry->id)->update(['vas_mm' => 1]),
            fn () => DB::table('entri_harian')->where('id', $entry->id)->delete(),
            fn () => DB::table('audit_log')->where('id', $audit->id)->delete(),
            fn () => DB::table('audit_log')->where('id', $audit->id)->update(['aksi' => 'TAMPERED']),
            fn () => DailyEntry::factory()->create(['responden_id' => $control->id]),
            fn () => DailyEntry::factory()->create(['responden_id' => $entry->responden_id, 'tanggal_wib' => $entry->tanggal_wib]),
            fn () => Participant::factory()->create(['vas_baseline_mm' => 101]),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Integrity constraint was bypassed.');
            } catch (QueryException $exception) {
                $this->assertContains($exception->errorInfo[0], ['45000', '23000', 'HY000']);
            }
        }
    }

    public function test_first_run_refuses_existing_records_without_adopting_accounts_or_participants(): void
    {
        StaffUser::factory()->create(['email' => 'researcher@telenapas-demo.invalid']);
        $before = $this->snapshot();
        try {
            $this->seedDemo();
            $this->fail('Existing database was adopted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('empty application tables', $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
    }

    #[DataProvider('unsafeSettings')]
    public function test_safety_guards_prevent_any_writes(string $guard): void
    {
        switch ($guard) {
            case 'production': app()->instance('env', 'production');
                break;
            case 'disabled': config(['telenapas_demo.enabled' => false]);
                break;
            case 'missing': config(['telenapas_demo.password_file' => '/missing-private-file']);
                break;
            case 'public_file': chmod($this->path, 0644);
                break;
            case 'short': file_put_contents($this->path, Str::random(5));
                break;
        }
        $before = $this->snapshot();
        try {
            $this->seedDemo();
            $this->fail('Unsafe seeding was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringNotContainsString($this->password, $exception->getMessage());
        }
        $this->assertSame($before, $this->snapshot());
    }

    public static function unsafeSettings(): array
    {
        return [['production'], ['disabled'], ['missing'], ['public_file'], ['short']];
    }

    public function test_non_mysql_driver_is_rejected_without_opening_another_database(): void
    {
        $connection = DB::getDefaultConnection();
        try {
            DB::setDefaultConnection('sqlite');
            $this->seedDemo();
            $this->fail('Non-MySQL demo accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('dedicated MySQL 8.4', $exception->getMessage());
        } finally {
            DB::setDefaultConnection($connection);
        }
        $this->assertSame(0, StaffUser::count());
    }

    public function test_concurrent_initializer_cannot_create_another_dataset(): void
    {
        $lock = 'telenapas.demo.'.substr(hash('sha256', DB::connection()->getDatabaseName()), 0, 40);
        config(['database.connections.demo_competing' => DB::connection()->getConfig()]);
        $other = DB::connection('demo_competing');
        $other->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock]);
        try {
            $this->seedDemo();
            $this->fail('Concurrent initializer bypassed lock.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Another demo initializer', $exception->getMessage());
        } finally {
            $other->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
            DB::purge('demo_competing');
        }
        $this->assertSame(0, StaffUser::count());
        $this->assertSame(0, AuditLog::count());
    }

    public function test_failed_audit_rolls_back_the_whole_dataset_and_releases_the_initializer_lock(): void
    {
        AuditLog::creating(function () {
            throw new RuntimeException('Synthetic audit failure');
        });
        try {
            $this->seedDemo();
            $this->fail('Audit failure was ignored.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }
        $lock = 'telenapas.demo.'.substr(hash('sha256', DB::connection()->getDatabaseName()), 0, 40);
        $this->assertSame(1, (int) DB::selectOne('SELECT IS_FREE_LOCK(?) AS free', [$lock])->free);
        $this->assertSame(0, StaffUser::count());
        $this->assertSame(0, Participant::count());
        $this->assertSame(0, AuditLog::count());
        $this->seedDemo();
        $this->assertSame(4, Participant::count());
    }
}
