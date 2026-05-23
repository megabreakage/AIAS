# DRY Principles Quick Reference — AIAS

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
grep -r "Finding" app/Repositories/
grep -r "AuditEngagement" app/Repositories/
grep -r "WorkingPaper" app/Repositories/
```

### 2. Check These Locations

| Type | Location | AIAS Example |
|------|----------|--------------|
| Repository Methods | `app/Repositories/` | `FindingRepository::createFinding()` |
| Service Classes | `app/Services/` | `AuditRiskService::calculateRiskScore()` |
| Traits | `app/Traits/` | `HasTenantRelationship` |
| Helper Functions | `app/Helpers/` | `formatRiskLevel()` |
| Middleware | `app/Http/Middleware/` | `SetSpatieTeamFromTenant` |
| Policies | `app/Policies/` | `FindingPolicy::create()` |
| Validation Rules | `app/Http/Requests/` | `CreateFindingRequest` |
| Model Scopes | In models | `AuditEngagement::forTenant()` |

### 3. Decision Tree

```
Need to create something?
├─ Does it already exist?
│  ├─ YES → Inject and reuse it ✅
│  └─ NO → Continue to next question
├─ Will it be used in multiple places?
│  ├─ YES → Create as service/repository method ✅
│  └─ NO → Keep it local, but make it reusable ✅
└─ Am I copying code from another file?
   ├─ YES → STOP! Extract to shared location ❌
   └─ NO → Proceed with implementation ✅
```

## AIAS-Specific Common Patterns

### ✅ Inject FindingRepository in AuditEngagement Context (CORRECT)

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
            // ✅ Reuse FindingRepository — never duplicate finding creation logic
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

### ✅ Inject WorkingPaperRepository for Evidence (CORRECT)

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

        // ✅ Reuse WorkingPaperRepository for evidence attachment
        if (!empty($closureData['evidence'])) {
            $this->workingPaperRepository->attachEvidence($finding->id, $closureData['evidence']);
        }

        return $finding->fresh();
    }
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
    return $this->repository->createFinding($data);
});

// ❌ Manual transaction management
DB::beginTransaction();
try {
    $finding = $this->repository->createFinding($data);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();  // WRONG — Laravel handles this
    throw $e;
}

// ❌ External I/O inside transaction
DB::transaction(function () use ($data) {
    $finding = $this->repository->createFinding($data);
    $this->notificationService->notifyAuditor($finding);  // WRONG
    return $finding;
});
```

---

## Project-Specific Must-Follow

1. **Audit Finding Operations:** ALWAYS use `FindingRepository`
2. **Working Paper/Evidence:** ALWAYS use `WorkingPaperRepository`
3. **Tenant Filtering:** Use repository methods — never add `where('tenant_id', ...)` manually
4. **UUID Generation:** Use model `creating` boot method — define once
5. **Polymorphic Relations:** Follow existing patterns in `WorkingPaper`
6. **No Auditing on Tenant Models:** `AuditEngagement`, `Finding`, `WorkingPaper`, `Risk`, `Control`, `Department`, `Group` must NOT use `Auditable`
7. **No FK to Central DB:** Tenant migrations must never add FK constraints to central tables — store central IDs as plain fields

---

## Red Flags (Code Smells)

🚩 Copying more than 3 lines from another file
🚩 Writing same validation rules twice
🚩 Creating similar methods in multiple repositories
🚩 Direct `Finding::create(...)` instead of `FindingRepository::createFinding()`
🚩 Adding `where('tenant_id', ...)` outside of a repository method
🚩 Hardcoding risk levels or finding statuses instead of using enums
🚩 Adding `Auditable` to a tenant model
🚩 Adding a `foreign()` constraint in a tenant migration referencing the central DB

---

## When in Doubt

Ask yourself: **"Does this already exist somewhere?"**

If YES or MAYBE → Search for 30 seconds → Inject and reuse
If not found → Create it reusably for future features
