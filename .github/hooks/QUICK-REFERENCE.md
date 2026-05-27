# DRY Principles Quick Reference — AIAS (GitHub Copilot)

## Before Writing ANY New Code

### 1. Search First (30 seconds)

```bash
# Search for similar functionality
grep -r "methodName" app/Repositories/
grep -r "ServiceName" app/Services/
grep -r "similarFunction" app/

# Check for existing patterns
grep -r "public function create" app/Repositories/
grep -r "public function browse" app/Repositories/

# AIAS-specific: check finding and engagement patterns
grep -r "FindingRepository" app/
grep -r "AuditEngagement" app/Repositories/
grep -r "WorkingPaper" app/Repositories/
grep -r "hasPermissionTo" app/Policies/
grep -r "identifier" app/Repositories/
```

---

### 2. Check These Locations First

| Type | Location | AIAS Example |
|------|----------|--------------|
| Repository Methods | `app/Repositories/` | `FindingRepository::createFinding()` |
| Service Classes | `app/Services/` | `AuditRiskService::calculateRiskScore()` |
| Traits | `app/Traits/` | `HasTenantRelationship`, `CentralConnection`, `TenantConnection` |
| Helper Functions | `app/Helpers/` | `formatRiskLevel()` |
| Middleware | `app/Http/Middleware/` | `SetSpatieTeamFromTenant`, `InitializeTenancyByPath` |
| Policies | `app/Policies/` | `FindingPolicy::create()` |
| Validation Rules | `app/Http/Requests/` | `CreateFindingRequest` |
| Filters | `app/Filters/` | `FindingFilters`, `SearchTermFilter` |

---

### 3. Decision Tree

```
Need to create something?
├─ Does it already exist?
│  ├─ YES → Inject and reuse it ✅
│  └─ NO → Continue
├─ Will it be used in multiple places?
│  ├─ YES → Create as service/repository method ✅
│  └─ NO → Keep local, but make it reusable
└─ Am I copying code from another file?
   ├─ YES → STOP! Extract to shared location ❌
   └─ NO → Proceed with implementation ✅
```

---

## AIAS-Specific Common Patterns

### ✅ Inject FindingRepository (CORRECT)

```php
class AuditEngagementRepository extends BaseRepository
{
    public function __construct(
        protected FindingRepository $findingRepository
    ) {}

    public function createEngagementWithFindings(array $data, array $findings): AuditEngagement
    {
        $engagement = $this->createAuditEngagement($data);

        foreach ($findings as $findingData) {
            // ✅ Reuse FindingRepository
            $this->findingRepository->createFinding(
                array_merge($findingData, ['audit_engagement_id' => $engagement->id])
            );
        }

        return $engagement;
    }
}
```

### ❌ Direct Model Creation (WRONG)

```php
foreach ($findings as $findingData) {
    // ❌ NEVER — duplicates FindingRepository logic
    Finding::create(array_merge($findingData, ['audit_engagement_id' => $engagement->id]));
}
```

---

### ✅ Inject WorkingPaperRepository (CORRECT)

```php
class FindingRepository extends BaseRepository
{
    public function __construct(
        protected WorkingPaperRepository $workingPaperRepository
    ) {}

    public function closeFinding(string $id, array $closureData): Finding
    {
        $finding = $this->readFinding($id);
        $finding->update(['status' => FindingStatus::Closed, ...$closureData]);

        if (!empty($closureData['evidence'])) {
            // ✅ Reuse WorkingPaperRepository
            $this->workingPaperRepository->attachEvidence($finding->id, $closureData['evidence']);
        }

        return $finding->fresh();
    }
}
```

---

### ✅ Repository Identifier Lookup (CORRECT)

```php
// ✅ ALWAYS use identifier (UUID), never PK id
public function readFinding(string $id, bool $withTrashed = false): Finding
{
    $query = Finding::query();

    if ($withTrashed) {
        $query->withTrashed();
    }

    return $query->where('identifier', $id)->firstOrFail();
}
```

---

### ✅ Spatie Permission Check (CORRECT)

```php
// In policies — ALWAYS use hasPermissionTo()
public function create(User $user): bool
{
    return $user->hasPermissionTo('create-findings');
}

// ❌ NEVER use can() in AIAS policies
public function create(User $user): bool
{
    return $user->can('create-findings');  // WRONG
}
```

---

## Transaction Handling Quick Reference

### ✅ Correct Order

```
1. Gate::authorize()          ← BEFORE transaction
2. $request->validated()      ← BEFORE transaction
3. Log::info('intent')        ← BEFORE transaction (optional)
4. DB::transaction(fn() =>    ← ONLY repository calls inside
     $repo->createFinding($data)
   )
5. Log::info('success')       ← AFTER transaction
6. return new FindingResource  ← Response
```

### ❌ Never Do These

```php
// ❌ Authorization inside transaction
DB::transaction(function () use ($data) {
    Gate::authorize('create', Finding::class);  // WRONG
    return $this->findingRepository->createFinding($data);
});

// ❌ Manual transaction management
DB::beginTransaction();
try {
    $finding = $this->findingRepository->createFinding($data);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

---

## Model Type Quick Reference

| Rule | Central | Tenant |
|------|---------|--------|
| Connection | `CentralConnection` | `TenantConnection` |
| Auditing | `Auditable` ✅ | `Auditable` ❌ |
| Migration dir | `database/migrations/` | `database/migrations/tenant/` |
| `tenant_id` | ❌ | ✅ required |
| FK to central DB | allowed | ❌ plain integer only |

---

## Testing Quick Reference

```bash
# Always use test.sh
./test.sh                                              # All tests
./test.sh tests/Feature/FindingTest.php               # Specific file
./test.sh --filter=test_can_create_finding            # Specific test
./test.sh --parallel                                   # Parallel

# NEVER use artisan test directly
php artisan test   # ❌ No DB isolation
```

### Pest v3 Test Structure

```php
uses(TestCase::class, RefreshDatabaseWithTenancy::class)->in(__FILE__);

it('can create a finding', function () {
    // Arrange
    $tenant = Tenant::factory()->create();
    $user = User::factory()->for($tenant)->create();

    // Act
    $response = $this->actingAs($user, 'api')
        ->postJson("/api/{$tenant->id}/findings", [...]);

    // Assert
    $response->assertStatus(201);
});
```

---

## Checklist Before Submitting

- [ ] No logic duplicated from another file
- [ ] All DB operations go through repositories
- [ ] Tenant filtering handled by repository `browse*` methods
- [ ] `BaseRepository` base methods used where applicable
- [ ] No direct `Model::create()` or `Model::query()` in controllers
- [ ] New validation in Form Request classes only
- [ ] Files correctly namespaced per Central/Tenant directory convention
- [ ] All tests are Pest v3 (`it()` / `uses()`)
- [ ] Tenant models have NO `Auditable` trait
- [ ] Tenant migrations have NO FK constraints to central DB tables
- [ ] Repository lookups use `identifier` (UUID), not `id` (PK)
- [ ] Policies use `hasPermissionTo()`, not `can()`
