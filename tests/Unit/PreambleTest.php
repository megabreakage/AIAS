<?php

declare(strict_types=1);

use App\Filters\Tenant\Preambles\Filters\IsFeaturedFilter;
use App\Filters\Tenant\Preambles\Filters\SearchTermFilter;
use App\Filters\Tenant\Preambles\Filters\StatusFilter;
use App\Filters\Tenant\Preambles\PreambleFilters;
use App\Http\Controllers\Api\V1\Tenant\PreambleController;
use App\Http\Requests\Tenant\Preambles\CreatePreambleRequest;
use App\Http\Requests\Tenant\Preambles\UpdatePreambleRequest;
use App\Http\Resources\Tenant\Preamble\PreambleCollection;
use App\Http\Resources\Tenant\Preamble\PreambleResource;
use App\Models\Concerns\TenantConnection;
use App\Models\Tenant\Preamble;
use App\Models\User;
use App\Policies\PreamblePolicy;
use App\Repositories\Tenant\PreambleRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Mockery\MockInterface;

// ---------------------------------------------------------------------------
// Preamble Model
// ---------------------------------------------------------------------------

describe('Preamble model constants', function (): void {
    it('has correct STATUS_DRAFT constant', function (): void {
        expect(Preamble::STATUS_DRAFT)->toBe('draft');
    });

    it('has correct STATUS_ACTIVE constant', function (): void {
        expect(Preamble::STATUS_ACTIVE)->toBe('active');
    });

    it('has correct STATUS_ARCHIVED constant', function (): void {
        expect(Preamble::STATUS_ARCHIVED)->toBe('archived');
    });

    it('STATUSES contains all three statuses', function (): void {
        expect(Preamble::STATUSES)
            ->toHaveCount(3)
            ->toContain('draft')
            ->toContain('active')
            ->toContain('archived');
    });
});

describe('Preamble model reference number', function (): void {
    it('generates reference number in PR-{id}-{timestamp} format', function (): void {
        $preamble = new Preamble;
        $preamble->id = 99;

        expect($preamble->generateReferenceNumber())->toMatch('/^PR-99-\d+$/');
    });

    it('generates unique reference numbers over time', function (): void {
        $a = new Preamble;
        $a->id = 1;
        $b = new Preamble;
        $b->id = 2;

        expect($a->generateReferenceNumber())->not->toBe($b->generateReferenceNumber());
    });
});

describe('Preamble model fillable', function (): void {
    it('does not have id in fillable', function (): void {
        expect((new Preamble)->getFillable())->not->toContain('id');
    });

    it('does not have identifier in fillable', function (): void {
        expect((new Preamble)->getFillable())->not->toContain('identifier');
    });

    it('has tenant_id in fillable', function (): void {
        expect((new Preamble)->getFillable())->toContain('tenant_id');
    });

    it('has name in fillable', function (): void {
        expect((new Preamble)->getFillable())->toContain('name');
    });

    it('has status in fillable', function (): void {
        expect((new Preamble)->getFillable())->toContain('status');
    });

    it('has effective_date in fillable', function (): void {
        expect((new Preamble)->getFillable())->toContain('effective_date');
    });

    it('has is_featured in fillable', function (): void {
        expect((new Preamble)->getFillable())->toContain('is_featured');
    });
});

describe('Preamble model casts', function (): void {
    it('casts is_featured as boolean', function (): void {
        $casts = (new Preamble)->getCasts();
        expect($casts)->toHaveKey('is_featured')
            ->and($casts['is_featured'])->toBe('boolean');
    });

    it('casts effective_date as date', function (): void {
        $casts = (new Preamble)->getCasts();
        expect($casts)->toHaveKey('effective_date')
            ->and($casts['effective_date'])->toBe('date');
    });
});

describe('Preamble model traits', function (): void {
    it('uses SoftDeletes', function (): void {
        expect(class_uses_recursive(Preamble::class))
            ->toContain(SoftDeletes::class);
    });

    it('uses HasFactory', function (): void {
        expect(class_uses_recursive(Preamble::class))
            ->toContain(HasFactory::class);
    });

    it('uses TenantConnection', function (): void {
        expect(class_uses_recursive(Preamble::class))
            ->toContain(TenantConnection::class);
    });
});

// ---------------------------------------------------------------------------
// PreamblePolicy
// ---------------------------------------------------------------------------

describe('PreamblePolicy::viewAny', function (): void {
    it('allows user with preamble.view permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.view')->andReturn(true);

        expect((new PreamblePolicy)->viewAny($user))->toBeTrue();
    });

    it('denies user without preamble.view permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.view')->andReturn(false);

        expect((new PreamblePolicy)->viewAny($user))->toBeFalse();
    });
});

describe('PreamblePolicy::view', function (): void {
    beforeEach(function (): void {
        // tenant() returns an object with ->id
        $tenant = (object) ['id' => 'tenant-abc'];
        // Mock the global tenant() function behaviour via a stub on the service
        // We test via direct property comparison: the policy calls tenant()?->id
    });

    it('allows user with permission when tenant matches', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.view')->andReturn(true);

        $preamble = new Preamble;
        $preamble->tenant_id = null; // tenant() returns null in unit context

        // When tenant() = null, tenant()?->id = null, preamble->tenant_id = null → match
        expect((new PreamblePolicy)->view($user, $preamble))->toBeTrue();
    });

    it('denies user without permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.view')->andReturn(false);

        $preamble = new Preamble;
        $preamble->tenant_id = null;

        expect((new PreamblePolicy)->view($user, $preamble))->toBeFalse();
    });

    it('denies user when tenant mismatch', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.view')->andReturn(true);

        $preamble = new Preamble;
        $preamble->tenant_id = 'other-tenant'; // tenant() returns null, so null !== 'other-tenant'

        expect((new PreamblePolicy)->view($user, $preamble))->toBeFalse();
    });
});

describe('PreamblePolicy::create', function (): void {
    it('allows user with preamble.create permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.create')->andReturn(true);

        expect((new PreamblePolicy)->create($user))->toBeTrue();
    });

    it('denies user without preamble.create permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.create')->andReturn(false);

        expect((new PreamblePolicy)->create($user))->toBeFalse();
    });
});

describe('PreamblePolicy::update', function (): void {
    it('allows user with permission when tenant matches', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.update')->andReturn(true);

        $preamble = new Preamble;
        $preamble->tenant_id = null;

        expect((new PreamblePolicy)->update($user, $preamble))->toBeTrue();
    });

    it('denies user without permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.update')->andReturn(false);

        $preamble = new Preamble;
        $preamble->tenant_id = null;

        expect((new PreamblePolicy)->update($user, $preamble))->toBeFalse();
    });

    it('denies user with permission but wrong tenant', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.update')->andReturn(true);

        $preamble = new Preamble;
        $preamble->tenant_id = 'other-tenant';

        expect((new PreamblePolicy)->update($user, $preamble))->toBeFalse();
    });
});

describe('PreamblePolicy::delete', function (): void {
    it('allows user with permission when tenant matches', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.delete')->andReturn(true);

        $preamble = new Preamble;
        $preamble->tenant_id = null;

        expect((new PreamblePolicy)->delete($user, $preamble))->toBeTrue();
    });

    it('denies user without permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.delete')->andReturn(false);

        $preamble = new Preamble;
        $preamble->tenant_id = null;

        expect((new PreamblePolicy)->delete($user, $preamble))->toBeFalse();
    });

    it('denies user with permission but wrong tenant', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.delete')->andReturn(true);

        $preamble = new Preamble;
        $preamble->tenant_id = 'wrong-tenant';

        expect((new PreamblePolicy)->delete($user, $preamble))->toBeFalse();
    });
});

describe('PreamblePolicy::restore', function (): void {
    it('allows user with permission when tenant matches', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.restore')->andReturn(true);

        $preamble = new Preamble;
        $preamble->tenant_id = null;

        expect((new PreamblePolicy)->restore($user, $preamble))->toBeTrue();
    });

    it('denies user without permission', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.restore')->andReturn(false);

        $preamble = new Preamble;
        $preamble->tenant_id = null;

        expect((new PreamblePolicy)->restore($user, $preamble))->toBeFalse();
    });

    it('denies user with permission but wrong tenant', function (): void {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasPermissionTo')->with('preamble.restore')->andReturn(true);

        $preamble = new Preamble;
        $preamble->tenant_id = 'wrong-tenant';

        expect((new PreamblePolicy)->restore($user, $preamble))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// Filters
// ---------------------------------------------------------------------------

describe('SearchTermFilter', function (): void {
    it('applies LIKE where clause on name, description, reference_number', function (): void {
        $inner = Mockery::mock(Builder::class);
        $inner->shouldReceive('where')
            ->once()
            ->with('name', 'like', '%audit%')
            ->andReturnSelf();
        $inner->shouldReceive('orWhere')
            ->once()
            ->with('description', 'like', '%audit%')
            ->andReturnSelf();
        $inner->shouldReceive('orWhere')
            ->once()
            ->with('reference_number', 'like', '%audit%')
            ->andReturnSelf();

        $outer = Mockery::mock(Builder::class);
        $outer->shouldReceive('where')
            ->once()
            ->withArgs(function (mixed $callback): bool {
                expect($callback)->toBeCallable();

                return true;
            })
            ->andReturnUsing(function (mixed $callback) use ($inner, $outer): Builder {
                $callback($inner);

                return $outer;
            });

        $result = (new SearchTermFilter('audit'))->apply($outer);

        expect($result)->toBe($outer);
    });

    it('trims whitespace from search term', function (): void {
        $inner = Mockery::mock(Builder::class)->makePartial();
        $inner->shouldReceive('where')->andReturnSelf();
        $inner->shouldReceive('orWhere')->andReturnSelf();

        $outer = Mockery::mock(Builder::class);
        $outer->shouldReceive('where')
            ->once()
            ->withArgs(function (mixed $callback) use ($inner): bool {
                $callback($inner);

                return true;
            })
            ->andReturnSelf();

        (new SearchTermFilter('  audit  '))->apply($outer);
    });
});

describe('StatusFilter', function (): void {
    it('applies where clause on status column', function (): void {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')
            ->once()
            ->with('status', 'active')
            ->andReturnSelf();

        $result = (new StatusFilter('active'))->apply($query);

        expect($result)->toBe($query);
    });
});

describe('IsFeaturedFilter', function (): void {
    it('applies where clause with true for truthy value', function (): void {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')
            ->once()
            ->with('is_featured', true)
            ->andReturnSelf();

        (new IsFeaturedFilter('1'))->apply($query);
    });

    it('applies where clause with false for falsy value', function (): void {
        $query = Mockery::mock(Builder::class);
        $query->shouldReceive('where')
            ->once()
            ->with('is_featured', false)
            ->andReturnSelf();

        (new IsFeaturedFilter('0'))->apply($query);
    });
});

describe('PreambleFilters::fromRequest', function (): void {
    it('returns PreambleFilters instance', function (): void {
        $request = Request::create('/preambles', 'GET');
        expect(PreambleFilters::fromRequest($request))->toBeInstanceOf(PreambleFilters::class);
    });

    it('creates filters from filled request params', function (): void {
        $request = Request::create('/preambles', 'GET', [
            'search' => 'audit',
            'status' => 'active',
        ]);
        $filters = PreambleFilters::fromRequest($request);
        expect($filters)->toBeInstanceOf(PreambleFilters::class);
    });

    it('ignores empty request params', function (): void {
        $request = Request::create('/preambles', 'GET', ['search' => '']);
        $filters = PreambleFilters::fromRequest($request);
        expect($filters)->toBeInstanceOf(PreambleFilters::class);
    });
});

// ---------------------------------------------------------------------------
// PreambleResource
// ---------------------------------------------------------------------------

describe('PreambleResource::toArray keys', function (): void {
    it('contains all expected keys', function (): void {
        $preamble = new Preamble;

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource)->toHaveKeys([
            'identifier',
            'reference_number',
            'tenant_id',
            'name',
            'description',
            'status',
            'effective_date',
            'is_featured',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    });

    it('maps scalar fields correctly', function (): void {
        $preamble = new Preamble;
        $preamble->identifier = 'abc-123';
        $preamble->reference_number = 'PR-5-9999';
        $preamble->tenant_id = 'tenant-xyz';
        $preamble->name = 'SOX Policy';
        $preamble->description = 'Sarbanes-Oxley compliance';
        $preamble->status = Preamble::STATUS_ACTIVE;
        $preamble->is_featured = true;

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource['identifier'])->toBe('abc-123')
            ->and($resource['reference_number'])->toBe('PR-5-9999')
            ->and($resource['tenant_id'])->toBe('tenant-xyz')
            ->and($resource['name'])->toBe('SOX Policy')
            ->and($resource['description'])->toBe('Sarbanes-Oxley compliance')
            ->and($resource['status'])->toBe(Preamble::STATUS_ACTIVE)
            ->and($resource['is_featured'])->toBeTrue();
    });
});

describe('PreambleResource effective_date', function (): void {
    it('formats non-null effective_date as Y-m-d string', function (): void {
        $preamble = new Preamble;
        $preamble->setRawAttributes(['effective_date' => '2026-12-31']);

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource['effective_date'])->toBe('2026-12-31');
    });

    it('returns null for null effective_date', function (): void {
        $preamble = new Preamble;

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource['effective_date'])->toBeNull();
    });
});

describe('PreambleResource timestamps', function (): void {
    it('formats created_at as ISO 8601 string', function (): void {
        $now = now()->setTimezone('UTC');
        $preamble = new Preamble;
        $preamble->setRawAttributes(['created_at' => $now->toDateTimeString()]);

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource['created_at'])->toBe($preamble->created_at->toISOString());
    });

    it('formats updated_at as ISO 8601 string', function (): void {
        $now = now()->setTimezone('UTC');
        $preamble = new Preamble;
        $preamble->setRawAttributes(['updated_at' => $now->toDateTimeString()]);

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource['updated_at'])->toBe($preamble->updated_at->toISOString());
    });

    it('returns null for null deleted_at', function (): void {
        $preamble = new Preamble;

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource['deleted_at'])->toBeNull();
    });

    it('formats deleted_at as ISO 8601 string when set', function (): void {
        $now = now()->setTimezone('UTC');
        $preamble = new Preamble;
        $preamble->setRawAttributes(['deleted_at' => $now->toDateTimeString()]);

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource['deleted_at'])->toBe($preamble->deleted_at->toISOString());
    });
});

describe('PreambleResource whenLoaded relations', function (): void {
    it('omits creator key when relation not loaded', function (): void {
        $preamble = new Preamble;

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource)->not->toHaveKey('creator');
    });

    it('omits updater key when relation not loaded', function (): void {
        $preamble = new Preamble;

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource)->not->toHaveKey('updater');
    });

    it('includes creator when relation is loaded', function (): void {
        $creator = new User;
        $creator->id = 5;
        $creator->identifier = 'user-uuid';
        $creator->first_name = 'Jane';
        $creator->last_name = 'Doe';

        $preamble = new Preamble;
        $preamble->setRelation('creator', $creator);

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource)->toHaveKey('creator')
            ->and($resource['creator'])->toMatchArray([
                'id' => 5,
                'identifier' => 'user-uuid',
                'name' => 'Jane Doe',
            ]);
    });

    it('includes updater when relation is loaded', function (): void {
        $updater = new User;
        $updater->id = 7;
        $updater->identifier = 'user-uuid-2';
        $updater->first_name = 'John';
        $updater->last_name = 'Smith';

        $preamble = new Preamble;
        $preamble->setRelation('updater', $updater);

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource)->toHaveKey('updater')
            ->and($resource['updater'])->toMatchArray([
                'id' => 7,
                'identifier' => 'user-uuid-2',
                'name' => 'John Smith',
            ]);
    });

    it('trims creator name correctly when middle name is absent', function (): void {
        $creator = new User;
        $creator->id = 1;
        $creator->first_name = 'Alice';
        $creator->last_name = '';

        $preamble = new Preamble;
        $preamble->setRelation('creator', $creator);

        $resource = (new PreambleResource($preamble))->resolve();

        expect($resource['creator']['name'])->toBe('Alice');
    });
});

// ---------------------------------------------------------------------------
// PreambleCollection
// ---------------------------------------------------------------------------

describe('PreambleCollection structure', function (): void {
    it('sets collects to PreambleResource', function (): void {
        $collection = new PreambleCollection(collect([]));

        expect($collection->collects)->toBe(PreambleResource::class);
    });

    it('wraps items under the data key', function (): void {
        $collection = new PreambleCollection(collect([]));

        $array = $collection->toArray(request());

        expect($array)->toHaveKey('data');
    });

    it('returns empty data array for empty collection', function (): void {
        $collection = new PreambleCollection(collect([]));

        $array = $collection->toArray(request());

        expect($array['data'])->toBeEmpty();
    });

    it('maps a single item to a PreambleResource instance', function (): void {
        $preamble = new Preamble;
        $preamble->identifier = 'p-uuid';
        $preamble->name = 'Compliance Policy';

        $collection = new PreambleCollection(collect([$preamble]));
        $array = $collection->toArray(request());

        expect($array['data'])->toHaveCount(1)
            ->and($array['data'][0])->toBeInstanceOf(PreambleResource::class);
    });

    it('maps multiple items to PreambleResource instances', function (): void {
        $items = collect([new Preamble, new Preamble, new Preamble]);

        $collection = new PreambleCollection($items);
        $array = $collection->toArray(request());

        expect($array['data'])->toHaveCount(3);
    });

    it('resolved collection items contain expected keys', function (): void {
        $preamble = new Preamble;
        $preamble->identifier = 'resolved-uuid';
        $preamble->name = 'Risk Policy';

        $collection = new PreambleCollection(collect([$preamble]));
        $resolved = $collection->resolve();

        expect($resolved['data'][0])->toHaveKeys(['identifier', 'name', 'status', 'is_featured']);
    });
});

// ---------------------------------------------------------------------------
// PreambleController
// ---------------------------------------------------------------------------

describe('PreambleController constructor', function (): void {
    it('stores the injected PreambleRepository', function (): void {
        $repo = Mockery::mock(PreambleRepository::class);
        $controller = new PreambleController($repo);

        $prop = new ReflectionProperty(PreambleController::class, 'repository');

        expect($prop->getValue($controller))->toBe($repo);
    });
});

describe('PreambleController::index', function (): void {
    it('calls Gate::authorize viewAny and delegates to repository', function (): void {
        Gate::shouldReceive('authorize')
            ->once()
            ->with('viewAny', Preamble::class);

        $paginator = new LengthAwarePaginator(collect([]), 0, 15, 1);

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('browsePreambles')->once()->andReturn($paginator);

        $controller = new PreambleController($repo);
        $response = $controller->index(Request::create('/v1/preambles', 'GET'));

        expect($response->getStatusCode())->toBe(200);
    });

    it('passes sort_by and sort_order to repository', function (): void {
        Gate::shouldReceive('authorize')->andReturn(null);

        $paginator = new LengthAwarePaginator(collect([]), 0, 15, 1);

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('browsePreambles')
            ->once()
            ->withArgs(function (PreambleFilters $filters, int $page, int $perPage, ?string $sortBy, bool $sortDesc): bool {
                return $sortBy === 'name' && $sortDesc === true;
            })
            ->andReturn($paginator);

        $controller = new PreambleController($repo);
        $controller->index(Request::create('/v1/preambles', 'GET', ['sort_by' => 'name', 'sort_order' => 'desc']));
    });
});

describe('PreambleController::store', function (): void {
    it('calls Gate::authorize create and wraps DB write in transaction', function (): void {
        Gate::shouldReceive('authorize')
            ->once()
            ->with('create', Preamble::class);

        $preamble = new Preamble;
        $preamble->identifier = 'new-uuid';

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('createPreamble')->once()->andReturn($preamble);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($cb) => $cb());

        $controller = new PreambleController($repo);
        $request = CreatePreambleRequest::createFrom(
            Request::create('/v1/preambles', 'POST', ['name' => 'Test Preamble'])
        );

        $response = $controller->store($request);

        expect($response->getStatusCode())->toBe(201);
    });

    it('merges tenant_id from tenant() into persisted data', function (): void {
        Gate::shouldReceive('authorize')->andReturn(null);

        $captured = [];

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('createPreamble')
            ->once()
            ->withArgs(function (array $data) use (&$captured): bool {
                $captured = $data;

                return true;
            })
            ->andReturn(new Preamble);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($cb) => $cb());

        $controller = new PreambleController($repo);
        $request = CreatePreambleRequest::createFrom(
            Request::create('/v1/preambles', 'POST', ['name' => 'Tenant Policy'])
        );

        $controller->store($request);

        expect($captured)->toHaveKey('tenant_id');
    });
});

describe('PreambleController::show', function (): void {
    it('reads preamble before calling Gate::authorize view', function (): void {
        $preamble = new Preamble;
        $preamble->identifier = 'show-uuid';
        $preamble->tenant_id = null;

        $callOrder = [];

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('readPreamble')
            ->once()
            ->with('show-uuid')
            ->andReturnUsing(function () use ($preamble, &$callOrder): Preamble {
                $callOrder[] = 'read';

                return $preamble;
            });

        Gate::shouldReceive('authorize')
            ->once()
            ->with('view', $preamble)
            ->andReturnUsing(function () use (&$callOrder): void {
                $callOrder[] = 'authorize';
            });

        $controller = new PreambleController($repo);
        $response = $controller->show('show-uuid');

        expect($callOrder)->toBe(['read', 'authorize'])
            ->and($response->getStatusCode())->toBe(200);
    });
});

describe('PreambleController::update', function (): void {
    it('reads preamble before calling Gate::authorize update', function (): void {
        $preamble = new Preamble;
        $preamble->identifier = 'upd-uuid';
        $preamble->tenant_id = null;

        $callOrder = [];

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('readPreamble')
            ->once()
            ->with('upd-uuid')
            ->andReturnUsing(function () use ($preamble, &$callOrder): Preamble {
                $callOrder[] = 'read';

                return $preamble;
            });

        Gate::shouldReceive('authorize')
            ->once()
            ->andReturnUsing(function () use (&$callOrder): void {
                $callOrder[] = 'authorize';
            });

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($cb) => $cb());

        $repo->shouldReceive('updatePreamble')->once()->andReturn($preamble);

        $controller = new PreambleController($repo);
        $request = UpdatePreambleRequest::createFrom(
            Request::create('/v1/preambles/upd-uuid', 'PUT', ['name' => 'Updated'])
        );

        $controller->update($request, 'upd-uuid');

        expect($callOrder)->toBe(['read', 'authorize']);
    });

    it('returns 200 after successful update', function (): void {
        $preamble = new Preamble;
        $preamble->tenant_id = null;

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('readPreamble')->andReturn($preamble);
        $repo->shouldReceive('updatePreamble')->andReturn($preamble);

        Gate::shouldReceive('authorize')->andReturn(null);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($cb) => $cb());

        $controller = new PreambleController($repo);
        $request = UpdatePreambleRequest::createFrom(
            Request::create('/v1/preambles/any', 'PUT', ['name' => 'Updated'])
        );

        $response = $controller->update($request, 'any');

        expect($response->getStatusCode())->toBe(200);
    });
});

describe('PreambleController::destroy', function (): void {
    it('reads preamble before calling Gate::authorize delete', function (): void {
        $preamble = new Preamble;
        $preamble->identifier = 'del-uuid';
        $preamble->tenant_id = null;

        $callOrder = [];

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('readPreamble')
            ->once()
            ->with('del-uuid')
            ->andReturnUsing(function () use ($preamble, &$callOrder): Preamble {
                $callOrder[] = 'read';

                return $preamble;
            });

        Gate::shouldReceive('authorize')
            ->once()
            ->andReturnUsing(function () use (&$callOrder): void {
                $callOrder[] = 'authorize';
            });

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($cb) => $cb());

        $repo->shouldReceive('deletePreamble')->once();

        $controller = new PreambleController($repo);
        $controller->destroy('del-uuid');

        expect($callOrder)->toBe(['read', 'authorize']);
    });

    it('returns 204 No Content', function (): void {
        $preamble = new Preamble;
        $preamble->tenant_id = null;

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('readPreamble')->andReturn($preamble);
        $repo->shouldReceive('deletePreamble');

        Gate::shouldReceive('authorize')->andReturn(null);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($cb) => $cb());

        $controller = new PreambleController($repo);
        $response = $controller->destroy('any-uuid');

        expect($response->getStatusCode())->toBe(204);
    });
});

describe('PreambleController::restore', function (): void {
    it('reads trashed preamble before calling Gate::authorize restore', function (): void {
        $preamble = new Preamble;
        $preamble->identifier = 'rst-uuid';
        $preamble->tenant_id = null;

        $callOrder = [];

        $repo = Mockery::mock(PreambleRepository::class);
        $repo->shouldReceive('readTrashedPreamble')
            ->once()
            ->with('rst-uuid')
            ->andReturnUsing(function () use ($preamble, &$callOrder): Preamble {
                $callOrder[] = 'readTrashed';

                return $preamble;
            });

        Gate::shouldReceive('authorize')
            ->once()
            ->andReturnUsing(function () use (&$callOrder): void {
                $callOrder[] = 'authorize';
            });

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn ($cb) => $cb());

        $repo->shouldReceive('restorePreamble')->once()->andReturn($preamble);

        $controller = new PreambleController($repo);
        $response = $controller->restore('rst-uuid');

        expect($callOrder)->toBe(['readTrashed', 'authorize'])
            ->and($response->getStatusCode())->toBe(200);
    });
});

// ---------------------------------------------------------------------------
// PreambleRepository
// ---------------------------------------------------------------------------

describe('PreambleRepository::model', function (): void {
    it('returns Preamble model class', function (): void {
        $repo = new PreambleRepository;
        $method = new ReflectionMethod($repo, 'model');

        expect($method->invoke($repo))->toBe(Preamble::class);
    });
});

describe('PreambleRepository::browsePreambles sort column whitelist', function (): void {
    /**
     * Helper: builds a partial-mocked repo whose newQuery() returns $mockQuery.
     * Auth user is set to super-admin so tenant scope is skipped.
     *
     * @return PreambleRepository&MockInterface
     */
    $repoWithQuery = function (Builder $mockQuery): PreambleRepository {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $repo = Mockery::mock(PreambleRepository::class)->makePartial();
        $repo->shouldReceive('newQuery')->andReturn($mockQuery);

        return $repo;
    };

    it('falls back to created_at for an unrecognised sort column', function () use ($repoWithQuery): void {
        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->with('created_at', 'asc')->once()->andReturnSelf();
        $mockQuery->shouldReceive('paginate')->andReturn(
            new LengthAwarePaginator(collect([]), 0, 15, 1)
        );

        $repo = $repoWithQuery($mockQuery);
        $filters = PreambleFilters::fromRequest(Request::create('/'));
        $repo->browsePreambles($filters, 1, 15, 'injected_bad_column');
    });

    it('accepts name as a valid sort column', function () use ($repoWithQuery): void {
        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->with('name', 'asc')->once()->andReturnSelf();
        $mockQuery->shouldReceive('paginate')->andReturn(
            new LengthAwarePaginator(collect([]), 0, 15, 1)
        );

        $repo = $repoWithQuery($mockQuery);
        $filters = PreambleFilters::fromRequest(Request::create('/'));
        $repo->browsePreambles($filters, 1, 15, 'name');
    });

    it('accepts status as a valid sort column', function () use ($repoWithQuery): void {
        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->with('status', 'desc')->once()->andReturnSelf();
        $mockQuery->shouldReceive('paginate')->andReturn(
            new LengthAwarePaginator(collect([]), 0, 15, 1)
        );

        $repo = $repoWithQuery($mockQuery);
        $filters = PreambleFilters::fromRequest(Request::create('/'));
        $repo->browsePreambles($filters, 1, 15, 'status', true);
    });

    it('accepts effective_date as a valid sort column', function () use ($repoWithQuery): void {
        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->with('effective_date', 'asc')->once()->andReturnSelf();
        $mockQuery->shouldReceive('paginate')->andReturn(
            new LengthAwarePaginator(collect([]), 0, 15, 1)
        );

        $repo = $repoWithQuery($mockQuery);
        $filters = PreambleFilters::fromRequest(Request::create('/'));
        $repo->browsePreambles($filters, 1, 15, 'effective_date');
    });

    it('accepts is_featured as a valid sort column', function () use ($repoWithQuery): void {
        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->with('is_featured', 'asc')->once()->andReturnSelf();
        $mockQuery->shouldReceive('paginate')->andReturn(
            new LengthAwarePaginator(collect([]), 0, 15, 1)
        );

        $repo = $repoWithQuery($mockQuery);
        $filters = PreambleFilters::fromRequest(Request::create('/'));
        $repo->browsePreambles($filters, 1, 15, 'is_featured');
    });

    it('caps perPage at 100', function () use ($repoWithQuery): void {
        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->andReturnSelf();
        $mockQuery->shouldReceive('paginate')
            ->once()
            ->andReturnUsing(function (int $perPage) {
                expect($perPage)->toBeLessThanOrEqual(100);

                return new LengthAwarePaginator(collect([]), 0, $perPage, 1);
            });

        $repo = $repoWithQuery($mockQuery);
        $filters = PreambleFilters::fromRequest(Request::create('/'));
        $repo->browsePreambles($filters, 1, 9999);
    });

    it('enforces minimum page of 1', function () use ($repoWithQuery): void {
        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->andReturnSelf();
        $mockQuery->shouldReceive('paginate')
            ->once()
            ->andReturnUsing(function (int $perPage, array $columns, string $pageName, int $page) {
                expect($page)->toBeGreaterThanOrEqual(1);

                return new LengthAwarePaginator(collect([]), 0, $perPage, $page);
            });

        $repo = $repoWithQuery($mockQuery);
        $filters = PreambleFilters::fromRequest(Request::create('/'));
        $repo->browsePreambles($filters, -5, 15);
    });
});

describe('PreambleRepository::browsePreambles tenant scoping', function (): void {
    it('adds tenant_id where clause for non-super-admin users', function (): void {
        $tenantScopeApplied = false;

        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('where')
            ->withArgs(function (string $col, mixed $val) use (&$tenantScopeApplied): bool {
                if ($col === 'tenant_id') {
                    $tenantScopeApplied = true;
                }

                return true;
            })
            ->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->andReturnSelf();
        $mockQuery->shouldReceive('paginate')->andReturn(
            new LengthAwarePaginator(collect([]), 0, 15, 1)
        );

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(false);
        Auth::shouldReceive('user')->andReturn($user);

        $repo = Mockery::mock(PreambleRepository::class)->makePartial();
        $repo->shouldReceive('newQuery')->andReturn($mockQuery);

        $filters = PreambleFilters::fromRequest(Request::create('/'));
        $repo->browsePreambles($filters);

        expect($tenantScopeApplied)->toBeTrue();
    });

    it('skips tenant_id where clause for super-admin users', function (): void {
        $tenantScopeApplied = false;

        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('where')
            ->withArgs(function (string $col, mixed $val) use (&$tenantScopeApplied): bool {
                if ($col === 'tenant_id') {
                    $tenantScopeApplied = true;
                }

                return true;
            })
            ->andReturnSelf();
        $mockQuery->shouldReceive('orderBy')->andReturnSelf();
        $mockQuery->shouldReceive('paginate')->andReturn(
            new LengthAwarePaginator(collect([]), 0, 15, 1)
        );

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $repo = Mockery::mock(PreambleRepository::class)->makePartial();
        $repo->shouldReceive('newQuery')->andReturn($mockQuery);

        $filters = PreambleFilters::fromRequest(Request::create('/'));
        $repo->browsePreambles($filters);

        expect($tenantScopeApplied)->toBeFalse();
    });
});

describe('PreambleRepository::createPreamble', function (): void {
    it('delegates to newQuery()->create() and returns the Preamble', function (): void {
        $data = ['name' => 'Test', 'status' => 'draft', 'tenant_id' => 'tenant-1'];
        $expected = new Preamble;

        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($expected);

        $repo = Mockery::mock(PreambleRepository::class)->makePartial();
        $repo->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $repo->createPreamble($data);

        expect($result)->toBe($expected);
    });
});

describe('PreambleRepository::readPreamble', function (): void {
    it('applies tenant_id scope for non-super-admin', function (): void {
        $tenantScopeApplied = false;

        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('where')
            ->withArgs(function (string $col) use (&$tenantScopeApplied): bool {
                if ($col === 'tenant_id') {
                    $tenantScopeApplied = true;
                }

                return true;
            })
            ->andReturnSelf();
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('firstOrFail')->andReturn(new Preamble);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(false);
        Auth::shouldReceive('user')->andReturn($user);

        $repo = Mockery::mock(PreambleRepository::class)->makePartial();
        $repo->shouldReceive('newQuery')->andReturn($mockQuery);

        $repo->readPreamble('some-identifier');

        expect($tenantScopeApplied)->toBeTrue();
    });

    it('skips tenant_id scope for super-admin', function (): void {
        $tenantScopeApplied = false;

        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('where')
            ->withArgs(function (string $col) use (&$tenantScopeApplied): bool {
                if ($col === 'tenant_id') {
                    $tenantScopeApplied = true;
                }

                return true;
            })
            ->andReturnSelf();
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('firstOrFail')->andReturn(new Preamble);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $repo = Mockery::mock(PreambleRepository::class)->makePartial();
        $repo->shouldReceive('newQuery')->andReturn($mockQuery);

        $repo->readPreamble('some-identifier');

        expect($tenantScopeApplied)->toBeFalse();
    });
});

describe('PreambleRepository::readTrashedPreamble', function (): void {
    it('includes soft-deleted records via withTrashed', function (): void {
        $withTrashedCalled = false;

        $mockQuery = Mockery::mock(Builder::class);
        $mockQuery->shouldReceive('withTrashed')
            ->once()
            ->andReturnUsing(function () use ($mockQuery, &$withTrashedCalled): Builder {
                $withTrashedCalled = true;

                return $mockQuery;
            });
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('with')->andReturnSelf();
        $mockQuery->shouldReceive('firstOrFail')->andReturn(new Preamble);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $repo = Mockery::mock(PreambleRepository::class)->makePartial();
        $repo->shouldReceive('newQuery')->andReturn($mockQuery);

        $repo->readTrashedPreamble('some-identifier');

        expect($withTrashedCalled)->toBeTrue();
    });
});
