<?php

namespace Tests\Feature;

use App\Auth\AccessGrantScope;
use App\Auth\ParticipantAccess;
use App\Auth\ResearchRoleCode;
use App\Models\AuditLog;
use App\Models\IdentifiedAccessGrant;
use App\Models\Participant;
use App\Models\ResearchRole;
use App\Models\StaffUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GrantScopeMigrationTest extends TestCase
{
    public function test_scope_upgrade_preserves_legacy_records_without_backfilling_permissions(): void
    {
        if (! getenv('TELENAPAS_MIGRATION_TESTS')) {
            $this->markTestSkipped('Scope upgrade requires its own initially empty disposable schema.');
        }
        if (DB::getDriverName() !== 'mysql' || ! preg_match('/^[a-z0-9_]+_grant_scope_migration_test$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use a dedicated _grant_scope_migration_test database.');
        }
        $this->assertSame([], Schema::getTables(DB::connection()->getDatabaseName()));
        $old = [
            'database/migrations/2026_11_03_000001_create_research_foundation.php',
            'database/migrations/2026_11_03_000002_enforce_research_integrity.php',
            'database/migrations/2026_11_03_000003_create_authentication_foundation.php',
        ];
        $scopeMigration = 'database/migrations/2026_11_03_000004_add_explicit_identified_access_grant_scopes.php';
        $this->artisan('migrate', ['--path' => $old, '--force' => true])->assertExitCode(0);
        $pi = StaffUser::factory()->create();
        $admin = StaffUser::factory()->create();
        $pi->roles()->attach(ResearchRole::create(['kode' => ResearchRoleCode::Researcher->value]));
        $admin->roles()->attach(ResearchRole::create(['kode' => ResearchRoleCode::Administrator->value]));
        $participant = Participant::factory()->create();
        $audit = AuditLog::factory()->create();
        $grant = IdentifiedAccessGrant::create([
            'pengguna_id' => $admin->id, 'responden_id' => $participant->id, 'approved_by' => $pi->id,
            'purpose_code' => 'SYNTHETIC_TEST', 'starts_at' => now()->subMinute(), 'expires_at' => now()->addHour(),
        ]);
        $participantBefore = $participant->fresh()->getRawOriginal();
        $grantBefore = $grant->fresh()->getRawOriginal();
        $this->artisan('migrate', ['--path' => $scopeMigration, '--force' => true])->assertExitCode(0);
        $this->assertSame($participantBefore, $participant->fresh()->getRawOriginal());
        $this->assertSame($grantBefore, $grant->fresh()->getRawOriginal());
        $this->assertSame(0, $grant->scopes()->count());
        $access = app(ParticipantAccess::class);
        $this->assertFalse($access->canView($admin, $participant));
        $this->assertFalse($access->canView($admin, $participant, AccessGrantScope::Contact));
        $grant->scopes()->create(['scope' => AccessGrantScope::Technical]);
        $this->assertFalse($access->canView($admin, $participant));
        $this->assertFalse($access->canView($admin, $participant, AccessGrantScope::Contact));
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('identified_access_grant_scopes'));
        $this->assertSame($participantBefore, $participant->fresh()->getRawOriginal());
        $this->assertSame($grantBefore, $grant->fresh()->getRawOriginal());
        try {
            DB::table('audit_log')->where('id', $audit->id)->update(['aksi' => 'TAMPERED']);
            $this->fail('Scope migration lost the original immutable audit constraint.');
        } catch (QueryException $exception) {
            $this->assertSame('45000', $exception->errorInfo[0]);
        }
        $this->artisan('migrate', ['--path' => $scopeMigration, '--force' => true])->assertExitCode(0);
        $this->assertSame(0, $grant->scopes()->count());
        $this->assertFalse($access->canView($admin, $participant));
        $this->assertSame($grantBefore, $grant->fresh()->getRawOriginal());
    }
}
