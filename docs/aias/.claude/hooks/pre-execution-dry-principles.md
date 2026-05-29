# Pre-Execution DRY (Don't Repeat Yourself) Principles Hook — AIAS

## Purpose

This hook ensures that before writing any new code, the Claude AI Agent thoroughly checks for existing implementations that can be reused through dependency injection, preventing code duplication and maintaining a single source of truth in the **AIAS (Adaptive Intelligent Audit System)** application.

## Enforcement Rules

### MANDATORY PRE-CODE-GENERATION CHECKLIST

Before writing **ANY** new function, method, or class, you **MUST** complete this checklist:

#### 1. Repository Method Check

- [ ] Search for existing repository methods that perform similar operations
- [ ] Check `app/Repositories/BaseRepository.php` for generic CRUD methods
- [ ] Search all repositories in `app/Repositories/` for domain-specific methods
- [ ] If a repository method exists, inject the repository and reuse it

**Example — CORRECT:**

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

**Example — INCORRECT:**

```php
class AuditEngagementRepository extends BaseRepository
{
    public function createEngagementWithFindings(array $data, array $findings): AuditEngagement
    {
        $engagement = $this->createAuditEngagement($data);

        foreach ($findings as $findingData) {
            // ❌ NEVER do this — duplicates FindingRepository logic
            Finding::create(array_merge($findingData, ['audit_engagement_id' => $engagement->id]));
        }

        return $engagement;
    }
}
```

#### 2. Service Class Check

- [ ] Search `app/Services/` for existing service classes
- [ ] Check if the business logic you need already exists (e.g., `AuditRiskService`, `FindingStatusService`)
- [ ] Inject the service rather than duplicating logic

#### 3. Helper Function Check

- [ ] Search `app/Helpers/` for utility functions
- [ ] Check Laravel's built-in helpers before creating custom ones
- [ ] Review global helper files for reusable utilities

#### 4. Trait Check

- [ ] Search for existing traits in `app/Traits/` or `app/Models/Concerns/`
- [ ] Check if shared behavior already exists in a trait (e.g., `HasTenantRelationship`)
- [ ] Reuse traits instead of duplicating methods across models

#### 5. Middleware Check

- [ ] Review `app/Http/Middleware/` for existing middleware
- [ ] Check `bootstrap/app.php` for registered middleware
- [ ] Reuse `SetSpatieTeamFromTenant`, `InitializeTenancyByPath` rather than creating duplicates

#### 6. Policy Check

- [ ] Search `app/Policies/` for similar authorization logic
- [ ] Check if permission checks already exist (e.g., `FindingPolicy`, `AuditEngagementPolicy`)
- [ ] Extend or reuse existing policy methods

#### 7. Validation Check

- [ ] Search `app/Http/Requests/` for similar validation rules
- [ ] Check if a parent request class can be extended
- [ ] Reuse validation rule arrays when possible

#### 8. Filter/Query Scope Check

- [ ] Search `app/Filters/` for existing filter classes
- [ ] Check model scopes in relevant models
- [ ] Reuse filter logic instead of duplicating query builders

---

## Critical DRY Violations to Avoid

### ❌ NEVER Do These

#### 1. Duplicating finding creation logic

```php
// ❌ BAD — Every repository creates findings differently
Finding::create([
    'title' => $data['title'],
    'description' => $data['description'],
    'tenant_id' => $data['tenant_id'],
    ...
]);

// ✅ GOOD — Inject and use FindingRepository
$this->findingRepository->createFinding([
    'title' => $data['title'],
    'description' => $data['description'],
    ...
]);
```

#### 2. Duplicating tenant filtering in queries

```php
// ❌ BAD — Hardcoded tenant filter in controllers or multiple repos
$findings = Finding::where('tenant_id', auth()->user()->tenant_id)->get();

// ✅ GOOD — Use repository method that applies tenant filtering internally
$findings = $this->findingRepository->browseFindings($filters, $page, $perPage);
```

#### 3. Duplicating risk score calculation

```php
// ❌ BAD — Same calculation in multiple places
$riskScore = $likelihood * $impact;

// ✅ GOOD — Service method
$riskScore = $this->auditRiskService->calculateRiskScore($likelihood, $impact);
```

#### 4. Duplicating model relationships

```php
// ❌ BAD — Defining same relationship differently across models
public function tenant() { return $this->belongsTo(Tenant::class, 'tenant_uuid', 'uuid'); }

// ✅ GOOD — Use trait for common relationships
use HasTenantRelationship;
```

#### 5. Copying validation rules across requests

```php
// ❌ BAD — Duplicated validation rules in CreateFindingRequest and UpdateFindingRequest
$rules = ['title' => 'required|string|max:255', 'risk_level' => 'required|in:low,medium,high,critical'];

// ✅ GOOD — Define once, share via base request or trait
protected function findingRules(): array {
    return ['title' => 'required|string|max:255', 'risk_level' => 'required|in:low,medium,high,critical'];
}
```

#### 6. Adding `Auditable` to tenant models

```php
// ❌ BAD — Tenant models must NOT use Auditable
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

#### 7. Creating FK constraints from tenant tables to central DB

```php
// ❌ BAD — FK constraint across databases will break
Schema::create('audit_engagements', function (Blueprint $table) {
    $table->foreignId('created_by')->constrained('users');  // NEVER — users is in central DB
});

// ✅ GOOD — Store central IDs as plain fields
Schema::create('audit_engagements', function (Blueprint $table) {
    $table->unsignedBigInteger('created_by')->nullable();  // Plain field, no FK
});
```

---

## Dependency Injection Best Practices

### ✅ Always Use Constructor Injection

```php
class FindingRepository extends BaseRepository
{
    public function __construct(
        protected WorkingPaperRepository $workingPaperRepository,
        protected AuditRiskService $auditRiskService,
    ) {}
}
```

### ✅ Inject Repositories, Not Models

```php
// ❌ BAD — Direct model usage
public function attachEvidence(string $findingId, array $data): void
{
    WorkingPaper::create(['finding_id' => $findingId, ...$data]);
}

// ✅ GOOD — Inject repository
public function __construct(protected WorkingPaperRepository $workingPaperRepository) {}

public function attachEvidence(string $findingId, array $data): void
{
    $this->workingPaperRepository->createWorkingPaper(['finding_id' => $findingId, ...$data]);
}
```

---

## Search Strategies Before Coding

### 1. Search by Functionality

```bash
# Search for existing implementations
grep -r "createFinding" app/Repositories/
grep -r "calculateRisk" app/Services/
grep -r "attachEvidence" app/

# Check for engagement patterns
grep -r "AuditEngagement" app/Repositories/
grep -r "WorkingPaper" app/Repositories/
```

### 2. Search by Pattern

```bash
# Find similar patterns
grep -r "public function create" app/Repositories/
grep -r "protected function validate" app/Http/Requests/
grep -r "FindingStatus" app/
grep -r "RiskLevel" app/
```

### 3. Review Similar Features

- If implementing `WorkingPaper`, review `Finding` implementation first
- If implementing `Risk`, review `Control` implementation first
- If implementing `AuditEngagement`, review `Department` for tenant patterns
- Copy architectural patterns; inject dependencies for shared logic

---

## Code Review Questions (Ask Yourself)

Before submitting ANY code, answer these questions:

1. **Does this logic already exist somewhere?**
   - If YES → Inject and reuse it
   - If NO → Proceed with implementation

2. **Could this logic be useful in other places?**
   - If YES → Create a service/repository method
   - If NO → Keep it local

3. **Am I copying code from another file?**
   - If YES → STOP! Refactor to inject the dependency
   - If NO → Proceed

4. **Does this violate single responsibility?**
   - If YES → Extract to service/repository
   - If NO → Proceed

5. **Can I inject a class instead of calling it statically?**
   - If YES → Use dependency injection
   - If NO → Only use static if truly stateless

6. **Does this tenant model use `Auditable`?**
   - If YES → REMOVE IT. Only central models (`User`, `Tenant`) use `Auditable`
   - If NO → Proceed

7. **Does this migration add an FK from a tenant table to a central table?**
   - If YES → REMOVE IT. Store central IDs as plain `unsignedBigInteger` fields
   - If NO → Proceed

---

## Refactoring Trigger Points

If you find yourself:

- Copying more than 3 lines of code → Create reusable method
- Implementing same logic twice → Extract to service
- Using same validation rules → Extract to reusable rule set
- Querying same data pattern → Use repository method
- Performing same audit calculation → Create service method

---

## Project-Specific DRY Patterns for AIAS

### 1. Audit Finding Management

**ALWAYS use `FindingRepository` for finding operations**

```php
// Inject in constructor
public function __construct(protected FindingRepository $findingRepository) {}

// Reuse for all finding creation
$this->findingRepository->createFinding([...]);

// Reuse for all finding updates
$this->findingRepository->updateFinding($id, [...]);
```

### 2. Tenant Filtering

**NEVER duplicate tenant filtering logic**

```php
// ❌ BAD — Duplicated in every repository
$query->where('tenant_id', auth()->user()->tenant_id);

// ✅ GOOD — Implemented once in each repository's browse method
if (!auth()->user()->hasRole('super-admin')) {
    $query->where('tenant_id', auth()->user()->tenant_id);
}
```

### 3. UUID / ID Generation

**Use model `creating` boot method — define once**

```php
// ❌ BAD — Manual UUID generation scattered everywhere
$model->identifier = Str::uuid();

// ✅ GOOD — In model boot method
protected static function booted(): void
{
    static::creating(function (self $model): void {
        if (empty($model->id)) {
            $model->id = (string) Str::uuid();
        }
    });
}
```

### 4. Finding Status Transitions

**Use `FindingStatus` enum and centralise transition logic in `FindingRepository`**

```php
// ❌ BAD — Status logic scattered
$finding->status = 'closed';

// ✅ GOOD — Enum + repository method
$this->findingRepository->closeFinding($id, $closureData);
```

### 5. Risk Level Calculation

**Use `AuditRiskService` for risk calculations**

```php
// ❌ BAD — Inline calculation
$score = $likelihood * $impact;
$level = $score >= 16 ? 'critical' : ($score >= 9 ? 'high' : 'medium');

// ✅ GOOD — Service method
$riskLevel = $this->auditRiskService->calculateRiskLevel($likelihood, $impact);
```

---

## Verification Steps

Before marking any task as complete:

1. ✅ Run search commands to verify no duplication exists
2. ✅ Confirm all dependencies are injected, not instantiated
3. ✅ Verify repository/service methods are reused
4. ✅ Check that no logic is copied from other files
5. ✅ Ensure single source of truth for business logic
6. ✅ Confirm no tenant model uses `Auditable`
7. ✅ Confirm no tenant migration adds FK constraints to central DB tables

---

## FINAL CHECKPOINT

**Before writing ANY new code, ask:**

> "Does this functionality or similar logic already exist in the AIAS codebase that I can inject and reuse?"

If the answer is **YES** or **MAYBE** → Search first, then reuse.
If the answer is **NO** → Proceed with implementation, ensuring it's reusable for future needs.

---

**Remember:** The best code is code you don't have to write because it already exists and can be reused!
