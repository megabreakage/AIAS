<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Filters\Central\Tenant\TenantFilters;
use App\Http\Requests\Tenants\CreateTenantRequest;
use App\Http\Resources\Tenant\TenantResource;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class TenantController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::query()->with('domains', 'owner');

        $filters = TenantFilters::fromRequest($request);
        $filters->apply($query);

        $tenants = $query->paginate(
            perPage: min($request->integer('per_page', 15), 100),
            page: max($request->integer('page', 1), 1),
        );

        return $this->paginated($tenants, TenantResource::class);
    }

    public function store(CreateTenantRequest $request): JsonResponse
    {
        $data = $request->validated();

        $domain = $data['domain'] ?? $this->generateUniqueDomain($data['name']);

        Log::info('Creating tenant', ['name' => $data['name'], 'domain' => $domain]);

        try {
            $tenant = DB::transaction(function () use ($data, $domain): Tenant {
                /** @var Tenant $tenant */
                $tenant = Tenant::create([
                    'owner_id' => $data['owner_id'],
                    'name' => $data['name'],
                    'domain' => $domain,
                    'logo' => $data['logo'] ?? null,
                    'country_id' => $data['country_id'] ?? null,
                    'data_center' => $data['data_center'] ?? null,
                    'status' => $data['status'] ?? 'active',
                ]);

                $tenant->domains()->create(['domain' => $domain]);

                return $tenant;
            });

            Log::info('Tenant created', ['id' => $tenant->id, 'domain' => $domain]);

            return $this->success(
                TenantResource::make($tenant->refresh()->load('domains', 'owner'))->resolve(),
                Response::HTTP_CREATED,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create tenant', [
                'name' => $data['name'],
                'error' => $e->getMessage(),
            ]);

            return $this->error(
                'TENANT_CREATION_FAILED',
                'Failed to create tenant: '.$e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['name' => $data['name'], 'domain' => $domain],
            );
        }
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with('domains', 'owner')->find($id);

        if (!$tenant) {
            return $this->error(
                'TENANT_NOT_FOUND',
                "Tenant with ID '{$id}' was not found.",
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->success(TenantResource::make($tenant)->resolve());
    }

    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::find($id);

        if (!$tenant) {
            return $this->error(
                'TENANT_NOT_FOUND',
                "Tenant with ID '{$id}' was not found.",
                Response::HTTP_NOT_FOUND,
            );
        }

        Log::info('Deleting tenant', ['id' => $id]);

        try {
            $tenant->delete();

            return $this->success(null, Response::HTTP_NO_CONTENT);
        } catch (\Throwable $e) {
            Log::error('Failed to delete tenant', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->error(
                'TENANT_DELETION_FAILED',
                'Failed to delete tenant: '.$e->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['id' => $id],
            );
        }
    }

    /**
     * Generate a unique domain slug from the tenant name.
     * Falls back to appending a timestamp if the slug is already taken.
     */
    private function generateUniqueDomain(string $name): string
    {
        $base = Str::slug($name);
        $domain = "{$base}.localhost";

        if (!Tenant::where('domain', $domain)->exists()
            && !DB::connection('central')->table('domains')->where('domain', $domain)->exists()) {
            return $domain;
        }

        $domain = "{$base}-".time().'.localhost';

        return $domain;
    }
}
