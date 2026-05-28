<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PriorityLevels\CreatePriorityLevelRequest;
use App\Http\Requests\PriorityLevels\UpdatePriorityLevelRequest;
use App\Http\Resources\PriorityLevels\PriorityLevelCollection;
use App\Http\Resources\PriorityLevels\PriorityLevelResource;
use App\Models\PriorityLevel;
use App\Repositories\PriorityLevelRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class PriorityLevelController extends Controller
{
    public function __construct(
        protected PriorityLevelRepository $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PriorityLevel::class);

        $filters = [
            'search' => $request->query('search'),
            'is_active' => $request->query('is_active'),
        ];

        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 15);

        $priority_levels = $this->repository->browsePriorityLevels(
            filters: $filters,
            page: $page,
            perPage: $perPage,
        );

        return (new PriorityLevelCollection($priority_levels))
            ->setMessage('Priority Levels retrieved successfully')
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function store(CreatePriorityLevelRequest $request): JsonResponse
    {
        Gate::authorize('create', PriorityLevel::class);

        try {
            $data = $request->validated();

            Log::info('Creating priority level', ['data' => $data]);

            $priorityLevel = DB::transaction(function () use ($data) {
                return $this->repository->createPriorityLevel($data);
            });

            Log::info('Priority Level created', ['id' => $priorityLevel->id]);

            return (new PriorityLevelResource($priorityLevel))
                ->setMessage('Priority Level created successfully')
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            Log::error('Failed to create priority level', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create priority level',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $priorityLevel = $this->repository->readPriorityLevel($id);

            Gate::authorize('view', $priorityLevel);

            return (new PriorityLevelResource($priorityLevel))
                ->setMessage('Priority Level retrieved successfully')
                ->response();
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Priority Level not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        }
    }

    public function update(UpdatePriorityLevelRequest $request, string $id): JsonResponse
    {
        try {
            $priorityLevel = $this->repository->readPriorityLevel($id);

            Gate::authorize('update', $priorityLevel);

            $data = $request->validated();

            Log::info('Updating priority level', ['id' => $id]);

            $priorityLevel = DB::transaction(function () use ($id, $data) {
                return $this->repository->updatePriorityLevel($id, $data);
            });

            Log::info('Priority Level updated', ['id' => $priorityLevel->id]);

            return (new PriorityLevelResource($priorityLevel))
                ->setMessage('Priority Level updated successfully')
                ->response();
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Priority Level not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Failed to update priority level', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update priority level',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $priorityLevel = $this->repository->readPriorityLevel($id);

            Gate::authorize('delete', $priorityLevel);

            Log::info('Deleting priority level', ['id' => $id]);

            DB::transaction(function () use ($id): void {
                $this->repository->deletePriorityLevel($id);
            });

            Log::info('Priority Level deleted', ['id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Priority Level deleted successfully',
                'data' => null,
            ], Response::HTTP_OK);
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Priority Level not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Failed to delete priority level', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete priority level',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function restore(string $id): JsonResponse
    {
        try {
            $priorityLevel = PriorityLevel::query()->withTrashed()
                ->where('identifier', $id)
                ->firstOrFail();

            Gate::authorize('restore', $priorityLevel);

            Log::info('Restoring priority level', ['id' => $id]);

            $priorityLevel = DB::transaction(function () use ($id) {
                return $this->repository->restorePriorityLevel($id);
            });

            Log::info('Priority Level restored', ['id' => $id]);

            return (new PriorityLevelResource($priorityLevel))
                ->setMessage('Priority Level restored successfully')
                ->response();
        } catch (ModelNotFoundException) {
            return response()->json([
                'status' => 'error',
                'message' => 'Priority Level not found',
                'data' => null,
            ], Response::HTTP_NOT_FOUND);
        } catch (\Throwable $e) {
            Log::error('Failed to restore priority level', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to restore priority level',
                'data' => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
