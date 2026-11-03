<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Participant;
use App\Models\PatientCredential;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AuthenticationMigrationTest extends TestCase
{
    public function test_authentication_upgrade_preserves_existing_research_data_and_audit_immutability(): void
    {
        if (! getenv('TELENAPAS_MIGRATION_TESTS')) {
            $this->markTestSkipped('Opt-in upgrade check requires its own empty disposable schema.');
        }
        if (DB::getDriverName() !== 'mysql' || ! preg_match('/^[a-z0-9_]+_auth_migration_test$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Upgrade check requires a dedicated _auth_migration_test database.');
        }
        $this->assertSame([], Schema::getTables(DB::connection()->getDatabaseName()));
        $foundation = [
            'database/migrations/2026_11_03_000001_create_research_foundation.php',
            'database/migrations/2026_11_03_000002_enforce_research_integrity.php',
        ];
        $authentication = 'database/migrations/2026_11_03_000003_create_authentication_foundation.php';
        $this->artisan('migrate', ['--path' => $foundation, '--force' => true])->assertExitCode(0);
        $participant = Participant::factory()->create();
        $audit = AuditLog::factory()->create();
        $snapshot = $participant->fresh()->getRawOriginal();
        $this->artisan('migrate', ['--path' => $authentication, '--force' => true])->assertExitCode(0);
        $this->assertSame($snapshot, $participant->fresh()->getRawOriginal());
        PatientCredential::factory()->create(['responden_id' => $participant->id]);
        $this->assertTrue(Schema::hasTable('personal_access_tokens'));
        // Only the auth migration is in the latest batch; no research table is rolled back.
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('patient_credentials'));
        $this->assertSame($snapshot, $participant->fresh()->getRawOriginal());
        $this->assertNotNull($audit->fresh());
        try {
            DB::table('audit_log')->where('id', $audit->id)->update(['aksi' => 'TAMPERED']);
            $this->fail('Existing immutable audit constraint was lost.');
        } catch (QueryException $exception) {
            $this->assertSame('45000', $exception->errorInfo[0]);
        }
        $this->artisan('migrate', ['--path' => $authentication, '--force' => true])->assertExitCode(0);
        $this->assertSame($snapshot, $participant->fresh()->getRawOriginal());
        $this->assertSame(1, AuditLog::count());
    }
}
