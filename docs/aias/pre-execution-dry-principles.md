# AIAS — DRY Principles Pre-Execution Hook

## Purpose

This hook MUST be reviewed before writing ANY code. It enforces the DRY (Don't Repeat Yourself) principles across the AIAS codebase.

## Mandatory Steps Before Writing Code

### 1. Search for Existing Implementations

Before creating any new class, method, or function:

- [ ] Search `app/Repositories/` for existing repository methods that handle this operation
- [ ] Search `app/Services/` for existing service classes
- [ ] Search `app/Traits/` for shared behavior
- [ ] Search `app/Filters/` for existing filter classes
- [ ] Check if a `BaseRepository` method already handles this CRUD operation

### 2. Inject Dependencies — Never Duplicate

```php
// ❌ NEVER - Duplicating logic from another repository
AuditEngagement::create(['title' => $data['title']]);

// ✅ ALWAYS - Inject and reuse the repository
public function __construct(protected AuditEngagementRepository $engagementRepository) {}
$this->engagementRepository->createEngagement($data);
```

### 3. Tenant Filtering — Use Repository Methods

```php
// ❌ NEVER - Duplicating tenant filtering in controllers or services
$engagements = AuditEngagement::where('tenant_id', auth()->user()->tenant_id)->get();

// ✅ ALWAYS - Use repository browse method which handles tenant filtering
$engagements = $this->engagementRepository->browseEngagements($filters, $page, $perPage);
```

### 4. Cross-Repository Dependency Injection

When one feature needs another feature's logic, inject the repository:

```php
// ❌ NEVER - Duplicating finding creation logic in engagement repository
class AuditEngagementRepository extends BaseRepository
{
    public function createEngagementWithFindings(array $data): AuditEngagement
    {
        $engagement = $this->createEngagement($data);
        // Don't duplicate Finding creation logic here
        Finding::create(['engagement_id' => $engagement->id, ...]);
    }
}

// ✅ ALWAYS - Inject FindingRepository
class AuditEngagementRepository extends BaseRepository
{
    public function __construct(
        protected FindingRepository $findingRepository
    ) {}

    public function createEngagementWithFindings(array $data): AuditEngagement
    {
        $engagement = $this->createEngagement($data);
        $this->findingRepository->createFinding([
            'engagement_id' => $engagement->id,
            ...
        ]);
        return $engagement;
    }
}
```

### 5. Verification Before Submitting

Before completing any code change:

- [ ] No logic has been duplicated from another file
- [ ] All database operations go through repositories (No direct model queries in controllers, services, or other layers)
- [ ] Tenant filtering is handled by repository `browse*` methods
- [ ] Existing base class methods (`insert`, `update`, `delete`, `read`) are used where applicable
- [ ] No direct `Model::create()` or `Model::query()` calls in controllers, services, or repositories without checking for existing methods first
- [ ] All new logic is added to the appropriate repository, service, or filter class instead of being duplicated in multiple places
- [ ] All new validation logic is added to Form Request classes instead of being duplicated in controllers
- [ ] Ensure all files are correctly namespaced and follow the project directory conventions: Central-specific code belongs in Central directories (`app/Models/Central/`, `app/Repositories/Central/`, `database/migrations/central/`), Tenant-specific code belongs in Tenant directories (`app/Models/Tenant/`, `app/Repositories/Tenant/`, `database/migrations/tenant/`), and shared logic should be placed in reusable locations such as `app/Traits/` or `app/Repositories/BaseRepository.php`.
- [ ] All new tests are PEST tests and follow the same DRY principles, reusing existing test setup and helper methods where possible instead of duplicating test logic.

## Common Violations to Watch For

1. **Copy-pasting controller patterns** without checking if a shared base controller exists
2. **Duplicating filter logic** instead of reusing existing filter classes
3. **Creating new query scopes** that duplicate repository filtering
4. **Inline validation** instead of using Form Request classes
5. **Direct model queries in controllers, services, or repositories** instead of using repository methods
6. **Manual tenant filtering** outside of repository methods
