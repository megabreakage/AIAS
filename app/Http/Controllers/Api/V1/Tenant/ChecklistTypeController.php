<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Filters\Tenant\ChecklistTypes\ChecklistTypeFilters;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Tenant\ChecklistTypes\CreateChecklistTypeRequest;
use App\Http\Requests\Tenant\ChecklistTypes\UpdateChecklistTypeRequest;
use App\Http\Resources\Tenant\ChecklistType\ChecklistTypeResource;
use App\Models\Tenant\ChecklistType;
use App\Repositories\Tenant\ChecklistTypeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ChecklistTypeController extends BaseApiController
{
    public function __construct(protected ChecklistTypeRepository $repository) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ChecklistType::class);

        $filters = ChecklistTypeFilters::fromRequest($request);

        $checklistTypes = $this->repository->browseChecklistTypes(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc',
        );

        return $this->paginated($checklistTypes, ChecklistTypeResource::class);
    }

    public function store(CreateChecklistTypeRequest $request): JsonResponse
    {
        Gate::authorize('create', ChecklistType::class);

        $data = $request->validated();
        $data['tenant_id'] = tenant()?->id;

        Log::info('Creating checklist type', ['name' => $data['name'], 'tenant_id' => $data['tenant_id']]);

        $checklistType = DB::transaction(function () use ($data): ChecklistType {
            return $this->repository->createChecklistType($data);
        });

        Log::info('Checklist type created', ['identifier' => $checklistType->identifier]);

        return $this->success(
            ChecklistTypeResource::make($checklistType->load(['creator', 'updater']))->resolve(),
            Response::HTTP_CREATED,
        );
    }

    public function show(string $identifier): JsonResponse
    {
        $checklistType = $this->repository->readChecklistType($identifier);

        Gate::authorize('view', $checklistType);

        return $this->success(ChecklistTypeResource::make($checklistType)->resolve());
    }

    public function update(UpdateChecklistTypeRequest $request, string $identifier): JsonResponse
    {
        $checklistType = $this->repository->readChecklistType($identifier);

        Gate::authorize('update', $checklistType);

        $data = $request->validated();

        Log::info('Updating checklist type', ['identifier' => $identifier]);

        $checklistType = DB::transaction(function () use ($identifier, $data): ChecklistType {
            return $this->repository->updateChecklistType($identifier, $data);
        });

        Log::info('Checklist type updated', ['identifier' => $checklistType->identifier]);

        return $this->success(ChecklistTypeResource::make($checklistType)->resolve());
    }

    public function destroy(string $identifier): JsonResponse
    {
        $checklistType = $this->repository->readChecklistType($identifier);

        Gate::authorize('delete', $checklistType);

        Log::info('Deleting checklist type', ['identifier' => $identifier]);

        DB::transaction(function () use ($identifier): void {
            $this->repository->deleteChecklistType($identifier);
        });

        Log::info('Checklist type deleted', ['identifier' => $identifier]);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    public function restore(string $identifier): JsonResponse
    {
        $checklistType = $this->repository->readTrashedChecklistType($identifier);

        Gate::authorize('restore', $checklistType);

        Log::info('Restoring checklist type', ['identifier' => $identifier]);

        $checklistType = DB::transaction(function () use ($identifier): ChecklistType {
            return $this->repository->restoreChecklistType($identifier);
        });

        Log::info('Checklist type restored', ['identifier' => $checklistType->identifier]);

        return $this->success(ChecklistTypeResource::make($checklistType)->resolve());
    }
}
