<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Filters\Tenant\Departments\DepartmentFilters;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Tenant\Departments\CreateDepartmentRequest;
use App\Http\Requests\Tenant\Departments\UpdateDepartmentRequest;
use App\Http\Resources\Tenant\Department\DepartmentResource;
use App\Models\Tenant\Department;
use App\Repositories\Tenant\DepartmentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class DepartmentController extends BaseApiController
{
    public function __construct(protected DepartmentRepository $repository) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Department::class);

        $filters = DepartmentFilters::fromRequest($request);

        $departments = $this->repository->browseDepartments(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc',
        );

        return $this->paginated($departments, DepartmentResource::class);
    }

    public function store(CreateDepartmentRequest $request): JsonResponse
    {
        Gate::authorize('create', Department::class);

        $data = $request->validated();
        $data['tenant_id'] = tenant()?->id;

        Log::info('Creating department', ['name' => $data['name'], 'tenant_id' => $data['tenant_id']]);

        $department = DB::transaction(function () use ($data): Department {
            return $this->repository->createDepartment($data);
        });

        Log::info('Department created', ['identifier' => $department->identifier]);

        return $this->success(
            DepartmentResource::make($department->load(['creator', 'updater', 'members.memberUser', 'head']))->resolve(),
            Response::HTTP_CREATED,
        );
    }

    public function show(string $identifier): JsonResponse
    {
        $department = $this->repository->readDepartment($identifier);

        Gate::authorize('view', $department);

        return $this->success(DepartmentResource::make($department)->resolve());
    }

    public function update(UpdateDepartmentRequest $request, string $identifier): JsonResponse
    {
        $department = $this->repository->readDepartment($identifier);

        Gate::authorize('update', $department);

        $data = $request->validated();

        Log::info('Updating department', ['identifier' => $identifier]);

        $department = DB::transaction(function () use ($identifier, $data): Department {
            return $this->repository->updateDepartment($identifier, $data);
        });

        Log::info('Department updated', ['identifier' => $department->identifier]);

        return $this->success(DepartmentResource::make($department)->resolve());
    }

    public function destroy(string $identifier): JsonResponse
    {
        $department = $this->repository->readDepartment($identifier);

        Gate::authorize('delete', $department);

        Log::info('Deleting department', ['identifier' => $identifier]);

        DB::transaction(function () use ($identifier): void {
            $this->repository->deleteDepartment($identifier);
        });

        Log::info('Department deleted', ['identifier' => $identifier]);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    public function restore(string $identifier): JsonResponse
    {
        $department = $this->repository->readTrashedDepartment($identifier);

        Gate::authorize('restore', $department);

        Log::info('Restoring department', ['identifier' => $identifier]);

        $department = DB::transaction(function () use ($identifier): Department {
            return $this->repository->restoreDepartment($identifier);
        });

        Log::info('Department restored', ['identifier' => $department->identifier]);

        return $this->success(DepartmentResource::make($department)->resolve());
    }
}
