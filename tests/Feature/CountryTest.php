<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Central\CountryController;
use App\Http\Resources\Central\Country\CountryResource;
use App\Models\Central\Country;
use App\Policies\CountryPolicy;
use App\Repositories\Central\CountryRepository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

// ---------------------------------------------------------------------------
// Route registration
// ---------------------------------------------------------------------------

describe('Country routes registration', function (): void {
    it('registers GET /api/v1/countries (index)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/countries' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /api/v1/countries (store)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/countries' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers GET /api/v1/countries/{identifier} (show)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/countries/{identifier}' && in_array('GET', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers PUT /api/v1/countries/{identifier} (update)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/countries/{identifier}' && in_array('PUT', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers DELETE /api/v1/countries/{identifier} (destroy)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/countries/{identifier}' && in_array('DELETE', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers POST /api/v1/countries/{identifier}/restore (restore)', function (): void {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => $r->uri() === 'api/v1/countries/{identifier}/restore' && in_array('POST', $r->methods(), true))
            ->values();

        expect($routes->count())->toBe(1);
    });

    it('registers exactly 6 country routes', function (): void {
        $count = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/v1/countries'))
            ->count();

        expect($count)->toBe(6);
    });

    it('country routes use auth:super_admin middleware', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/countries' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect(implode(',', $route->middleware()))->toContain('auth:super_admin');
    });

    it('country routes use the Central CountryController', function (): void {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/v1/countries' && in_array('GET', $r->methods(), true));

        expect($route)->not->toBeNull();
        expect($route->getActionName())->toContain(CountryController::class);
    });
});

// ---------------------------------------------------------------------------
// Model
// ---------------------------------------------------------------------------

describe('Country model', function (): void {
    it('has correct fillable attributes', function (): void {
        $country = new Country;

        expect($country->getFillable())->toBe([
            'identifier',
            'name',
            'slug',
            'continent_id',
            'short_code',
            'iso_code',
            'currency',
            'currency_name',
            'currency_sign',
            'country_code',
            'phone_digits',
            'status',
            'created_by',
            'updated_by',
        ]);
    });

    it('uses central connection', function (): void {
        $country = new Country;

        expect($country->getConnectionName())->toBe('central');
    });

    it('uses identifier as route key name', function (): void {
        $country = new Country;

        expect($country->getRouteKeyName())->toBe('identifier');
    });

    it('casts status to boolean', function (): void {
        $country = new Country;
        $casts = $country->getCasts();

        expect($casts)->toHaveKey('status');
        expect($casts['status'])->toBe('boolean');
    });

    it('casts continent_id to integer', function (): void {
        $country = new Country;
        $casts = $country->getCasts();

        expect($casts)->toHaveKey('continent_id');
        expect($casts['continent_id'])->toBe('integer');
    });

    it('casts phone_digits to integer', function (): void {
        $country = new Country;
        $casts = $country->getCasts();

        expect($casts)->toHaveKey('phone_digits');
        expect($casts['phone_digits'])->toBe('integer');
    });

    it('has continent relationship method', function (): void {
        $country = new Country;

        expect(method_exists($country, 'continent'))->toBeTrue();
    });

    it('has createdBy relationship method', function (): void {
        $country = new Country;

        expect(method_exists($country, 'createdBy'))->toBeTrue();
    });

    it('has updatedBy relationship method', function (): void {
        $country = new Country;

        expect(method_exists($country, 'updatedBy'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// Repository
// ---------------------------------------------------------------------------

describe('CountryRepository', function (): void {
    it('can be instantiated', function (): void {
        $repository = app(CountryRepository::class);

        expect($repository)->toBeInstanceOf(CountryRepository::class);
    });
});

// ---------------------------------------------------------------------------
// Policy
// ---------------------------------------------------------------------------

describe('CountryPolicy', function (): void {
    it('is registered in the gate', function (): void {
        $policy = Gate::getPolicyFor(Country::class);

        expect($policy)->not->toBeNull();
        expect($policy)->toBeInstanceOf(CountryPolicy::class);
    });
});

// ---------------------------------------------------------------------------
// Resource
// ---------------------------------------------------------------------------

describe('CountryResource', function (): void {
    it('transforms country to correct array structure', function (): void {
        $country = new Country([
            'identifier' => (string) Str::uuid(),
            'name' => 'Kenya',
            'slug' => 'kenya',
            'continent_id' => 1,
            'short_code' => 'KE',
            'iso_code' => 'KEN',
            'currency' => 'KES',
            'currency_name' => 'Kenyan Shilling',
            'currency_sign' => 'KSh',
            'country_code' => '+254',
            'phone_digits' => 9,
            'status' => true,
        ]);

        $resource = new CountryResource($country);
        $resolved = $resource->resolve();

        expect($resolved)->toHaveKeys([
            'identifier',
            'name',
            'slug',
            'short_code',
            'iso_code',
            'currency',
            'currency_name',
            'currency_sign',
            'country_code',
            'phone_digits',
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

describe('CountryFactory', function (): void {
    it('creates valid definition', function (): void {
        $factory = Country::factory();

        expect($factory)->toBeInstanceOf(Factory::class);

        $definition = $factory->definition();
        expect($definition)->toHaveKeys([
            'identifier',
            'name',
            'slug',
            'continent_id',
            'short_code',
            'iso_code',
            'currency',
            'currency_name',
            'currency_sign',
            'country_code',
            'phone_digits',
            'status',
        ]);
    });

    it('has inactive state', function (): void {
        $factory = Country::factory()->inactive();
        $definition = $factory->raw(['continent_id' => 1]);

        expect($definition['status'])->toBeFalse();
    });

    it('has active state', function (): void {
        $factory = Country::factory()->active();
        $definition = $factory->raw(['continent_id' => 1]);

        expect($definition['status'])->toBeTrue();
    });
});
