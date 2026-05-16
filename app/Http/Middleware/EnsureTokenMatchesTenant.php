<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\TenantMismatchException;
use Closure;
use Illuminate\Http\Request;

final class EnsureTokenMatchesTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->user()?->token();
        if (! $token) {
            return $next($request);
        }

        $currentTenant = function_exists('tenant') ? tenant() : null;
        if (! $currentTenant) {
            return $next($request);
        }

        if ($token->tenant_id !== $currentTenant->id) {
            throw new TenantMismatchException('Token does not match the current tenant.');
        }

        return $next($request);
    }
}
