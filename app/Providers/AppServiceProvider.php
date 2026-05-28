<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\TenancyBootstrapped;
use Stancl\Tenancy\Events\RevertedToCentralContext;
use App\Models\Central\SuperAdmin;
use App\Models\PriorityLevel;
use App\Policies\PriorityLevelPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Reset Spatie permission cache when switching tenant context
        Event::listen(TenancyBootstrapped::class, function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        Event::listen(RevertedToCentralContext::class, function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        // Register model policies
        Gate::policy(PriorityLevel::class, PriorityLevelPolicy::class);

        // Super admin bypasses all gate checks
        Gate::before(function (SuperAdmin $superAdmin, string $ability): ?bool {
            if ($superAdmin->hasRole('super-admin')) {
                return true;
            }
            return null;
        });
    }
}
