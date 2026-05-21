<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Central\Continent;
use App\Models\Central\Country;
use App\Models\Central\SuperAdmin;
use App\Models\Tenant\Preamble;
use App\Policies\ContinentPolicy;
use App\Policies\CountryPolicy;
use App\Policies\PreamblePolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\RevertedToCentralContext;
use Stancl\Tenancy\Events\TenancyBootstrapped;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Register policies
        Gate::policy(Continent::class, ContinentPolicy::class);
        Gate::policy(Country::class, CountryPolicy::class);
        Gate::policy(Preamble::class, PreamblePolicy::class);

        // Reset Spatie permission cache when switching tenant context
        Event::listen(TenancyBootstrapped::class, function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        Event::listen(RevertedToCentralContext::class, function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        // Super admin bypasses all gate checks
        Gate::before(function (mixed $user, string $ability): ?bool {
            if ($user instanceof SuperAdmin && $user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });
    }
}
