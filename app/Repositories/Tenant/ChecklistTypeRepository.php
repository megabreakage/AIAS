<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Filters\Tenant\ChecklistTypes\ChecklistTypeFilters;
use App\Models\Tenant\ChecklistType;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ChecklistTypeRepository extends BaseRepository
{
    protected function model(): string
    {
        return ChecklistType::class;
    }

    /**
     * Browse checklist types with filters, sorting, and pagination.
     * Non-super-admin users are scoped to the current tenant.
     */
    public function browseChecklistTypes(
        ChecklistTypeFilters $filters,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = null,
        bool $sortDesc = false,
    ): LengthAwarePaginator {
        $query = $this->newQuery()->with(['creator', 'updater']);

        if (!auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        $filters->apply($query);

        $sortColumn = in_array($sortBy, ['name', 'is_active', 'is_featured', 'created_at'], true)
            ? $sortBy
            : 'created_at';

        $query->orderBy($sortColumn, $sortDesc ? 'desc' : 'asc');

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1),
        );
    }

    /**
     * Find a checklist type by identifier (active records only).
     *
     * @throws ModelNotFoundException
     */
    public function readChecklistType(string $identifier): ChecklistType
    {
        $query = $this->newQuery()
            ->where('identifier', $identifier)
            ->with(['creator', 'updater']);

        if (!auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        /** @var ChecklistType */
        return $query->firstOrFail();
    }

    /**
     * Find a soft-deleted checklist type by identifier (includes trashed).
     *
     * @throws ModelNotFoundException
     */
    public function readTrashedChecklistType(string $identifier): ChecklistType
    {
        $query = $this->newQuery()
            ->withTrashed()
            ->where('identifier', $identifier)
            ->with(['creator', 'updater']);

        if (!auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        /** @var ChecklistType */
        return $query->firstOrFail();
    }

    /**
     * Create a new checklist type.
     *
     * @param  array<string, mixed>  $data
     */
    public function createChecklistType(array $data): ChecklistType
    {
        /** @var ChecklistType */
        $checklistType = $this->newQuery()->create($data);

        return $checklistType->fresh(['creator', 'updater']);
    }

    /**
     * Update an existing checklist type.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateChecklistType(string $identifier, array $data): ChecklistType
    {
        $checklistType = $this->readChecklistType($identifier);
        $checklistType->fill($data)->save();

        return $checklistType->fresh(['creator', 'updater']);
    }

    /**
     * Soft-delete a checklist type.
     */
    public function deleteChecklistType(string $identifier): void
    {
        $checklistType = $this->readChecklistType($identifier);
        $checklistType->delete();
    }

    /**
     * Restore a soft-deleted checklist type.
     */
    public function restoreChecklistType(string $identifier): ChecklistType
    {
        $checklistType = $this->readTrashedChecklistType($identifier);
        $checklistType->restore();

        return $checklistType->fresh(['creator', 'updater']);
    }
}
