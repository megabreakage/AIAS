<?php

use App\Models\Central\SuperAdmin;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;

uses(DatabaseTransactions::class);

it('can create a tenant record with events faked', function (): void {
    Event::fake([TenantCreated::class, TenantDeleted::class]);

    $admin = SuperAdmin::factory()->create();

    $tenant = Tenant::create([
        'name'     => 'Test Tenant',
        'owner_id' => $admin->id,
        'domain'   => 'test.localhost',
    ]);

    expect($tenant->id)->toBeGreaterThan(0);
    expect($tenant->name)->toBe('Test Tenant');
});
