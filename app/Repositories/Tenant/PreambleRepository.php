<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Filters\Tenant\Preambles\PreambleFilters;
use App\Models\Tenant\Preamble;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PreambleRepository extends BaseRepository
{
    protected function model(): string
    {
        return Preamble::class;
    }

    /**
     * Browse preambles with filters, sorting, and pagination.
     * Non-super-admin users are scoped to the current tenant.
     */
    public function browsePreambles(
        PreambleFilters $filters,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = null,
        bool $sortDesc = false,
    ): LengthAwarePaginator {
        $query = $this->newQuery()->with(['creator', 'updater']);

        if (!auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->id);
        }

        $filters->apply($query);

        $sortColumn = in_array($sortBy, ['name', 'status', 'effective_date', 'is_featured', 'created_at'], true)
            ? $sortBy
            : 'created_at';

        $query->orderBy($sortColumn, $sortDesc ? 'desc' : 'asc');

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1),
        );
    }

    /**
     * Find a preamble by identifier (active records only).
     *
     * @throws ModelNotFoundException
     */
    public function readPreamble(string $identifier): Preamble
    {
        $query = $this->newQuery()
            ->where('identifier', $identifier)
            ->with(['creator', 'updater']);

        if (!auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->id);
        }

        /** @var Preamble */
        return $query->firstOrFail();
    }

    /**
     * Find a soft-deleted preamble by identifier (includes trashed).
     *
     * @throws ModelNotFoundException
     */
    public function readTrashedPreamble(string $identifier): Preamble
    {
        $query = $this->newQuery()
            ->withTrashed()
            ->where('identifier', $identifier)
            ->with(['creator', 'updater']);

        if (!auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->id);
        }

        /** @var Preamble */
        return $query->firstOrFail();
    }

    /**
     * Create a new preamble.
     *
     * @param  array<string, mixed>  $data
     */
    public function createPreamble(array $data): Preamble
    {
        /** @var Preamble */
        return $this->newQuery()->create($data);
    }

    /**
     * Update an existing preamble.
     *
     * @param  array<string, mixed>  $data
     */
    public function updatePreamble(string $identifier, array $data): Preamble
    {
        $preamble = $this->readPreamble($identifier);
        $preamble->fill($data)->save();

        return $preamble->fresh(['creator', 'updater']);
    }

    /**
     * Soft-delete a preamble.
     */
    public function deletePreamble(string $identifier): void
    {
        $preamble = $this->readPreamble($identifier);
        $preamble->delete();
    }

    /**
     * Restore a soft-deleted preamble.
     */
    public function restorePreamble(string $identifier): Preamble
    {
        $preamble = $this->readTrashedPreamble($identifier);
        $preamble->restore();

        return $preamble->fresh(['creator', 'updater']);
    }
}
