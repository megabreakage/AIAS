<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ensures all API requests are treated as JSON.
 *
 * Prevents validation failures caused by a missing Content-Type header when
 * clients send a raw JSON body without setting Content-Type: application/json.
 */
final class ForceJsonBody
{
    public function handle(Request $request, Closure $next): mixed
    {
        // Always signal that we accept JSON responses.
        $request->headers->set('Accept', 'application/json');

        // If the body looks like JSON but Content-Type was not set (or was set
        // to something other than application/json), force the header so
        // Laravel's body parser picks it up via $request->json() / $request->all().
        if (!$request->isJson()) {
            $content = trim((string) $request->getContent());

            if (str_starts_with($content, '{') || str_starts_with($content, '[')) {
                $request->headers->set('Content-Type', 'application/json');
            }
        }

        return $next($request);
    }
}
