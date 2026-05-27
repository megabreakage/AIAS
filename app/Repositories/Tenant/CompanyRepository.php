<?php

declare(strict_types=1);

namespace App\Repositories\Tenant;

use App\Filters\Tenant\Companies\CompanyFilters;
use App\Models\Tenant\Company;
use App\Models\Tenant\CompanyContact;
use App\Repositories\BaseRepository;
use App\Services\GeocodingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

class CompanyRepository extends BaseRepository
{
    public function __construct(protected GeocodingService $geocodingService) {}

    protected function model(): string
    {
        return Company::class;
    }

    /**
     * Browse companies with filters, sorting, and pagination.
     * Non-super-admin users are scoped to the current tenant.
     */
    public function browseCompanies(
        CompanyFilters $filters,
        int $page = 1,
        int $perPage = 15,
        ?string $sortBy = null,
        bool $sortDesc = false,
    ): LengthAwarePaginator {
        $query = $this->newQuery()->with(['creator', 'updater', 'contacts.contactUser']);

        if (! auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        $filters->apply($query);

        $sortColumn = in_array($sortBy, ['name', 'reference_number', 'level_of_operations', 'is_active', 'is_featured', 'created_at'], true)
            ? $sortBy
            : 'created_at';

        $query->orderBy($sortColumn, $sortDesc ? 'desc' : 'asc');

        return $query->paginate(
            perPage: min($perPage, 100),
            page: max($page, 1),
        );
    }

    /**
     * Find a company by identifier (active records only).
     *
     * @throws ModelNotFoundException
     */
    public function readCompany(string $identifier): Company
    {
        $query = $this->newQuery()
            ->where('identifier', $identifier)
            ->with(['creator', 'updater', 'contacts.contactUser']);

        if (! auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        /** @var Company */
        return $query->firstOrFail();
    }

    /**
     * Find a soft-deleted company by identifier (includes trashed).
     *
     * @throws ModelNotFoundException
     */
    public function readTrashedCompany(string $identifier): Company
    {
        $query = $this->newQuery()
            ->withTrashed()
            ->where('identifier', $identifier)
            ->with(['creator', 'updater', 'contacts.contactUser']);

        if (! auth()->user()?->hasRole('super-admin')) {
            $query->where('tenant_id', tenant()?->getTenantKey());
        }

        /** @var Company */
        return $query->firstOrFail();
    }

    /**
     * Create a new company, geocoding the office_location if provided.
     *
     * @param  array<string, mixed>  $data
     */
    public function createCompany(array $data): Company
    {
        $contacts = (array) ($data['company_contacts'] ?? []);
        unset($data['company_contacts']);

        $data = $this->applyGeocoding($data);
        $data = $this->handleLogoUpload($data);

        /** @var Company */
        $company = $this->newQuery()->create($data);

        if (! empty($contacts)) {
            $this->syncContacts($company, $contacts);
        }

        return $company->fresh(['creator', 'updater', 'contacts.contactUser']);
    }

    /**
     * Update an existing company.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateCompany(string $identifier, array $data): Company
    {
        $company = $this->readCompany($identifier);

        $contacts = array_key_exists('company_contacts', $data) ? (array) $data['company_contacts'] : null;
        unset($data['company_contacts']);

        if (isset($data['office_location']) && $data['office_location'] !== $company->office_location) {
            $data = $this->applyGeocoding($data);
        }

        $data = $this->handleLogoUpload($data, $company->logo);

        $company->fill($data)->save();

        if ($contacts !== null) {
            $this->syncContacts($company, $contacts);
        }

        return $company->fresh(['creator', 'updater', 'contacts.contactUser']);
    }

    /**
     * Soft-delete a company.
     */
    public function deleteCompany(string $identifier): void
    {
        $company = $this->readCompany($identifier);
        $company->delete();
    }

    /**
     * Restore a soft-deleted company.
     */
    public function restoreCompany(string $identifier): Company
    {
        $company = $this->readTrashedCompany($identifier);
        $company->restore();

        return $company->fresh(['creator', 'updater', 'contacts.contactUser']);
    }

    /**
     * Apply geocoding data if office_location is present in data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyGeocoding(array $data): array
    {
        if (! empty($data['office_location'])) {
            $geo = $this->geocodingService->geocode((string) $data['office_location']);

            if ($geo['latitude'] !== null) {
                $data['latitude'] = $data['latitude'] ?? $geo['latitude'];
            }

            if ($geo['longitude'] !== null) {
                $data['longitude'] = $data['longitude'] ?? $geo['longitude'];
            }

            if ($geo['country_id'] !== null) {
                $data['country_id'] = $data['country_id'] ?? $geo['country_id'];
            }
        }

        return $data;
    }

    /**
     * Handle logo file upload, returning updated data with the stored path.
     * Deletes the old logo file if replaced.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function handleLogoUpload(array $data, ?string $existingLogo = null): array
    {
        if (! isset($data['logo'])) {
            return $data;
        }

        $logo = $data['logo'];

        if ($logo instanceof \Illuminate\Http\UploadedFile) {
            if ($existingLogo && Storage::disk('public')->exists($existingLogo)) {
                Storage::disk('public')->delete($existingLogo);
            }

            $path = $logo->store('companies/logos', 'public');
            $data['logo'] = $path;
        }

        return $data;
    }

    /**
     * Sync company contacts — replaces existing contacts with provided list.
     *
     * @param  array<int, array<string, mixed>>  $contacts
     */
    private function syncContacts(Company $company, array $contacts): void
    {
        $company->contacts()->delete();

        foreach ($contacts as $contact) {
            $company->contacts()->create([
                'user_id' => $contact['user_id'] ?? null,
                'contact_type' => $contact['contact_type'] ?? 'primary',
            ]);
        }
    }
}
