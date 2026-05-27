<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Central;

use App\Filters\Central\SectionStyles\SectionStyleFilters;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\Central\SectionStyle\CreateSectionStyleRequest;
use App\Http\Requests\Central\SectionStyle\UpdateSectionStyleRequest;
use App\Http\Resources\Central\SectionStyle\SectionStyleResource;
use App\Models\Central\SectionStyle;
use App\Repositories\Central\SectionStyleRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class SectionStyleController extends BaseApiController
{
    public function __construct(protected SectionStyleRepository $repository) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SectionStyle::class);

        $filters = SectionStyleFilters::fromRequest($request);

        $sectionStyles = $this->repository->browseSectionStyles(
            filters: $filters,
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
            sortBy: $request->input('sort_by'),
            sortDesc: $request->input('sort_order') === 'desc',
        );

        return $this->paginated($sectionStyles, SectionStyleResource::class);
    }

    public function store(CreateSectionStyleRequest $request): JsonResponse
    {
        Gate::authorize('create', SectionStyle::class);

        $data = $request->validated();

        Log::info('Creating section style', ['name' => $data['name']]);

        $sectionStyle = DB::connection('central')->transaction(function () use ($data): SectionStyle {
            return $this->repository->createSectionStyle($data);
        });

        Log::info('Section style created', ['identifier' => $sectionStyle->identifier]);

        return $this->success(
            SectionStyleResource::make($sectionStyle)->resolve(),
            Response::HTTP_CREATED,
        );
    }

    public function show(string $identifier): JsonResponse
    {
        $sectionStyle = $this->repository->readSectionStyle($identifier);

        Gate::authorize('view', $sectionStyle);

        return $this->success(SectionStyleResource::make($sectionStyle)->resolve());
    }

    public function update(UpdateSectionStyleRequest $request, string $identifier): JsonResponse
    {
        $sectionStyle = $this->repository->readSectionStyle($identifier);

        Gate::authorize('update', $sectionStyle);

        $data = $request->validated();

        Log::info('Updating section style', ['identifier' => $identifier]);

        $sectionStyle = DB::connection('central')->transaction(function () use ($identifier, $data): SectionStyle {
            return $this->repository->updateSectionStyle($identifier, $data);
        });

        Log::info('Section style updated', ['identifier' => $sectionStyle->identifier]);

        return $this->success(SectionStyleResource::make($sectionStyle)->resolve());
    }

    public function destroy(string $identifier): JsonResponse
    {
        $sectionStyle = $this->repository->readSectionStyle($identifier);

        Gate::authorize('delete', $sectionStyle);

        Log::info('Deleting section style', ['identifier' => $identifier]);

        DB::connection('central')->transaction(function () use ($identifier): void {
            $this->repository->deleteSectionStyle($identifier);
        });

        Log::info('Section style deleted', ['identifier' => $identifier]);

        return $this->success(null, Response::HTTP_NO_CONTENT);
    }

    public function restore(string $identifier): JsonResponse
    {
        $sectionStyle = $this->repository->readTrashedSectionStyle($identifier);

        Gate::authorize('restore', $sectionStyle);

        Log::info('Restoring section style', ['identifier' => $identifier]);

        $sectionStyle = DB::connection('central')->transaction(function () use ($identifier): SectionStyle {
            return $this->repository->restoreSectionStyle($identifier);
        });

        Log::info('Section style restored', ['identifier' => $sectionStyle->identifier]);

        return $this->success(SectionStyleResource::make($sectionStyle)->resolve());
    }
}
