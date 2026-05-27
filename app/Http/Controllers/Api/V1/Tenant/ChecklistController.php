<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Filters\Tenant\Checklists\ChecklistFilters;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Tenant\Checklists\CreateChecklistRequest;
use App\Http\Requests\Tenant\Checklists\UpdateChecklistRequest;
use App\Http\Resources\Tenant\Checklist\ChecklistResource;
use App\Models\Tenant\Checklist;
use App\Repositories\Tenant\ChecklistRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ChecklistController extends BaseApiController
{
    public function __construct(protected ChecklistRepository $repository) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Checklist::class);

        $filters = ChecklistFilters::fromRequest($request);

        $checklists = $this->repository->browseChecklists(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc',
        );

        return $this->paginated($checklists, ChecklistResource::class);
    }

    public function store(CreateChecklistRequest $request): JsonResponse
    {
        Gate::authorize('create', Checklist::class);

        $data = $request->validated();
        $data['tenant_id'] = tenant()?->id;

        Log::info('Creating checklist', ['name' => $data['name'], 'tenant_id' => $data['tenant_id']]);

        $checklist = DB::transaction(function () use ($data): Checklist {
            return $this->repository->createChecklist($data);
        });

        Log::info('Checklist created', ['identifier' => $checklist->identifier]);

        return $this->success(
            ChecklistResource::make($checklist->load(['creator', 'updater', 'preamble', 'checklistType', 'qualityController']))->resolve(),
            Response::HTTP_CREATED,
        );
    }

    public function show(string $identifier): JsonResponse
    {
        $checklist = $this->repository->readChecklist($identifier);

        Gate::authorize('view', $checklist);

        return $this->success(ChecklistResource::make($checklist)->resolve());
    }

    public function update(UpdateChecklistRequest $request, string $identifier): JsonResponse
    {
        $checklist = $this->repository->readChecklist($identifier);

        Gate::authorize('update', $checklist);

        $data = $request->validated();

        Log::info('Updating checklist', ['identifier' => $identifier]);

        $checklist = DB::transaction(function () use ($identifier, $data): Checklist {
            return $this->repository->updateChecklist($identifier, $data);
        });

        Log::info('Checklist updated', ['identifier' => $checklist->identifier]);

        return $this->success(ChecklistResource::make($checklist)->resolve());
    }

    public function destroy(string $identifier): JsonResponse
    {
        $checklist = $this->repository->readChecklist($identifier);

        Gate::authorize('delete', $checklist);

        Log::info('Deleting checklist', ['identifier' => $identifier]);

        DB::transaction(function () use ($identifier): void {
            $this->repository->deleteChecklist($identifier);
        });

        Log::info('Checklist deleted', ['identifier' => $identifier]);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    public function restore(string $identifier): JsonResponse
    {
        $checklist = $this->repository->readTrashedChecklist($identifier);

        Gate::authorize('restore', $checklist);

        Log::info('Restoring checklist', ['identifier' => $identifier]);

        $checklist = DB::transaction(function () use ($identifier): Checklist {
            return $this->repository->restoreChecklist($identifier);
        });

        Log::info('Checklist restored', ['identifier' => $checklist->identifier]);

        return $this->success(ChecklistResource::make($checklist)->resolve());
    }
}
