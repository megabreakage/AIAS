<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Enums\AuditStatusStageStatus;
use App\Filters\Tenant\Audits\AuditFilters;
use App\Models\Tenant\Audit;
use App\Models\Tenant\AuditStatusStage;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

class AuditRepository extends BaseRepository
{
    protected function model(): string
    {
        return Audit::class;
    }

    /**
     * Browse audits with filters, sorting, and pagination.
     * Non-super-admin users are scoped to the current tenant.
     */
    public function browseAudits(
        AuditFilters $filters,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = null,
        bool $sortDesc = false,
    ): LengthAwarePaginator {
        $query = $this->newQuery()->with([
            'creator', 'updater', 'latestStatus',
            'leadAuditor', 'qualityManager', 'department', 'checklist',
        ]);

        if (! auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        $filters->apply($query);

        $sortColumn = in_array($sortBy, ['name', 'reference_number', 'scope', 'audit_start_date', 'audit_end_date', 'is_featured', 'created_at'], true)
            ? $sortBy
            : 'created_at';

        $query->orderBy($sortColumn, $sortDesc ? 'desc' : 'asc');

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1),
        );
    }

    /**
     * Find an audit by identifier (active records only).
     *
     * @throws ModelNotFoundException
     */
    public function readAudit(string $identifier): Audit
    {
        $query = $this->newQuery()
            ->where('identifier', $identifier)
            ->with([
                'creator', 'updater', 'statusStages', 'latestStatus',
                'leadAuditor', 'qualityManager', 'department', 'checklist',
            ]);

        if (! auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        /** @var Audit */
        return $query->firstOrFail();
    }

    /**
     * Find a soft-deleted audit by identifier (includes trashed).
     *
     * @throws ModelNotFoundException
     */
    public function readTrashedAudit(string $identifier): Audit
    {
        $query = $this->newQuery()
            ->withTrashed()
            ->where('identifier', $identifier)
            ->with([
                'creator', 'updater', 'statusStages', 'latestStatus',
                'leadAuditor', 'qualityManager', 'department', 'checklist',
            ]);

        if (! auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        /** @var Audit */
        return $query->firstOrFail();
    }

    /**
     * Create a new audit and seed the initial 'scheduled' status stage.
     *
     * @param  array<string, mixed>  $data
     */
    public function createAudit(array $data): Audit
    {
        /** @var Audit */
        $audit = $this->newQuery()->create($data);

        $this->createStatusStage($audit, AuditStatusStageStatus::Scheduled->value);

        return $audit->fresh([
            'creator', 'updater', 'statusStages', 'latestStatus',
            'leadAuditor', 'qualityManager', 'department', 'checklist',
        ]);
    }

    /**
     * Update an existing audit.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateAudit(string $identifier, array $data): Audit
    {
        $audit = $this->readAudit($identifier);

        $audit->fill($data)->save();

        return $audit->fresh([
            'creator', 'updater', 'statusStages', 'latestStatus',
            'leadAuditor', 'qualityManager', 'department', 'checklist',
        ]);
    }

    /**
     * Soft-delete an audit.
     */
    public function deleteAudit(string $identifier): void
    {
        $audit = $this->readAudit($identifier);
        $audit->delete();
    }

    /**
     * Restore a soft-deleted audit.
     */
    public function restoreAudit(string $identifier): Audit
    {
        $audit = $this->readTrashedAudit($identifier);
        $audit->restore();

        return $audit->fresh([
            'creator', 'updater', 'statusStages', 'latestStatus',
            'leadAuditor', 'qualityManager', 'department', 'checklist',
        ]);
    }

    /**
     * Add a new status stage entry for an audit.
     */
    public function createStatusStage(Audit $audit, string $status, ?string $notes = null): AuditStatusStage
    {
        /** @var AuditStatusStage */
        return $audit->statusStages()->create([
            'status' => $status,
            'changed_at' => now(),
            'changed_by' => Auth::id(),
            'notes' => $notes,
        ]);
    }
}
