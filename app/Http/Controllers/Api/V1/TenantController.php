<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Tenants\CreateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Central\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class TenantController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $tenants = Tenant::query()->with('domains')->get();
        return $this->success(TenantResource::collection($tenants)->resolve());
    }

    public function store(CreateTenantRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Auto-generate a slug-based ID from name if not provided
        $tenantId = $data['id'] ?? Str::slug($data['name']);

        Log::info('Creating tenant', ['id' => $tenantId]);

        $tenant = DB::transaction(function () use ($data, $tenantId): Tenant {
            /** @var Tenant $tenant */
            $tenant = Tenant::create([
                'id'     => $tenantId,
                'name'   => $data['name'],
                'plan'   => $data['plan'] ?? 'starter',
                'status' => 'active',
            ]);

            if (! empty($data['domain'])) {
                $tenant->domains()->create(['domain' => $data['domain']]);
            }

            return $tenant;
        });

        Log::info('Tenant created', ['id' => $tenant->id]);

        return $this->success(
            TenantResource::make($tenant->load('domains'))->resolve(),
            Response::HTTP_CREATED
        );
    }

    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::with('domains')->findOrFail($id);
        return $this->success(TenantResource::make($tenant)->resolve());
    }

    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        Log::info('Deleting tenant', ['id' => $id]);

        $tenant->delete();

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }
}
