<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\PriorityLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PriorityLevelRepository extends BaseRepository
{
    protected function model(): string
    {
        return PriorityLevel::class;
    }

    public function browsePriorityLevels(
        array $filters = [],
        int $page = 1,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = $this->newQuery()->with(['createdBy', 'updatedBy']);

        if (! auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->id);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate(perPage: min($perPage, 100), page: max($page, 1));
    }

    public function readPriorityLevel(string $identifier): Model
    {
        $query = $this->newQuery()
            ->with(['createdBy', 'updatedBy'])
            ->where('identifier', $identifier);

        if (! auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->id);
        }

        return $query->firstOrFail();
    }

    public function createPriorityLevel(array $data): Model
    {
        $data['tenant_id'] ??= tenant()?->id;

        return $this->create($data);
    }

    public function updatePriorityLevel(string $identifier, array $data): Model
    {
        $model = $this->findByIdentifier($identifier);

        return $this->update($model, $data);
    }

    public function deletePriorityLevel(string $identifier): void
    {
        $model = $this->findByIdentifier($identifier);
        $this->delete($model);
    }

    public function restorePriorityLevel(string $identifier): Model
    {
        $model = PriorityLevel::query()->withTrashed()
            ->where('identifier', $identifier)
            ->firstOrFail();

        $model->restore();

        return $model->fresh();
    }
}
