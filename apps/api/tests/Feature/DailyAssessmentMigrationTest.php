<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DailyEntry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DailyAssessmentMigrationTest extends TestCase
{
    public function test_upgrade_preserves_legacy_immutable_reports_without_inventing_provenance(): void
    {
        if (! getenv('TELENAPAS_MIGRATION_TESTS')) {
            $this->markTestSkipped('Opt-in requires a new disposable MySQL schema and administrator.');
        }
        if (DB::getDriverName() !== 'mysql' || ! preg_match('/^[a-z0-9_]+_assessment_migration_test$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Use a dedicated _assessment_migration_test schema.');
        }
        $this->assertSame([], Schema::getTables(DB::connection()->getDatabaseName()));
        $paths = array_map(fn ($file) => 'database/migrations/'.basename($file), glob(database_path('migrations/*.php')));
        $legacy = array_values(array_filter($paths, fn ($path) => ! str_contains($path, '000006_')));
        $this->artisan('migrate', ['--force' => true, '--path' => $legacy])->assertExitCode(0);
        $entry = DailyEntry::factory()->create();
        $audit = AuditLog::factory()->create();
        $before = (array) DB::table('entri_harian')->where('id', $entry->id)->first();
        $auditBefore = (array) DB::table('audit_log')->where('id', $audit->id)->first();
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $after = (array) DB::table('entri_harian')->where('id', $entry->id)->first();
        $this->assertNull($after['assessment_setting_id']);
        $this->assertNull($after['submission_fingerprint']);
        unset($after['assessment_setting_id'], $after['submission_fingerprint']);
        $this->assertSame($before, $after);
        $this->assertSame($auditBefore, (array) DB::table('audit_log')->where('id', $audit->id)->first());
        foreach (['entri_harian', 'audit_log'] as $table) {
            try {
                DB::table($table)->where('id', $table === 'entri_harian' ? $entry->id : $audit->id)->delete();
                $this->fail('Immutable trigger removed.');
            } catch (QueryException $exception) {
                $this->assertSame('45000', $exception->errorInfo[0]);
            }
        }
    }
}
