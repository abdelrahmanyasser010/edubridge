<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_returns_the_health_status(): void
    {
        $response = $this->getJson('/health');

        $response
            ->assertOk()
            ->assertExactJson([
                'service' => 'edubridge-backend',
                'status' => 'ok',
            ]);
    }
}
