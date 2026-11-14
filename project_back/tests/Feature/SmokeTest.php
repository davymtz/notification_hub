<?php

namespace Tests\Feature;

use Tests\TestCase;

class SmokeTest extends TestCase
{
    /**
     * Test the root endpoint returns service info
     */
    public function test_root_endpoint_returns_service_info(): void
    {
        $response = $this->getJson('/');

        $response->assertStatus(200)
            ->assertJson([
                'service' => 'Notification Hub API',
                'version' => '1.0.0',
                'status' => 'running',
            ]);
    }
}
