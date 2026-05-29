<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Filters\Tenant\Companies\CompanyFilters;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Tenant\Companies\CreateCompanyRequest;
use App\Http\Requests\Tenant\Companies\UpdateCompanyRequest;
use App\Http\Resources\Tenant\Company\CompanyResource;
use App\Models\Tenant\Company;
use App\Repositories\Tenant\CompanyRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class CompanyController extends BaseApiController
{
    public function __construct(protected CompanyRepository $repository) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Company::class);

        $filters = CompanyFilters::fromRequest($request);

        $companies = $this->repository->browseCompanies(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc',
        );

        return $this->paginated($companies, CompanyResource::class);
    }

    public function store(CreateCompanyRequest $request): JsonResponse
    {
        Gate::authorize('create', Company::class);

        $data = $request->validated();
        $data['tenant_id'] = tenant()?->id;

        Log::info('Creating company', ['name' => $data['name'], 'tenant_id' => $data['tenant_id']]);

        $company = DB::transaction(function () use ($data): Company {
            return $this->repository->createCompany($data);
        });

        Log::info('Company created', ['identifier' => $company->identifier]);

        return $this->success(
            CompanyResource::make($company->load(['creator', 'updater', 'contacts.contactUser']))->resolve(),
            Response::HTTP_CREATED,
        );
    }

    public function show(string $identifier): JsonResponse
    {
        $company = $this->repository->readCompany($identifier);

        Gate::authorize('view', $company);

        return $this->success(CompanyResource::make($company)->resolve());
    }

    public function update(UpdateCompanyRequest $request, string $identifier): JsonResponse
    {
        $company = $this->repository->readCompany($identifier);

        Gate::authorize('update', $company);

        $data = $request->validated();

        Log::info('Updating company', ['identifier' => $identifier]);

        $company = DB::transaction(function () use ($identifier, $data): Company {
            return $this->repository->updateCompany($identifier, $data);
        });

        Log::info('Company updated', ['identifier' => $company->identifier]);

        return $this->success(CompanyResource::make($company)->resolve());
    }

    public function destroy(string $identifier): JsonResponse
    {
        $company = $this->repository->readCompany($identifier);

        Gate::authorize('delete', $company);

        Log::info('Deleting company', ['identifier' => $identifier]);

        DB::transaction(function () use ($identifier): void {
            $this->repository->deleteCompany($identifier);
        });

        Log::info('Company deleted', ['identifier' => $identifier]);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    public function restore(string $identifier): JsonResponse
    {
        $company = $this->repository->readTrashedCompany($identifier);

        Gate::authorize('restore', $company);

        Log::info('Restoring company', ['identifier' => $identifier]);

        $company = DB::transaction(function () use ($identifier): Company {
            return $this->repository->restoreCompany($identifier);
        });

        Log::info('Company restored', ['identifier' => $company->identifier]);

        return $this->success(CompanyResource::make($company)->resolve());
    }
}
