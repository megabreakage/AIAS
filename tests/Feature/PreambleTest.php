<?php

declare(strict_types=1);

use App\Models\Tenant\Preamble;

// ---------------------------------------------------------------------------
// Model unit tests (no DB required)
// ---------------------------------------------------------------------------

describe('Preamble model', function (): void {
    it('has correct status constants', function (): void {
        expect(Preamble::STATUS_DRAFT)->toBe('draft')
            ->and(Preamble::STATUS_ACTIVE)->toBe('active')
            ->and(Preamble::STATUS_ARCHIVED)->toBe('archived');
    });

    it('STATUSES array contains all three statuses', function (): void {
        expect(Preamble::STATUSES)->toHaveCount(3)
            ->toContain('draft')
            ->toContain('active')
            ->toContain('archived');
    });

    it('generates reference number in correct format', function (): void {
        $preamble = new Preamble();
        $preamble->id = 42;

        $ref = $preamble->generateReferenceNumber();

        expect($ref)->toMatch('/^PR-42-\d+$/');
    });
});

// ---------------------------------------------------------------------------
// Route registration tests (no DB required)
// ---------------------------------------------------------------------------

describe('Preamble routes', function (): void {
    it('preamble endpoints are registered', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->map(fn ($r) => $r->uri())
            ->filter(fn ($uri) => str_contains($uri, 'preamble'))
            ->values();

        expect($routes->count())->toBeGreaterThanOrEqual(6);
    });
});
