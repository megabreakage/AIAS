<?php

use App\Models\Central\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;

uses(DatabaseTransactions::class);

it('can create a super admin', function (): void {
    $admin = SuperAdmin::factory()->create();

    expect($admin)->toBeInstanceOf(SuperAdmin::class);
    expect($admin->id)->toBeGreaterThan(0);
});

it('can authenticate as super admin', function (): void {
    $admin = SuperAdmin::factory()->create();
    Passport::actingAs($admin, [], 'super_admin');

    $this->getJson('/api/v1/tenants')
        ->assertOk();
});
