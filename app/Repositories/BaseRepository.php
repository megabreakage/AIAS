<?php

declare(strict_types=1);

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository
{
    /** @return class-string<Model> */
    abstract protected function model(): string;

    public function query(): Builder
    {
        return ($this->model())::query();
    }

    public function newQuery(): Builder
    {
        return $this->query();
    }

    public function read(string $identifier): Model
    {
        return $this->query()->where('identifier', $identifier)->firstOrFail();
    }

    public function browse(): Collection
    {
        return $this->query()->get();
    }

    public function paginate(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        return $this->query()->paginate(perPage: $perPage, page: $page);
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): Model
    {
        return $this->query()->create($data);
    }

    /** @param array<string, mixed> $data */
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
