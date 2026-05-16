<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

abstract class BaseRepository
{
    abstract protected function model(): string;

    public function newQuery(): Builder
    {
        return ($this->model())::query();
    }

    public function findByIdentifier(string $identifier): Model
    {
        return ($this->model())::query()->where('identifier', $identifier)->firstOrFail();
    }

    public function browseAll(): Collection
    {
        $results = $this->newQuery()->get();
        if ($results->count() > 1000) {
            Log::warning('browseAll() returned a large result set', [
                'model' => $this->model(),
                'count' => $results->count(),
            ]);
        }
        return $results;
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()->paginate($perPage);
    }

    public function create(array $data): Model
    {
        return ($this->model())::query()->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->fill($data)->save();
        return $model;
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    public function forceDelete(Model $model): void
    {
        $model->forceDelete();
    }
}
