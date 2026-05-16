<?php

declare(strict_types=1);

it('rejects invalid super admin credentials', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [
        'email'    => 'nobody@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertUnauthorized();
});

it('rejects unauthenticated access to me endpoint', function (): void {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized();
});

it('rejects unauthenticated access to tenants list', function (): void {
    $response = $this->getJson('/api/v1/tenants');

    $response->assertUnauthorized();
});
