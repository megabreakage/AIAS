---
applyTo: '**'
---

# Pre-Execution DRY (Don't Repeat Yourself) Principles — AIAS

**MANDATORY: Complete this checklist before writing ANY new function, method, or class.**

This hook ensures GitHub Copilot thoroughly checks for existing implementations that can be reused through dependency injection, preventing code duplication and maintaining a single source of truth in the **AIAS (Adaptive Intelligent Audit System)** application.

---

## Enforcement Rules

### MANDATORY PRE-CODE-GENERATION CHECKLIST

#### 1. Repository Method Check

- [ ] Search for existing repository methods that perform similar operations
- [ ] Check `app/Repositories/BaseRepository.php` for generic CRUD methods (`insert`, `update`, `delete`, `read`, `browse`)
- [ ] Search all repositories in `app/Repositories/` for domain-specific methods
- [ ] If a repository method exists, inject the repository and reuse it

**CORRECT:**

```php
class AuditEngagementRepository extends BaseRepository
{
    public function __construct(
        protected FindingRepository $findingRepository  // ✅ Inject existing repository
    ) {}

    public function createEngagementWithFindings(array $data, array $findings): AuditEngagement
    {
        $engagement = $this->createAuditEngagement($data);

        foreach ($findings as $findingData) {
            // ✅ Reuse FindingRepository::createFinding()
            $this->findingRepository->createFinding(
                array_merge($findingData, ['audit_engagement_id' => $engagement->id])
            );
        }

        return $engagement;
    }
}
```

**INCORRECT:**

```php
class AuditEngagementRepository extends BaseRepository
{
    public function createEngagementWithFindings(array $data, array $findings): AuditEngagement
    {
        $engagement = $this->createAuditEngagement($data);

        foreach ($findings as $findingData) {
            // ❌ NEVER — duplicates FindingRepository logic
            Finding::create(array_merge($findingData, ['audit_engagement_id' => $engagement->id]));
        }

        return $engagement;
    }
}
```

---

#### 2. Service Class Check

- [ ] Search `app/Services/` for existing service classes
- [ ] Check if the business logic you need already exists (e.g., `AuditRiskService`, `FindingStatusService`)
- [ ] Inject the service rather than duplicating logic

---

#### 3. Helper Function Check

- [ ] Search `app/Helpers/` for utility functions
- [ ] Check Laravel's built-in helpers before creating custom ones
- [ ] Review global helper files for reusable utilities

---

#### 4. Trait Check

- [ ] Search for existing traits in `app/Traits/` or `app/Models/Concerns/`
- [ ] Check if shared behavior already exists (e.g., `HasTenantRelationship`, `CentralConnection`, `TenantConnection`)
- [ ] Reuse traits instead of duplicating methods across models

---

#### 5. Middleware Check

- [ ] Review `app/Http/Middleware/` for existing middleware
- [ ] Check `bootstrap/app.php` for registered middleware
- [ ] Reuse `SetSpatieTeamFromTenant`, `InitializeTenancyByPath` rather than creating duplicates

---

#### 6. Policy Check

- [ ] Search `app/Policies/` for similar authorization logic
- [ ] Check if permission checks already exist (e.g., `FindingPolicy`, `AuditEngagementPolicy`)
- [ ] Extend or reuse existing policy methods
- [ ] Use `$user->hasPermissionTo()` — **never** `$user->can()` (Spatie permission pattern)

---

#### 7. Validation Check

- [ ] Search `app/Http/Requests/` for similar validation rules
- [ ] Check if a parent request class can be extended
- [ ] Reuse validation rule arrays where possible

---

#### 8. Filter / Query Scope Check

- [ ] Search `app/Filters/` for existing filter classes (extends `EloquentFilter`)
- [ ] Check model scopes in relevant models
- [ ] Reuse filter logic instead of duplicating query builders

---

## Critical DRY Violations — NEVER Do These

### 1. Duplicating finding creation logic

```php
// ❌ BAD — duplicates FindingRepository logic
Finding::create([
    'title' => $data['title'],
    'description' => $data['description'],
    'tenant_id' => $data['tenant_id'],
]);

// ✅ GOOD — inject and use FindingRepository
$this->findingRepository->createFinding([
    'title' => $data['title'],
    'description' => $data['description'],
]);
```

---

### 2. Duplicating tenant filtering in queries

```php
// ❌ BAD — hardcoded tenant filter in controllers or multiple repos
$findings = Finding::where('tenant_id', auth()->user()->tenant_id)->get();

// ✅ GOOD — use repository method that applies tenant filtering internally
$findings = $this->findingRepository->browseFindings($filters, $page, $perPage);
```

---

### 3. Duplicating risk score calculation

```php
// ❌ BAD — same calculation in multiple places
$riskScore = $likelihood * $impact;

// ✅ GOOD — service method
$riskScore = $this->auditRiskService->calculateRiskScore($likelihood, $impact);
```

---

### 4. Duplicating model relationships

```php
// ❌ BAD — defining the same relationship differently across models
public function tenant(): BelongsTo
{
    return $this->belongsTo(Tenant::class, 'tenant_uuid', 'uuid');
}

// ✅ GOOD — use trait for common relationships
use HasTenantRelationship;
```

---

### 5. Copying validation rules across requests

```php
// ❌ BAD — duplicated rules in CreateFindingRequest and UpdateFindingRequest
$rules = ['title' => 'required|string|max:255', 'risk_level' => 'required|in:low,medium,high,critical'];

// ✅ GOOD — define once, share via base request or helper method
protected function findingRules(): array
{
    return ['title' => 'required|string|max:255', 'risk_level' => 'required|in:low,medium,high,critical'];
}
```

---

### 6. Adding `Auditable` to tenant models

```php
// ❌ BAD — tenant models MUST NOT use Auditable
class Finding extends Model implements AuditableContract
{
    use Auditable;  // NEVER on tenant models
}

// ✅ GOOD — Auditable only on central models (User, Tenant)
class User extends Authenticatable implements AuditableContract
{
    use Auditable;
}
```

---

### 7. Creating FK constraints from tenant tables to central DB

```php
// ❌ BAD — FK constraint across databases will break
Schema::create('audit_engagements', function (Blueprint $table) {
    $table->foreignId('created_by')->constrained('users');  // NEVER — users is in central DB
});

// ✅ GOOD — store central IDs as plain fields
Schema::create('audit_engagements', function (Blueprint $table) {
    $table->unsignedBigInteger('created_by')->nullable();  // plain field, no FK
});
```

---

### 8. Using PK `id` for repository lookups

```php
// ❌ BAD — looking up by auto-increment PK id
$finding = Finding::find($id);
$finding = $this->findingRepository->read($id);

// ✅ GOOD — always look up by UUID identifier
$finding = Finding::where('identifier', $id)->firstOrFail();
// Or via repository method:
$finding = $this->findingRepository->readFinding($id);  // internally uses identifier
```

---

### 9. Using `$user->can()` instead of `hasPermissionTo()`

```php
// ❌ BAD — Gates use can() but AIAS uses Spatie permissions
if ($user->can('create-findings')) { ... }

// ✅ GOOD — Spatie permission check
if ($user->hasPermissionTo('create-findings')) { ... }
```

---

## Dependency Injection Best Practices

### Constructor Injection

```php
class FindingRepository extends BaseRepository
{
    public function __construct(
        protected WorkingPaperRepository $workingPaperRepository,
        protected AuditRiskService $auditRiskService,
    ) {}
}
```

### Inject Repositories, Not Models

```php
// ❌ BAD — direct model usage
public function attachEvidence(string $findingId, array $data): void
{
    WorkingPaper::create(['finding_id' => $findingId, ...$data]);
}

// ✅ GOOD — inject repository
public function __construct(protected WorkingPaperRepository $workingPaperRepository) {}

public function attachEvidence(string $findingId, array $data): void
{
    $this->workingPaperRepository->createWorkingPaper(['finding_id' => $findingId, ...$data]);
}
```

---

## Search Strategies Before Coding

```bash
# Search for similar functionality
grep -r "createFinding" app/Repositories/
grep -r "calculateRisk" app/Services/
grep -r "attachEvidence" app/

# Check for existing patterns
grep -r "public function create" app/Repositories/
grep -r "public function browse" app/Repositories/

# AIAS-specific patterns
grep -r "FindingRepository" app/
grep -r "AuditEngagement" app/Repositories/
grep -r "WorkingPaper" app/Repositories/
grep -r "hasPermissionTo" app/Policies/
grep -r "identifier" app/Repositories/
```

---

## Code Review Questions (Ask Before Submitting)

1. **Does this logic already exist somewhere?**
   - YES → Inject and reuse it ✅
   - NO → Proceed

2. **Could this logic be useful in multiple places?**
   - YES → Create as service/repository method ✅
   - NO → Keep it local, but make it reusable

3. **Am I copying code from another file?**
   - YES → STOP — extract to shared location ❌
   - NO → Proceed

4. **Does this violate single responsibility?**
   - YES → Extract to service/repository ❌
   - NO → Proceed

5. **Can I inject a class instead of calling it statically?**
   - YES → Use dependency injection ✅
   - NO → Only use static if truly stateless

6. **Does this tenant model use `Auditable`?**
   - YES → REMOVE IT — only `User` and `Tenant` use `Auditable` ❌
   - NO → Proceed

7. **Does this tenant migration have a FK to the central DB?**
   - YES → REMOVE IT — use plain `unsignedBigInteger` fields ❌
   - NO → Proceed

8. **Is this a lookup by `id` (PK) instead of `identifier` (UUID)?**
   - YES → Change to `where('identifier', $id)->firstOrFail()` ❌
   - NO → Proceed

---

## Central vs Tenant Model Rules

| Rule | Central Models | Tenant Models |
|------|---------------|---------------|
| Connection trait | `CentralConnection` | `TenantConnection` |
| Auditing | `Auditable` ✅ | `Auditable` ❌ NEVER |
| Migration directory | `database/migrations/` | `database/migrations/tenant/` |
| `tenant_id` field | ❌ Not applicable | ✅ Required |
| FK to central DB | Allowed | ❌ NEVER — plain integer field |
| `$connection` | `'central'` | Handled by `TenantConnection` |

---

## Common Violations to Watch For

1. **Copy-pasting controller patterns** without checking if a shared base controller exists
2. **Duplicating filter logic** instead of reusing existing `EloquentFilter` subclasses
3. **Creating new query scopes** that duplicate repository filtering
4. **Inline validation** instead of using Form Request classes
5. **Direct model queries** in controllers, services, or repositories instead of using repository methods
6. **Manual tenant filtering** outside of repository `browse*` methods
7. **Using `can()` instead of `hasPermissionTo()`** in policies
8. **Lookup by PK `id`** instead of UUID `identifier` in repositories
