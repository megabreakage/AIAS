<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Central;

use App\Filters\Central\Continents\ContinentFilters;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Central\Continent\CreateContinentRequest;
use App\Http\Requests\Central\Continent\UpdateContinentRequest;
use App\Http\Resources\Central\Continent\ContinentResource;
use App\Models\Central\Continent;
use App\Repositories\Central\ContinentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ContinentController extends BaseApiController
{
    public function __construct(protected ContinentRepository $repository) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Continent::class);

        $filters = ContinentFilters::fromRequest($request);

        $continents = $this->repository->browseContinents(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc',
        );

        return $this->paginated($continents, ContinentResource::class);
    }

    public function store(CreateContinentRequest $request): JsonResponse
    {
        Gate::authorize('create', Continent::class);

        $data = $request->validated();

        Log::info('Creating continent', ['name' => $data['name']]);

        $continent = DB::connection('central')->transaction(function () use ($data): Continent {
            return $this->repository->createContinent($data);
        });

        Log::info('Continent created', ['identifier' => $continent->identifier]);

        return $this->success(
            ContinentResource::make($continent->load(['createdBy', 'updatedBy']))->resolve(),
            Response::HTTP_CREATED,
        );
    }

    public function show(string $identifier): JsonResponse
    {
        $continent = $this->repository->readContinent($identifier);

        Gate::authorize('view', $continent);

        return $this->success(ContinentResource::make($continent)->resolve());
    }

    public function update(UpdateContinentRequest $request, string $identifier): JsonResponse
    {
        $continent = $this->repository->readContinent($identifier);

        Gate::authorize('update', $continent);

        $data = $request->validated();

        Log::info('Updating continent', ['identifier' => $identifier]);

        $continent = DB::connection('central')->transaction(function () use ($identifier, $data): Continent {
            return $this->repository->updateContinent($identifier, $data);
        });

        Log::info('Continent updated', ['identifier' => $continent->identifier]);

        return $this->success(ContinentResource::make($continent)->resolve());
    }

    public function destroy(string $identifier): JsonResponse
    {
        $continent = $this->repository->readContinent($identifier);

        Gate::authorize('delete', $continent);

        Log::info('Deleting continent', ['identifier' => $identifier]);

        DB::connection('central')->transaction(function () use ($identifier): void {
            $this->repository->deleteContinent($identifier);
        });

        Log::info('Continent deleted', ['identifier' => $identifier]);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    public function restore(string $identifier): JsonResponse
    {
        $continent = $this->repository->readTrashedContinent($identifier);

        Gate::authorize('restore', $continent);

        Log::info('Restoring continent', ['identifier' => $identifier]);

        $continent = DB::connection('central')->transaction(function () use ($identifier): Continent {
            return $this->repository->restoreContinent($identifier);
        });

        Log::info('Continent restored', ['identifier' => $continent->identifier]);

        return $this->success(ContinentResource::make($continent)->resolve());
    }
}
