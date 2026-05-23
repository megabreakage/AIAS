<?php

declare(strict_types=1);

namespace App\Repositories\Central;

use App\Filters\Central\Tenant\TenantFilters;
use App\Models\Central\Tenant;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class TenantRepository extends BaseRepository
{
    protected function model(): string
    {
        return Tenant::class;
    }

    /**
     * Browse tenants with filters, sorting, and pagination.
     */
    public function browseTenants(
        TenantFilters $filters,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = null,
        bool $sortDesc = false,
    ): LengthAwarePaginator {
        $query = Tenant::on('central')->with(['domains', 'owner']);

        $filters->apply($query);

        $sortColumn = in_array($sortBy, ['name', 'domain', 'status', 'created_at'], true)
            ? $sortBy
            : 'created_at';

        $query->orderBy($sortColumn, $sortDesc ? 'desc' : 'asc');

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1),
        );
    }

    /**
     * Find a tenant by identifier.
     *
     * @throws ModelNotFoundException
     */
    public function readTenant(string $identifier): Tenant
    {
        /** @var Tenant */
        return Tenant::on('central')
            ->where('identifier', $identifier)
            ->with(['domains', 'owner'])
            ->firstOrFail();
    }

    /**
     * Find a soft-deleted tenant by identifier.
     *
     * @throws ModelNotFoundException
     */
    public function readTrashedTenant(string $identifier): Tenant
    {
        /** @var Tenant */
        return Tenant::on('central')
            ->withTrashed()
            ->where('identifier', $identifier)
            ->with(['domains', 'owner'])
            ->firstOrFail();
    }

    /**
     * Update an existing tenant.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTenant(string $identifier, array $data): Tenant
    {
        $tenant = $this->readTenant($identifier);
        $tenant->fill($data)->save();

        return $tenant->fresh(['domains', 'owner']);
    }

    /**
     * Soft-delete a tenant.
     */
    public function deleteTenant(string $identifier): void
    {
        $tenant = $this->readTenant($identifier);
        $tenant->delete();
    }

    /**
     * Restore a soft-deleted tenant.
     */
    public function restoreTenant(string $identifier): Tenant
    {
        $tenant = $this->readTrashedTenant($identifier);
        $tenant->restore();

        return $tenant->fresh(['domains', 'owner']);
    }
}
