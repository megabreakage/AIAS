<?php

declare(strict_types=1);

namespace App\Repositories\Central;

use App\Filters\Central\SectionStyles\SectionStyleFilters;
use App\Models\Central\SectionStyle;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SectionStyleRepository extends BaseRepository
{
    protected function model(): string
    {
        return SectionStyle::class;
    }

    /**
     * Browse section styles with filters, sorting, and pagination.
     */
    public function browseSectionStyles(
        SectionStyleFilters $filters,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = null,
        bool $sortDesc = false,
    ): LengthAwarePaginator {
        $query = SectionStyle::on('central')->with(['creator', 'updater']);

        $filters->apply($query);

        $sortColumn = in_array($sortBy, ['name', 'columns', 'is_active', 'is_featured', 'created_at'], true)
            ? $sortBy
            : 'name';

        $query->orderBy($sortColumn, $sortDesc ? 'desc' : 'asc');

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1),
        );
    }

    /**
     * Find a section style by identifier.
     *
     * @throws ModelNotFoundException
     */
    public function readSectionStyle(string $identifier): SectionStyle
    {
        /** @var SectionStyle */
        return SectionStyle::on('central')
            ->where('identifier', $identifier)
            ->with(['creator', 'updater'])
            ->firstOrFail();
    }

    /**
     * Find a soft-deleted section style by identifier.
     *
     * @throws ModelNotFoundException
     */
    public function readTrashedSectionStyle(string $identifier): SectionStyle
    {
        /** @var SectionStyle */
        return SectionStyle::on('central')
            ->withTrashed()
            ->where('identifier', $identifier)
            ->with(['creator', 'updater'])
            ->firstOrFail();
    }

    /**
     * Create a new section style.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSectionStyle(array $data): SectionStyle
    {
        /** @var SectionStyle */
        $sectionStyle = SectionStyle::on('central')->create($data);

        return $sectionStyle->load(['creator', 'updater']);
    }

    /**
     * Update an existing section style.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSectionStyle(string $identifier, array $data): SectionStyle
    {
        $sectionStyle = $this->readSectionStyle($identifier);
        $sectionStyle->fill($data)->save();

        return $sectionStyle->fresh(['creator', 'updater']);
    }

    /**
     * Soft-delete a section style.
     */
    public function deleteSectionStyle(string $identifier): void
    {
        $sectionStyle = $this->readSectionStyle($identifier);
        $sectionStyle->delete();
    }

    /**
     * Restore a soft-deleted section style.
     */
    public function restoreSectionStyle(string $identifier): SectionStyle
    {
        $sectionStyle = $this->readTrashedSectionStyle($identifier);
        $sectionStyle->restore();

        return $sectionStyle->fresh(['creator', 'updater']);
    }
}
