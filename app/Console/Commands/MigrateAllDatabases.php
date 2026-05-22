<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('migrate:all
    {--fresh : Drop all tables and re-run all migrations (with --central-only, also wipes all tenant databases)}
    {--seed : Seed the databases after migration}
    {--seeder= : Specify a specific seeder class to run (implies --seed)}
    {--central-only : Only migrate the central database}
    {--tenants-only : Only migrate tenant databases}
    {--tenant= : Migrate a specific tenant by ID}
    {--force : Force the operation to run in production}')]
#[Description('Run migrations for central and tenant databases with optional fresh wipe and seeding')]
class MigrateAllDatabases extends Command
{
    public function handle(): int
    {
        $this->info('🚀 Starting database migration sync...');
        $this->newLine();

        $centralOnly = $this->option('central-only');
        $tenantsOnly = $this->option('tenants-only');
        $specificTenant = $this->option('tenant');
        $fresh = $this->option('fresh');
        $shouldSeed = $this->option('seed');
        $seederClass = $this->option('seeder');
        $force = $this->option('force');

        if ($seederClass) {
            $seederClass = 'Database\\Seeders\\'.$seederClass;
            $shouldSeed = true;
        }

        if ($centralOnly && $tenantsOnly) {
            $this->error('Cannot use --central-only and --tenants-only together.');

            return self::FAILURE;
        }

        $failed = false;

        if (!$tenantsOnly) {
            $result = $this->migrateCentral($fresh, $shouldSeed, $force, $seederClass);
            if ($result !== self::SUCCESS) {
                $failed = true;
            }
        }

        // When --fresh --central-only: wipe all tenant databases but do not re-migrate them.
        if ($fresh && $centralOnly) {
            $result = $this->wipeTenantDatabases($specificTenant);
            if ($result !== self::SUCCESS) {
                $failed = true;
            }
        }

        if (!$centralOnly) {
            $result = $this->migrateTenants($fresh, $shouldSeed, $force, $specificTenant, $seederClass);
            if ($result !== self::SUCCESS) {
                $failed = true;
            }
        }

        $this->newLine();

        if ($failed) {
            $this->error('⚠️  Migration completed with errors. Review the output above.');

            return self::FAILURE;
        }

        $this->info('✅ All database migrations completed successfully!');

        return self::SUCCESS;
    }

    /**
     * Migrate (or fresh-wipe and migrate) the central database.
     */
    protected function migrateCentral(bool $fresh, bool $shouldSeed, bool $force, ?string $seederClass = null): int
    {
        $this->info('━━━ Central Database ━━━');

        $migrateOptions = [];

        if ($force) {
            $migrateOptions['--force'] = true;
        }

        $command = $fresh ? 'migrate:fresh' : 'migrate';
        $result = $this->call($command, $migrateOptions);

        if ($result !== self::SUCCESS) {
            $this->error('Central database migration failed.');

            return self::FAILURE;
        }

        if ($shouldSeed) {
            $this->info('Seeding central database...');
            $seedResult = $seederClass
                ? $this->seedSpecificClass($seederClass, $force)
                : $this->seedCentral($force);

            if ($seedResult !== self::SUCCESS) {
                $this->warn('Central database seeding encountered issues.');

                return self::FAILURE;
            }
        }

        $this->info('Central database migration complete.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Seed the central database.
     */
    protected function seedCentral(bool $force): int
    {
        $seedOptions = ['--class' => 'Database\\Seeders\\Central\\DatabaseSeeder'];

        if ($force) {
            $seedOptions['--force'] = true;
        }

        return $this->call('db:seed', $seedOptions);
    }

    /**
     * Seed a specific seeder class.
     */
    protected function seedSpecificClass(string $seederClass, bool $force): int
    {
        $seedOptions = ['--class' => $seederClass];

        if ($force) {
            $seedOptions['--force'] = true;
        }

        return $this->call('db:seed', $seedOptions);
    }

    /**
     * Wipe all tenant databases without re-migrating (used by --fresh --central-only).
     */
    protected function wipeTenantDatabases(?string $specificTenant): int
    {
        $this->info('━━━ Wiping Tenant Databases ━━━');

        $tenants = $this->resolveMigratableTenants($specificTenant);

        if ($tenants === null) {
            return self::SUCCESS;
        }

        $failedTenants = [];

        foreach ($tenants as $tenant) {
            $tenantId = $tenant->getTenantKey();

            try {
                $tenant->run(function () use ($tenantId, &$failedTenants) {
                    $result = $this->call('migrate:fresh', ['--force' => true]);

                    if ($result !== self::SUCCESS) {
                        $failedTenants[] = $tenantId;
                        $this->error("  Wipe failed for tenant: {$tenantId}");
                    } else {
                        $this->info("  ✓ Tenant {$tenantId} wiped.");
                    }
                });
            } catch (\Throwable $e) {
                $failedTenants[] = $tenantId;
                $this->error("  ✗ Tenant {$tenantId} failed: {$e->getMessage()}");
            }
        }

        $this->newLine();

        if (!empty($failedTenants)) {
            $this->error('Failed tenants: '.implode(', ', $failedTenants));

            return self::FAILURE;
        }

        $this->info("All {$tenants->count()} tenant database(s) wiped.");

        return self::SUCCESS;
    }

    /**
     * Migrate tenant databases, optionally fresh.
     */
    protected function migrateTenants(bool $fresh, bool $shouldSeed, bool $force, ?string $specificTenant, ?string $seederClass = null): int
    {
        $this->info('━━━ Tenant Databases ━━━');

        $tenants = $this->resolveMigratableTenants($specificTenant);

        if ($tenants === null) {
            return self::SUCCESS;
        }

        $this->info("Found {$tenants->count()} tenant(s) to migrate.");
        $this->newLine();

        $migrateOptions = [
            '--path' => [database_path('migrations/tenant')],
            '--realpath' => true,
        ];

        if ($force) {
            $migrateOptions['--force'] = true;
        }

        $command = $fresh ? 'migrate:fresh' : 'migrate';

        $failedTenants = [];

        foreach ($tenants as $tenant) {
            $tenantId = $tenant->getTenantKey();
            $this->info("Migrating tenant: {$tenantId}");

            try {
                $tenant->run(function () use ($command, $migrateOptions, $shouldSeed, $seederClass, $tenantId, &$failedTenants) {
                    $result = $this->call($command, $migrateOptions);

                    if ($result !== self::SUCCESS) {
                        $failedTenants[] = $tenantId;
                        $this->error("  Migration failed for tenant: {$tenantId}");

                        return;
                    }

                    if ($shouldSeed) {
                        $this->info("  Seeding tenant: {$tenantId}");
                        $seedResult = $this->call('db:seed', [
                            '--class' => $seederClass ?? 'Database\\Seeders\\Tenant\\TenantDatabaseSeeder',
                        ]);

                        if ($seedResult !== self::SUCCESS) {
                            $this->warn("  Seeding issues for tenant: {$tenantId}");
                        }
                    }

                    $this->info("  ✓ Tenant {$tenantId} complete.");
                });
            } catch (\Throwable $e) {
                $failedTenants[] = $tenantId;
                $this->error("  ✗ Tenant {$tenantId} failed: {$e->getMessage()}");
            }
        }

        $this->newLine();

        if (!empty($failedTenants)) {
            $this->error('Failed tenants: '.implode(', ', $failedTenants));

            return self::FAILURE;
        }

        $this->info("All {$tenants->count()} tenant(s) migrated successfully.");

        return self::SUCCESS;
    }

    /**
     * Resolve migratable tenants, returning null when none are found (already warned).
     *
     * @return Collection<int, Tenant>|null
     */
    protected function resolveMigratableTenants(?string $specificTenant): ?Collection
    {
        $query = Tenant::query();

        if ($specificTenant) {
            $query->where('id', $specificTenant);
        }

        $all = $query->get();

        if ($all->isEmpty()) {
            $this->warn($specificTenant
                ? "Tenant '{$specificTenant}' not found."
                : 'No tenants found.');

            return null;
        }

        $migratable = $all->filter(fn (Tenant $t) => $this->isMigratableTenant($t));
        $skipped = $all->count() - $migratable->count();

        if ($skipped > 0) {
            $this->warn("Skipping {$skipped} non-migratable tenant(s) (SYSTEM / test leftovers).");
        }

        if ($migratable->isEmpty()) {
            $this->warn('No migratable tenants found.');

            return null;
        }

        return $migratable->values();
    }

    /**
     * Determine if a tenant should be migrated.
     *
     * Skips the SYSTEM tenant (no database) and TEST* tenants (test leftovers).
     */
    protected function isMigratableTenant(Tenant $tenant): bool
    {
        $tenantId = (string) $tenant->getTenantKey();

        if ($tenantId === 'SYSTEM') {
            return false;
        }

        if (str_starts_with($tenantId, 'TEST')) {
            return false;
        }

        return true;
    }
}
