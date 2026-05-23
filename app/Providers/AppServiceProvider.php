<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Central\Continent;
use App\Models\Central\Country;
use App\Models\Tenant\Preamble;
use App\Models\User;
use App\Policies\ContinentPolicy;
use App\Policies\CountryPolicy;
use App\Policies\PreamblePolicy;
use App\Policies\UserPolicy;
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
        Gate::policy(Continent::class, ContinentPolicy::class);
        Gate::policy(Country::class, CountryPolicy::class);
        Gate::policy(Preamble::class, PreamblePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        Event::listen(TenancyBootstrapped::class, function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        Event::listen(RevertedToCentralContext::class, function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });

        Gate::before(function (mixed $user, string $ability): ?bool {
            if ($user instanceof User && $user->hasRole('super-admin')) {
                return true;
            }

            return null;
        });
    }
}
