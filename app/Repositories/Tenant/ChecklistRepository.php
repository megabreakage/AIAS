<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Filters\Tenant\Checklists\ChecklistFilters;
use App\Models\Tenant\Checklist;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ChecklistRepository extends BaseRepository
{
    protected function model(): string
    {
        return Checklist::class;
    }

    /**
     * Browse checklists with filters, sorting, and pagination.
     * Non-super-admin users are scoped to the current tenant.
     */
    public function browseChecklists(
        ChecklistFilters $filters,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = null,
        bool $sortDesc = false,
    ): LengthAwarePaginator {
        $query = $this->newQuery()->with(['creator', 'updater', 'preamble', 'checklistType', 'qualityController']);

        if (!auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        $filters->apply($query);

        $sortColumn = in_array($sortBy, ['name', 'reference_number', 'is_active', 'is_featured', 'created_at'], true)
            ? $sortBy
            : 'created_at';

        $query->orderBy($sortColumn, $sortDesc ? 'desc' : 'asc');

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1),
        );
    }

    /**
     * Find a checklist by identifier (active records only).
     *
     * @throws ModelNotFoundException
     */
    public function readChecklist(string $identifier): Checklist
    {
        $query = $this->newQuery()
            ->where('identifier', $identifier)
            ->with(['creator', 'updater', 'preamble', 'checklistType', 'qualityController']);

        if (!auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        /** @var Checklist */
        return $query->firstOrFail();
    }

    /**
     * Find a soft-deleted checklist by identifier (includes trashed).
     *
     * @throws ModelNotFoundException
     */
    public function readTrashedChecklist(string $identifier): Checklist
    {
        $query = $this->newQuery()
            ->withTrashed()
            ->where('identifier', $identifier)
            ->with(['creator', 'updater', 'preamble', 'checklistType', 'qualityController']);

        if (!auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        /** @var Checklist */
        return $query->firstOrFail();
    }

    /**
     * Create a new checklist.
     *
     * @param  array<string, mixed>  $data
     */
    public function createChecklist(array $data): Checklist
    {
        /** @var Checklist */
        $checklist = $this->newQuery()->create($data);

        return $checklist->fresh(['creator', 'updater', 'preamble', 'checklistType', 'qualityController']);
    }

    /**
     * Update an existing checklist.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateChecklist(string $identifier, array $data): Checklist
    {
        $checklist = $this->readChecklist($identifier);
        $checklist->fill($data)->save();

        return $checklist->fresh(['creator', 'updater', 'preamble', 'checklistType', 'qualityController']);
    }

    /**
     * Soft-delete a checklist.
     */
    public function deleteChecklist(string $identifier): void
    {
        $checklist = $this->readChecklist($identifier);
        $checklist->delete();
    }

    /**
     * Restore a soft-deleted checklist.
     */
    public function restoreChecklist(string $identifier): Checklist
    {
        $checklist = $this->readTrashedChecklist($identifier);
        $checklist->restore();

        return $checklist->fresh(['creator', 'updater', 'preamble', 'checklistType', 'qualityController']);
    }
}
