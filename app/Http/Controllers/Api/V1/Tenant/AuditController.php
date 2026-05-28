<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Filters\Tenant\Audits\AuditFilters;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Tenant\Audits\CreateAuditRequest;
use App\Http\Requests\Tenant\Audits\UpdateAuditRequest;
use App\Http\Resources\Tenant\Audit\AuditResource;
use App\Models\Tenant\Audit;
use App\Repositories\Tenant\AuditRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class AuditController extends BaseApiController
{
    public function __construct(protected AuditRepository $repository) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Audit::class);

        $filters = AuditFilters::fromRequest($request);

        $audits = $this->repository->browseAudits(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc',
        );

        return $this->paginated($audits, AuditResource::class);
    }

    public function store(CreateAuditRequest $request): JsonResponse
    {
        Gate::authorize('create', Audit::class);

        $data = $request->validated();
        $data['tenant_id'] = tenant()?->id;

        Log::info('Creating audit', ['name' => $data['name'], 'tenant_id' => $data['tenant_id']]);

        $audit = DB::transaction(function () use ($data): Audit {
            return $this->repository->createAudit($data);
        });

        Log::info('Audit created', ['identifier' => $audit->identifier]);

        return $this->success(
            AuditResource::make($audit->load([
                'creator', 'updater', 'statusStages', 'latestStatus',
                'leadAuditor', 'qualityManager', 'department', 'checklist',
            ]))->resolve(),
            Response::HTTP_CREATED,
        );
    }

    public function show(string $identifier): JsonResponse
    {
        $audit = $this->repository->readAudit($identifier);

        Gate::authorize('view', $audit);

        return $this->success(AuditResource::make($audit)->resolve());
    }

    public function update(UpdateAuditRequest $request, string $identifier): JsonResponse
    {
        $audit = $this->repository->readAudit($identifier);

        Gate::authorize('update', $audit);

        $data = $request->validated();

        Log::info('Updating audit', ['identifier' => $identifier]);

        $audit = DB::transaction(function () use ($identifier, $data): Audit {
            return $this->repository->updateAudit($identifier, $data);
        });

        Log::info('Audit updated', ['identifier' => $audit->identifier]);

        return $this->success(AuditResource::make($audit)->resolve());
    }

    public function destroy(string $identifier): JsonResponse
    {
        $audit = $this->repository->readAudit($identifier);

        Gate::authorize('delete', $audit);

        Log::info('Deleting audit', ['identifier' => $identifier]);

        DB::transaction(function () use ($identifier): void {
            $this->repository->deleteAudit($identifier);
        });

        Log::info('Audit deleted', ['identifier' => $identifier]);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    public function restore(string $identifier): JsonResponse
    {
        $audit = $this->repository->readTrashedAudit($identifier);

        Gate::authorize('restore', $audit);

        Log::info('Restoring audit', ['identifier' => $identifier]);

        $audit = DB::transaction(function () use ($identifier): Audit {
            return $this->repository->restoreAudit($identifier);
        });

        Log::info('Audit restored', ['identifier' => $audit->identifier]);

        return $this->success(AuditResource::make($audit)->resolve());
    }
}
