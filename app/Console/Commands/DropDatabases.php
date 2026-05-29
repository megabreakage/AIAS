<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Stancl\Tenancy\Contracts\TenantDatabaseManager;

#[Signature('db:drop
    {--tenant-only : Drop all tenant databases only (skip central)}
    {--tenant= : Drop a specific tenant database by tenant ID}
    {--force : Skip confirmation prompts}')]
#[Description('Drop central and/or tenant databases')]
class DropDatabases extends Command
{
    public function handle(): int
    {
        $tenantOnly = $this->option('tenant-only');
        $specificTenant = $this->option('tenant');
        $force = $this->option('force');

        if ($specificTenant && $tenantOnly) {
            $this->error('Cannot use --tenant-only and --tenant together. Use --tenant=ID to target one tenant.');

            return self::FAILURE;
        }

        // Resolve scope label for confirmation prompt
        $scope = match (true) {
            (bool) $specificTenant => "tenant '{$specificTenant}' database",
            $tenantOnly => 'all tenant databases',
            default => 'central database AND all tenant databases',
        };

        if (!$force) {
            $confirmed = $this->confirm("⚠️  This will permanently DROP {$scope}. Continue?", false);

            if (!$confirmed) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $this->info('🗑️  Starting database drop...');
        $this->newLine();

        $failed = false;

        if ($specificTenant) {
            if ($this->dropSpecificTenant($specificTenant) !== self::SUCCESS) {
                $failed = true;
            }
        } elseif ($tenantOnly) {
            if ($this->dropAllTenants() !== self::SUCCESS) {
                $failed = true;
            }
        } else {
            if ($this->dropAllTenants() !== self::SUCCESS) {
                $failed = true;
            }

            if ($this->dropCentral() !== self::SUCCESS) {
                $failed = true;
            }
        }

        $this->newLine();

        if ($failed) {
            $this->error('⚠️  Drop completed with errors. Review output above.');

            return self::FAILURE;
        }

        $this->info('✅ Done.');

        return self::SUCCESS;
    }

    /**
     * Drop the central database.
     */
    protected function dropCentral(): int
    {
        $this->info('━━━ Central Database ━━━');

        $dbName = config('database.connections.central.database');

        if (!$dbName) {
            $this->error('Central connection database name not resolved.');

            return self::FAILURE;
        }

        try {
            \DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$dbName}`");
            $this->info("  ✓ Dropped: {$dbName}");
            $this->newLine();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("  ✗ Failed to drop central DB '{$dbName}': {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Drop all tenant databases found in the central DB.
     */
    protected function dropAllTenants(): int
    {
        $this->info('━━━ Tenant Databases ━━━');

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found in central database.');
            $this->newLine();

            return self::SUCCESS;
        }

        $migratable = $tenants->filter(fn (Tenant $t) => $this->isMigratableTenant($t));
        $skipped = $tenants->count() - $migratable->count();

        if ($skipped > 0) {
            $this->warn("Skipping {$skipped} non-droppable tenant(s) (SYSTEM / test leftovers).");
        }

        if ($migratable->isEmpty()) {
            $this->warn('No droppable tenants found.');
            $this->newLine();

            return self::SUCCESS;
        }

        $this->info("Found {$migratable->count()} tenant(s) to drop.");
        $this->newLine();

        $failed = [];

        foreach ($migratable as $tenant) {
            if ($this->dropTenantDatabase($tenant) !== self::SUCCESS) {
                $failed[] = $tenant->getTenantKey();
            }
        }

        if (!empty($failed)) {
            $this->error('Failed tenants: '.implode(', ', $failed));

            return self::FAILURE;
        }

        $this->info("All {$migratable->count()} tenant database(s) dropped.");
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Drop a single tenant database by tenant ID.
     */
    protected function dropSpecificTenant(string $tenantId): int
    {
        $this->info("━━━ Tenant: {$tenantId} ━━━");

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");

            return self::FAILURE;
        }

        $result = $this->dropTenantDatabase($tenant);
        $this->newLine();

        return $result;
    }

    /**
     * Drop a single tenant's database using Stancl's database manager.
     */
    protected function dropTenantDatabase(Tenant $tenant): int
    {
        $tenantId = $tenant->getTenantKey();
        $dbName = $tenant->database()->getName();

        try {
            /** @var TenantDatabaseManager $manager */
            $manager = $tenant->database()->manager();

            if (!$manager->databaseExists($dbName)) {
                $this->warn("  ⚠  Database '{$dbName}' does not exist — skipping tenant {$tenantId}.");

                return self::SUCCESS;
            }

            $manager->deleteDatabase($tenant);
            $this->info("  ✓ Dropped: {$dbName} (tenant: {$tenantId})");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("  ✗ Failed '{$dbName}' (tenant: {$tenantId}): {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Skip SYSTEM tenant (no DB) and TEST* tenants (test leftovers).
     */
    protected function isMigratableTenant(Tenant $tenant): bool
    {
        $id = (string) $tenant->getTenantKey();

        return $id !== 'SYSTEM' && !str_starts_with($id, 'TEST');
    }
}
