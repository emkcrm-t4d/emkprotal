<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    /**
     * Test /up health check probe returns 200 OK.
     */
    public function test_up_probe_returns_successful_status(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ok',
        ]);
    }

    /**
     * Test /healthz health check probe returns 200 OK with status and environment.
     */
    public function test_healthz_probe_returns_ok_and_environment(): void
    {
        $response = $this->get('/healthz');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'environment',
        ]);
        $response->assertJson([
            'status' => 'ok',
        ]);
    }
}
