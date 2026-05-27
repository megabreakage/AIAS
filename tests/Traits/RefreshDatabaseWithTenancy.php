<?php

namespace Tests\Traits;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;

/**
 * Custom RefreshDatabase trait that properly handles multi-tenant testing.
 *
 * This trait extends Laravel's RefreshDatabase to:
 * 1. Run both central and tenant migrations
 * 2. Avoid SQLite VACUUM issues within transactions
 * 3. Properly reset the database state between tests
 *
 * Use this trait instead of RefreshDatabase in test classes that
 * interact with tenant data.
 */
trait RefreshDatabaseWithTenancy
{
    use RefreshDatabase {
        refreshDatabase as parentRefreshDatabase;
    }

    /**
     * Refresh the test database.
     */
    protected function refreshInMemoryDatabase(): void
    {
        // Run central migrations
        Artisan::call('migrate', [
            '--force' => true,
        ]);

        // Run tenant migrations on the same connection
        Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant',
            '--realpath' => false,
            '--force' => true,
        ]);

        $this->app[Kernel::class]->setArtisan(null);
    }

    /**
     * Refresh a conventional test database.
     */
    protected function refreshTestDatabase(): void
    {
        if (!RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());

            // Run tenant migrations after fresh migration
            $this->artisan('migrate', [
                '--path' => 'database/migrations/tenant',
                '--realpath' => false,
                '--force' => true,
            ]);

            // Seed permissions outside of transaction to avoid deadlocks
            $this->artisan('db:seed', [
                '--class' => 'RolePermissionsSeeder',
                '--force' => true,
            ]);

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

// Configure database for better concurrency (no-op for MySQL)
        if (method_exists($this, 'configureSqliteConcurrency')) {
            $this->configureSqliteConcurrency();
        }

        $this->beginDatabaseTransaction();
    }

    /**
     * Determine if an in-memory database is being used.
     */
    protected function usingInMemoryDatabase(): bool
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        return $driver === 'sqlite' && config("database.connections.{$connection}.database") === ':memory:';
    }
}