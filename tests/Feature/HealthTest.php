<?php

declare(strict_types=1);

it('returns ok on health check', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonStructure(['data' => ['status', 'timestamp', 'version'], 'meta' => ['request_id', 'version']]);
});
