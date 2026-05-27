<?php

declare(strict_types=1);

use App\Models\PriorityLevel;
use App\Models\User;
use Database\Seeders\DefaultTenantRoleSeeder;

uses(Tests\Traits\RefreshDatabaseWithTenancy::class);

beforeEach(function (): void {
    $this->seed(DefaultTenantRoleSeeder::class);
    $this->user = User::factory()->create();
    $this->user->assignRole('tenant-admin');
    $this->actingAs($this->user, 'api');
});

it('can list priority_levels', function (): void {
    PriorityLevel::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/priority-levels');

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

it('rejects unauthenticated access to priority_levels', function (): void {
    $response = $this->withHeaders(['Authorization' => ''])
        ->getJson('/api/v1/priority-levels');

    $response->assertUnauthorized();
});

it('can search priority_levels by term', function (): void {
    PriorityLevel::factory()->create(['name' => 'Searchable Item']);
    PriorityLevel::factory()->create(['name' => 'Other Item']);

    $response = $this->getJson('/api/v1/priority-levels?search=Searchable');

    $response->assertOk();
});

it('can filter priority_levels by active status', function (): void {
    PriorityLevel::factory()->create(['is_active' => true]);
    PriorityLevel::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/priority-levels?is_active=1');

    $response->assertOk();
});

it('can show a priority_level', function (): void {
    $priorityLevel = PriorityLevel::factory()->create();

    $response = $this->getJson('/api/v1/priority-levels/' . $priorityLevel->identifier);

    $response->assertOk()
        ->assertJsonPath('data.id', $priorityLevel->identifier);
});

it('returns 404 for non-existent priority_level', function (): void {
    $response = $this->getJson('/api/v1/priority-levels/non-existent-id');

    $response->assertNotFound();
});

it('can create a priority_level', function (): void {
    $response = $this->postJson('/api/v1/priority-levels', [
        'name'      => 'Test Priority Level',
        'description' => 'A test priority level.',
        'level'     => 1,
        'is_active' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Test Priority Level');
});

it('rejects duplicate priority_level name on create', function (): void {
    PriorityLevel::factory()->create(['name' => 'Duplicate']);

    $response = $this->postJson('/api/v1/priority-levels', [
        'name'      => 'Duplicate',
        'level'     => 2,
        'is_active' => true,
    ]);

    $response->assertUnprocessable();
});

it('can update a priority_level', function (): void {
    $priorityLevel = PriorityLevel::factory()->create();

    $response = $this->putJson('/api/v1/priority-levels/' . $priorityLevel->identifier, [
        'name' => 'Updated Priority Level',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Priority Level');
});

it('allows same name on update for same priority_level', function (): void {
    $priorityLevel = PriorityLevel::factory()->create(['name' => 'Keep Name']);

    $response = $this->putJson('/api/v1/priority-levels/' . $priorityLevel->identifier, [
        'name' => 'Keep Name',
    ]);

    $response->assertOk();
});

it('returns 404 when updating non-existent priority_level', function (): void {
    $response = $this->putJson('/api/v1/priority-levels/non-existent-id', [
        'name' => 'Does not matter',
    ]);

    $response->assertNotFound();
});

it('can soft delete a priority_level', function (): void {
    $priorityLevel = PriorityLevel::factory()->create();

    $response = $this->deleteJson('/api/v1/priority-levels/' . $priorityLevel->identifier);

    $response->assertOk();
    $this->assertSoftDeleted('priority_levels', ['id' => $priorityLevel->id]);
});

it('can restore a soft-deleted priority_level', function (): void {
    $priorityLevel = PriorityLevel::factory()->create();
    $priorityLevel->delete();

    $response = $this->postJson('/api/v1/priority-levels/' . $priorityLevel->identifier . '/restore');

    $response->assertOk();
    $this->assertDatabaseHas('priority_levels', [
        'id' => $priorityLevel->id,
        'deleted_at' => null,
    ]);
});

it('cannot access other tenant priority_levels', function (): void {
    $priorityLevel = PriorityLevel::factory()->create(['tenant_id' => 'other-tenant']);

    $response = $this->getJson('/api/v1/priority-levels/' . $priorityLevel->identifier);

    $response->assertNotFound();
});
