<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_liveness_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_api_readiness_checks_the_database(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'database' => 'connected']);
    }

    public function test_database_failure_returns_a_sanitized_unavailable_response(): void
    {
        DB::shouldReceive('select')->once()->with('SELECT 1')
            ->andThrow(new QueryException('mysql', 'SELECT 1', [], new PDOException('private connection details')));

        $this->getJson('/api/v1/health')
            ->assertStatus(503)
            ->assertExactJson(['status' => 'unavailable', 'database' => 'disconnected']);
    }

    public function test_unversioned_health_route_is_not_exposed(): void
    {
        $this->getJson('/api/health')->assertNotFound();
    }
}
