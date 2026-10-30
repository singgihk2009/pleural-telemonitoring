<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_approved_dashboard_origin_can_read_health(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173')
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_approved_dashboard_preflight_is_supported(): void
    {
        $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Content-Type',
        ])->options('/api/v1/health')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
    }

    public function test_other_origins_are_not_granted_browser_access(): void
    {
        // A fixed allowed-origin header rejects these origins in the browser:
        // it never matches the requesting origin and never uses a wildcard.
        foreach (['https://unapproved.example', 'http://127.0.0.1:5173', 'http://localhost:5174'] as $origin) {
            $this->withHeader('Origin', $origin)->getJson('/api/v1/health')
                ->assertOk()
                ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
                ->assertHeaderMissing('Access-Control-Allow-Credentials');

            $this->withHeaders([
                'Origin' => $origin,
                'Access-Control-Request-Method' => 'GET',
            ])->options('/api/v1/health')
                ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
                ->assertHeaderMissing('Access-Control-Allow-Credentials');
        }
    }
}
