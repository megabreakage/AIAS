<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Filters\Tenant\Preambles\PreambleFilters;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Tenant\Preambles\CreatePreambleRequest;
use App\Http\Requests\Tenant\Preambles\UpdatePreambleRequest;
use App\Http\Resources\Tenant\Preamble\PreambleResource;
use App\Models\Tenant\Preamble;
use App\Repositories\Tenant\PreambleRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class PreambleController extends BaseApiController
{
    public function __construct(protected PreambleRepository $repository) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Preamble::class);

        $filters = PreambleFilters::fromRequest($request);

        $preambles = $this->repository->browsePreambles(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc',
        );

        return $this->paginated($preambles, PreambleResource::class);
    }

    public function store(CreatePreambleRequest $request): JsonResponse
    {
        Gate::authorize('create', Preamble::class);

        $data = $request->validated();
        $data['tenant_id'] = tenant()?->id;

        Log::info('Creating preamble', ['name' => $data['name'], 'tenant_id' => $data['tenant_id']]);

        $preamble = DB::transaction(function () use ($data): Preamble {
            return $this->repository->createPreamble($data);
        });

        Log::info('Preamble created', ['identifier' => $preamble->identifier]);

        return $this->success(
            PreambleResource::make($preamble->load(['creator', 'updater']))->resolve(),
            Response::HTTP_CREATED,
        );
    }

    public function show(string $identifier): JsonResponse
    {
        $preamble = $this->repository->readPreamble($identifier);

        Gate::authorize('view', $preamble);

        return $this->success(PreambleResource::make($preamble)->resolve());
    }

    public function update(UpdatePreambleRequest $request, string $identifier): JsonResponse
    {
        $preamble = $this->repository->readPreamble($identifier);

        Gate::authorize('update', $preamble);

        $data = $request->validated();

        Log::info('Updating preamble', ['identifier' => $identifier]);

        $preamble = DB::transaction(function () use ($identifier, $data): Preamble {
            return $this->repository->updatePreamble($identifier, $data);
        });

        Log::info('Preamble updated', ['identifier' => $preamble->identifier]);

        return $this->success(PreambleResource::make($preamble)->resolve());
    }

    public function destroy(string $identifier): JsonResponse
    {
        $preamble = $this->repository->readPreamble($identifier);

        Gate::authorize('delete', $preamble);

        Log::info('Deleting preamble', ['identifier' => $identifier]);

        DB::transaction(function () use ($identifier): void {
            $this->repository->deletePreamble($identifier);
        });

        Log::info('Preamble deleted', ['identifier' => $identifier]);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    public function restore(string $identifier): JsonResponse
    {
        $preamble = $this->repository->readTrashedPreamble($identifier);

        Gate::authorize('restore', $preamble);

        Log::info('Restoring preamble', ['identifier' => $identifier]);

        $preamble = DB::transaction(function () use ($identifier): Preamble {
            return $this->repository->restorePreamble($identifier);
        });

        Log::info('Preamble restored', ['identifier' => $preamble->identifier]);

        return $this->success(PreambleResource::make($preamble)->resolve());
    }
}
