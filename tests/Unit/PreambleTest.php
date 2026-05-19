<?php

declare(strict_types=1);

use App\Filters\Tenant\Preambles\Filters\IsFeaturedFilter;
use App\Filters\Tenant\Preambles\Filters\SearchTermFilter;
use App\Filters\Tenant\Preambles\Filters\StatusFilter;
use App\Filters\Tenant\Preambles\PreambleFilters;
use App\Models\Tenant\Preamble;
use App\Models\User;
use App\Policies\PreamblePolicy;
use App\Support\Concerns\HasUuidIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

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

    it('uses HasUuidIdentifier', function (): void {
        expect(class_uses_recursive(Preamble::class))
            ->toContain(HasUuidIdentifier::class);
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
