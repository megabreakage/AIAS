<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Central;

use App\Filters\Central\Countries\CountryFilters;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Central\Country\CreateCountryRequest;
use App\Http\Requests\Central\Country\UpdateCountryRequest;
use App\Http\Resources\Central\Country\CountryResource;
use App\Models\Central\Country;
use App\Repositories\Central\CountryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class CountryController extends BaseApiController
{
    public function __construct(protected CountryRepository $repository) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Country::class);

        $filters = CountryFilters::fromRequest($request);

        $countries = $this->repository->browseCountries(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc',
        );

        return $this->paginated($countries, CountryResource::class);
    }

    public function store(CreateCountryRequest $request): JsonResponse
    {
        Gate::authorize('create', Country::class);

        $data = $request->validated();

        Log::info('Creating country', ['name' => $data['name']]);

        $country = DB::connection('central')->transaction(function () use ($data): Country {
            return $this->repository->createCountry($data);
        });

        Log::info('Country created', ['identifier' => $country->identifier]);

        return $this->success(
            CountryResource::make($country->load(['continent', 'createdBy', 'updatedBy']))->resolve(),
            Response::HTTP_CREATED,
        );
    }

    public function show(string $identifier): JsonResponse
    {
        $country = $this->repository->readCountry($identifier);

        Gate::authorize('view', $country);

        return $this->success(CountryResource::make($country)->resolve());
    }

    public function update(UpdateCountryRequest $request, string $identifier): JsonResponse
    {
        $country = $this->repository->readCountry($identifier);

        Gate::authorize('update', $country);

        $data = $request->validated();

        Log::info('Updating country', ['identifier' => $identifier]);

        $country = DB::connection('central')->transaction(function () use ($identifier, $data): Country {
            return $this->repository->updateCountry($identifier, $data);
        });

        Log::info('Country updated', ['identifier' => $country->identifier]);

        return $this->success(CountryResource::make($country)->resolve());
    }

    public function destroy(string $identifier): JsonResponse
    {
        $country = $this->repository->readCountry($identifier);

        Gate::authorize('delete', $country);

        Log::info('Deleting country', ['identifier' => $identifier]);

        DB::connection('central')->transaction(function () use ($identifier): void {
            $this->repository->deleteCountry($identifier);
        });

        Log::info('Country deleted', ['identifier' => $identifier]);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    public function restore(string $identifier): JsonResponse
    {
        $country = $this->repository->readTrashedCountry($identifier);

        Gate::authorize('restore', $country);

        Log::info('Restoring country', ['identifier' => $identifier]);

        $country = DB::connection('central')->transaction(function () use ($identifier): Country {
            return $this->repository->restoreCountry($identifier);
        });

        Log::info('Country restored', ['identifier' => $country->identifier]);

        return $this->success(CountryResource::make($country)->resolve());
    }
}
