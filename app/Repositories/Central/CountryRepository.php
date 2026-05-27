<?php

declare(strict_types=1);

namespace App\Repositories\Central;

use App\Filters\Central\Countries\CountryFilters;
use App\Models\Central\Country;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CountryRepository extends BaseRepository
{
    protected function model(): string
    {
        return Country::class;
    }

    /**
     * Browse countries with filters, sorting, and pagination.
     */
    public function browseCountries(
        CountryFilters $filters,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = null,
        bool $sortDesc = false,
    ): LengthAwarePaginator {
        $query = Country::on('central')->with(['continent', 'createdBy', 'updatedBy']);

        $filters->apply($query);

        $sortColumn = in_array($sortBy, ['name', 'short_code', 'iso_code', 'currency', 'country_code', 'status', 'created_at'], true)
            ? $sortBy
            : 'name';

        $query->orderBy($sortColumn, $sortDesc ? 'desc' : 'asc');

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1),
        );
    }

    /**
     * Find a country by identifier.
     *
     * @throws ModelNotFoundException
     */
    public function readCountry(string $identifier): Country
    {
        /** @var Country */
        return Country::on('central')
            ->where('identifier', $identifier)
            ->with(['continent', 'createdBy', 'updatedBy'])
            ->firstOrFail();
    }

    /**
     * Find a soft-deleted country by identifier.
     *
     * @throws ModelNotFoundException
     */
    public function readTrashedCountry(string $identifier): Country
    {
        /** @var Country */
        return Country::on('central')
            ->withTrashed()
            ->where('identifier', $identifier)
            ->with(['continent', 'createdBy', 'updatedBy'])
            ->firstOrFail();
    }

    /**
     * Create a new country.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCountry(array $data): Country
    {
        /** @var Country */
        return Country::on('central')->create($data);
    }

    /**
     * Update an existing country.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCountry(string $identifier, array $data): Country
    {
        $country = $this->readCountry($identifier);
        $country->fill($data)->save();

        return $country->fresh(['continent', 'createdBy', 'updatedBy']);
    }

    /**
     * Soft-delete a country.
     */
    public function deleteCountry(string $identifier): void
    {
        $country = $this->readCountry($identifier);
        $country->delete();
    }

    /**
     * Restore a soft-deleted country.
     */
    public function restoreCountry(string $identifier): Country
    {
        $country = $this->readTrashedCountry($identifier);
        $country->restore();

        return $country->fresh(['continent', 'createdBy', 'updatedBy']);
    }
}
