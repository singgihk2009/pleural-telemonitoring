<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_liveness_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_api_readiness_checks_the_database(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'database' => 'connected']);
    }
}
