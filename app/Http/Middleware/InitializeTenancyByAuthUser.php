<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\TenantMismatchException;
use App\Models\Central\Tenant;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;

/**
 * Initializes tenancy from the authenticated user's tenant_id column.
 * Eliminates the need to pass tenant_id in every request body/query.
 */
final class InitializeTenancyByAuthUser
{
    public function __construct(protected Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->method() === 'OPTIONS') {
            return $next($request);
        }

        $user = $request->user();

        if (!$user || empty($user->tenant_id)) {
            return $next($request);
        }

        $tenant = Tenant::where('identifier', $user->tenant_id)->first();

        if (!$tenant) {
            throw new TenantMismatchException('User tenant not found.');
        }

        $this->tenancy->initialize($tenant);

        return $next($request);
    }
}
