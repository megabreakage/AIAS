<?php

declare(strict_types=1);

namespace App\Repositories\Central;

use App\Filters\Central\Continents\ContinentFilters;
use App\Models\Central\Continent;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ContinentRepository extends BaseRepository
{
    protected function model(): string
    {
        return Continent::class;
    }

    /**
     * Browse continents with filters, sorting, and pagination.
     */
    public function browseContinents(
        ContinentFilters $filters,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = null,
        bool $sortDesc = false,
    ): LengthAwarePaginator {
        $query = Continent::on('central')->with(['createdBy', 'updatedBy']);

        $filters->apply($query);

        $sortColumn = in_array($sortBy, ['name', 'short_code', 'iso_code', 'status', 'created_at'], true)
            ? $sortBy
            : 'name';

        $query->orderBy($sortColumn, $sortDesc ? 'desc' : 'asc');

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1),
        );
    }

    /**
     * Find a continent by identifier.
     *
     * @throws ModelNotFoundException
     */
    public function readContinent(string $identifier): Continent
    {
        /** @var Continent */
        return Continent::on('central')
            ->where('identifier', $identifier)
            ->with(['createdBy', 'updatedBy'])
            ->firstOrFail();
    }

    /**
     * Find a soft-deleted continent by identifier.
     *
     * @throws ModelNotFoundException
     */
    public function readTrashedContinent(string $identifier): Continent
    {
        /** @var Continent */
        return Continent::on('central')
            ->withTrashed()
            ->where('identifier', $identifier)
            ->with(['createdBy', 'updatedBy'])
            ->firstOrFail();
    }

    /**
     * Create a new continent.
     *
     * @param  array<string, mixed>  $data
     */
    public function createContinent(array $data): Continent
    {
        /** @var Continent */
        return Continent::on('central')->create($data);
    }

    /**
     * Update an existing continent.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateContinent(string $identifier, array $data): Continent
    {
        $continent = $this->readContinent($identifier);
        $continent->fill($data)->save();

        return $continent->fresh(['createdBy', 'updatedBy']);
    }

    /**
     * Soft-delete a continent.
     */
    public function deleteContinent(string $identifier): void
    {
        $continent = $this->readContinent($identifier);
        $continent->delete();
    }

    /**
     * Restore a soft-deleted continent.
     */
    public function restoreContinent(string $identifier): Continent
    {
        $continent = $this->readTrashedContinent($identifier);
        $continent->restore();

        return $continent->fresh(['createdBy', 'updatedBy']);
    }
}
