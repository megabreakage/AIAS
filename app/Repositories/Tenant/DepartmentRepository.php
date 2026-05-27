<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Filters\Tenant\Departments\DepartmentFilters;
use App\Models\Tenant\Department;
use App\Models\Tenant\DepartmentMember;
use App\Repositories\BaseRepository;
use App\Services\GeocodingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class DepartmentRepository extends BaseRepository
{
    public function __construct(protected GeocodingService $geocodingService) {}

    protected function model(): string
    {
        return Department::class;
    }

    /**
     * Browse departments with filters, sorting, and pagination.
     * Non-super-admin users are scoped to the current tenant.
     */
    public function browseDepartments(
        DepartmentFilters $filters,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = null,
        bool $sortDesc = false,
    ): LengthAwarePaginator {
        $query = $this->newQuery()->with(['creator', 'updater', 'members.memberUser', 'head']);

        if (! auth()->user()?->hasRole('super-admin')) {
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
     * Find a department by identifier (active records only).
     *
     * @throws ModelNotFoundException
     */
    public function readDepartment(string $identifier): Department
    {
        $query = $this->newQuery()
            ->where('identifier', $identifier)
            ->with(['creator', 'updater', 'members.memberUser', 'head']);

        if (! auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        /** @var Department */
        return $query->firstOrFail();
    }

    /**
     * Find a soft-deleted department by identifier (includes trashed).
     *
     * @throws ModelNotFoundException
     */
    public function readTrashedDepartment(string $identifier): Department
    {
        $query = $this->newQuery()
            ->withTrashed()
            ->where('identifier', $identifier)
            ->with(['creator', 'updater', 'members.memberUser', 'head']);

        if (! auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        /** @var Department */
        return $query->firstOrFail();
    }

    /**
     * Create a new department, geocoding the office_location if provided.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDepartment(array $data): Department
    {
        $members = (array) ($data['department_members'] ?? []);
        unset($data['department_members']);

        $data = $this->applyGeocoding($data);

        /** @var Department */
        $department = $this->newQuery()->create($data);

        if (! empty($members)) {
            $this->syncMembers($department, $members);
        }

        return $department->fresh(['creator', 'updater', 'members.memberUser', 'head']);
    }

    /**
     * Update an existing department.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDepartment(string $identifier, array $data): Department
    {
        $department = $this->readDepartment($identifier);

        $members = array_key_exists('department_members', $data) ? (array) $data['department_members'] : null;
        unset($data['department_members']);

        if (isset($data['office_location']) && $data['office_location'] !== $department->office_location) {
            $data = $this->applyGeocoding($data);
        }

        $department->fill($data)->save();

        if ($members !== null) {
            $this->syncMembers($department, $members);
        }

        return $department->fresh(['creator', 'updater', 'members.memberUser', 'head']);
    }

    /**
     * Soft-delete a department.
     */
    public function deleteDepartment(string $identifier): void
    {
        $department = $this->readDepartment($identifier);
        $department->delete();
    }

    /**
     * Restore a soft-deleted department.
     */
    public function restoreDepartment(string $identifier): Department
    {
        $department = $this->readTrashedDepartment($identifier);
        $department->restore();

        return $department->fresh(['creator', 'updater', 'members.memberUser', 'head']);
    }

    /**
     * Apply geocoding data if office_location is present in data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyGeocoding(array $data): array
    {
        if (! empty($data['office_location'])) {
            $geo = $this->geocodingService->geocode((string) $data['office_location']);

            if ($geo['latitude'] !== null) {
                $data['latitude'] = $data['latitude'] ?? $geo['latitude'];
            }

            if ($geo['longitude'] !== null) {
                $data['longitude'] = $data['longitude'] ?? $geo['longitude'];
            }

            if ($geo['country_id'] !== null) {
                $data['country_id'] = $data['country_id'] ?? $geo['country_id'];
            }
        }

        return $data;
    }

    /**
     * Sync department members — replaces existing members with provided list.
     *
     * @param  array<int, array<string, mixed>>  $members
     */
    private function syncMembers(Department $department, array $members): void
    {
        $department->members()->delete();

        foreach ($members as $member) {
            $department->members()->create([
                'user_id' => $member['user_id'] ?? null,
            ]);
        }
    }
}
