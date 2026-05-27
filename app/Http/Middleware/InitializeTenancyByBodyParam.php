<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\IdentificationMiddleware;
use Stancl\Tenancy\Resolvers\RequestDataTenantResolver;
use Stancl\Tenancy\Tenancy;

/**
 * Initializes tenancy from a JSON body or query string parameter.
 * Reads `tenant_id` via $request->input() so JSON payloads are supported.
 */
final class InitializeTenancyByBodyParam extends IdentificationMiddleware
{
    public static string $bodyParameter = 'tenant_id';

    /** @var callable|null */
    public static $onFail;

    public function __construct(Tenancy $tenancy, RequestDataTenantResolver $resolver)
    {
        $this->tenancy = $tenancy;
        $this->resolver = $resolver;
    }

    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->method() === 'OPTIONS') {
            return $next($request);
        }

        return $this->initializeTenancy(
            $request,
            $next,
            $request->input(static::$bodyParameter),
        );
    }
}
