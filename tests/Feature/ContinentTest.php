<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Central\ContinentController;
use App\Http\Resources\Central\Continent\ContinentResource;
use App\Models\Central\Continent;
use App\Policies\ContinentPolicy;
use App\Repositories\Central\ContinentRepository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

describe('Continent routes registration', function (): void {
    it('registers GET /api/v1/continents (index)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/continents' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /api/v1/continents (store)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/continents' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /api/v1/continents/{identifier} (show)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/continents/{identifier}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers PUT /api/v1/continents/{identifier} (update)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/continents/{identifier}' && in_array('PUT', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /api/v1/continents/{identifier} (destroy)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/continents/{identifier}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /api/v1/continents/{identifier}/restore (restore)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/continents/{identifier}/restore' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers exactly 6 continent routes', function (): void {
        $count = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/continents'))
            ->count();

        expect($count)->toBe(6);
    });

    it('continent routes use auth:super_admin middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/continents' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('auth:super_admin');
    });

    it('continent routes use the Central ContinentController', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/continents' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain(ContinentController::class);
    });
});

// ---------------------------------------------------------------------------
// Model
// ---------------------------------------------------------------------------

describe('Continent model', function (): void {
    it('has correct fillable attributes', function (): void {
        $continent = new Continent;

        expect($continent->getFillable())->toBe([
            'identifier',
            'name',
            'slug',
            'short_code',
            'iso_code',
            'status',
            'created_by',
            'updated_by',
        ]);
    });

    it('uses central connection', function (): void {
        $continent = new Continent;

        expect($continent->getConnectionName())->toBe('central');
    });

    it('uses identifier as route key name', function (): void {
        $continent = new Continent;

        expect($continent->getRouteKeyName())->toBe('identifier');
    });

    it('casts status to boolean', function (): void {
        $continent = new Continent;
        $casts = $continent->getCasts();

        expect($casts)->toHaveKey('status');
        expect($casts['status'])->toBe('boolean');
    });
});

// ---------------------------------------------------------------------------
// Repository
// ---------------------------------------------------------------------------

describe('ContinentRepository', function (): void {
    it('can be instantiated', function (): void {
        $repository = app(ContinentRepository::class);

        expect($repository)->toBeInstanceOf(ContinentRepository::class);
    });
});

// ---------------------------------------------------------------------------
// Policy
// ---------------------------------------------------------------------------

describe('ContinentPolicy', function (): void {
    it('is registered in the gate', function (): void {
        $policy = Gate::getPolicyFor(Continent::class);

        expect($policy)->not->toBeNull();
        expect($policy)->toBeInstanceOf(ContinentPolicy::class);
    });
});

// ---------------------------------------------------------------------------
// Resource
// ---------------------------------------------------------------------------

describe('ContinentResource', function (): void {
    it('transforms continent to correct array structure', function (): void {
        $continent = new Continent([
            'identifier' => (string) Str::uuid(),
            'name' => 'Africa',
            'slug' => 'africa',
            'short_code' => 'AF',
            'iso_code' => 'AF',
            'status' => true,
        ]);

        $resource = new ContinentResource($continent);
        $resolved = $resource->resolve();

        expect($resolved)->toHaveKeys([
            'identifier',
            'name',
            'slug',
            'short_code',
            'iso_code',
            'status',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    });
});

// ---------------------------------------------------------------------------
// Factory
// ---------------------------------------------------------------------------

describe('ContinentFactory', function (): void {
    it('creates valid definition', function (): void {
        $factory = Continent::factory();

        expect($factory)->toBeInstanceOf(Factory::class);

        $definition = $factory->definition();
        expect($definition)->toHaveKeys(['identifier', 'name', 'slug', 'short_code', 'iso_code', 'status']);
    });

    it('has inactive state', function (): void {
        $factory = Continent::factory()->inactive();
        $definition = $factory->raw();

        expect($definition['status'])->toBeFalse();
    });

    it('has active state', function (): void {
        $factory = Continent::factory()->active();
        $definition = $factory->raw();

        expect($definition['status'])->toBeTrue();
    });
});
