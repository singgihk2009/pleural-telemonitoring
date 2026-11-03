<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationLifecycleTest extends TestCase
{
    public function test_additive_migrations_can_be_reversed_and_reapplied_on_a_new_disposable_schema(): void
    {
        if (! getenv('TELENAPAS_MIGRATION_TESTS')) {
            $this->markTestSkipped('Opt-in lifecycle check requires a new disposable MySQL schema and its administrator.');
        }
        if (DB::getDriverName() !== 'mysql' || ! preg_match('/^[a-z0-9_]+_migration_test$/', DB::connection()->getDatabaseName())) {
            throw new \RuntimeException('Lifecycle check requires a dedicated database ending in _migration_test.');
        }
        $this->assertSame([], Schema::getTables(DB::connection()->getDatabaseName()), 'Lifecycle schema must be entirely empty before this test.');
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertTrue(Schema::hasTable('responden'));
        $this->assertTrue(Schema::hasTable('notification_deliveries'));
        $this->assertNotEmpty(DB::select("SHOW TRIGGERS WHERE `Table` = 'audit_log'"));
        $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('responden'));
        $this->assertFalse(Schema::hasTable('audit_log'));
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertTrue(Schema::hasTable('entri_harian'));
        $this->assertSame(0, DB::table('responden')->count());
    }
}
