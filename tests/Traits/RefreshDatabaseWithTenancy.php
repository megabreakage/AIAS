<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

/**
 * Trait RefreshDatabaseWithTenancy
 *
 * Extends the standard RefreshDatabase behaviour to also set up a tenant
 * context before each test and tear it down afterwards.
 *
 * Usage (Pest):
 *   uses(Tests\Traits\RefreshDatabaseWithTenancy::class);
 *
 * The trait:
 *   1. Refreshes the central database (roles, permissions, OAuth tables).
 *   2. Creates a test tenant and initialises Stancl Tenancy.
 *   3. Runs all tenant-scope migrations inside the test tenant's database.
 *   4. Ends tenancy after each test to restore the central connection.
 */
trait RefreshDatabaseWithTenancy
{
    use RefreshDatabase;

    /** The tenant used throughout this test. */
    protected ?Tenant $testTenant = null;

    /**
     * Hook called by Pest/PHPUnit before every test method.
     * Because this trait uses `setUp`, Pest automatically calls it when the
     * trait is applied via `uses()`.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenancy();
    }

    /**
     * Hook called by Pest/PHPUnit after every test method.
     */
    protected function tearDown(): void
    {
        $this->tearDownTenancy();

        parent::tearDown();
    }

    /**
     * Create a throwaway test tenant and initialise tenancy for this test.
     */
    protected function setUpTenancy(): void
    {
        // Create a deterministic tenant for the test run
        $this->testTenant = Tenant::create([
            'id'     => 'test-tenant-' . uniqid(),
            'name'   => 'Test Tenant',
            'plan'   => 'starter',
            'status' => 'active',
        ]);

        // Switch the active DB connection to the tenant's database
        tenancy()->initialize($this->testTenant);

        // Run all tenant-scope migrations inside the tenant's database
        Artisan::call('migrate', [
            '--path'     => 'database/migrations/tenant',
            '--force'    => true,
            '--realpath' => false,
        ]);
    }

    /**
     * End the tenant session and restore the central DB connection.
     */
    protected function tearDownTenancy(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}
