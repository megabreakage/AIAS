---
mode: 'agent'
description: 'Perform a full DRY audit on the current AIAS change set before code generation proceeds. Checks all 8 DRY categories, transaction patterns, and AIAS-specific constraints.'
---

# AIAS DRY Principles Audit Skill

Perform a comprehensive DRY audit before generating or modifying any code in the AIAS codebase. Work through all categories below systematically, reporting findings for each.

---

## Step 1 — Gather Context

Identify the feature or change being requested:

- What domain module is this? (e.g., Finding, AuditEngagement, WorkingPaper, Risk)
- Is this a Central model or Tenant model?
- What operations are needed? (CRUD, search, filter, auth, validation)

---

## Step 2 — Repository Audit

Search for existing repository implementations:

```bash
grep -r "public function create" app/Repositories/
grep -r "public function browse" app/Repositories/
grep -r "public function read" app/Repositories/
grep -r "public function update" app/Repositories/
grep -r "public function delete" app/Repositories/
```

**Report:**
- [ ] Does a repository already exist for this domain?
- [ ] Does `BaseRepository` have a method that covers this operation?
- [ ] Can an existing repository be injected instead of duplicating logic?
- [ ] Is `FindingRepository` involved? (Finding operations must always use it)

---

## Step 3 — Service Class Audit

Search for existing services:

```bash
grep -r "class.*Service" app/Services/
grep -r "calculateRisk\|computeScore\|evaluateRisk" app/Services/
```

**Report:**
- [ ] Does a service exist for the business logic needed?
- [ ] Can `AuditRiskService` or similar be injected?

---

## Step 4 — Trait Audit

Check for shared traits:

```bash
grep -r "trait " app/Traits/
grep -r "use CentralConnection\|use TenantConnection\|use HasTenantRelationship" app/Models/
```

**Report:**
- [ ] Is `CentralConnection` or `TenantConnection` used correctly for this model?
- [ ] Does a relevant trait exist that provides the needed behavior?

---

## Step 5 — Filter Audit

Search for existing filters:

```bash
grep -r "class.*Filter" app/Filters/
grep -r "extends EloquentFilter" app/Filters/
```

**Report:**
- [ ] Does a `SearchTermFilter` already exist for this module?
- [ ] Does an `IsActiveFilter` exist?
- [ ] Can existing filters be reused or composed?

---

## Step 6 — Policy Audit

Check for existing policies:

```bash
grep -r "class.*Policy" app/Policies/
grep -r "hasPermissionTo" app/Policies/
```

**Report:**
- [ ] Does a policy exist for this model?
- [ ] Are permission checks using `hasPermissionTo()` (not `can()`)?

---

## Step 7 — Form Request Audit

Search for existing requests:

```bash
grep -r "class Create.*Request\|class Update.*Request" app/Http/Requests/
```

**Report:**
- [ ] Do `Create{Model}Request` and `Update{Model}Request` exist?
- [ ] Can validation rules be shared via a base method?

---

## Step 8 — AIAS-Specific Constraint Check

Verify each constraint for the proposed change:

| Constraint | Status |
|-----------|--------|
| Tenant model has no `Auditable` trait | ✅ / ❌ |
| Tenant migration has no FK to central DB | ✅ / ❌ |
| Repository lookup uses `identifier` (UUID), not `id` (PK) | ✅ / ❌ |
| Policy uses `hasPermissionTo()`, not `can()` | ✅ / ❌ |
| Central model uses `CentralConnection` trait | ✅ / ❌ |
| Tenant model uses `TenantConnection` trait | ✅ / ❌ |
| Migration in correct directory (`migrations/` vs `migrations/tenant/`) | ✅ / ❌ |
| Tests use Pest v3 `it()` / `uses()` syntax | ✅ / ❌ |
| Tests use `RefreshDatabaseWithTenancy` (not `RefreshDatabase`) | ✅ / ❌ |

---

## Step 9 — Transaction Pattern Check

For any controller method with writes, verify the order:

- [ ] `Gate::authorize()` called **before** `DB::transaction()`
- [ ] `$request->validated()` called **before** `DB::transaction()`
- [ ] `DB::transaction()` closure contains **only** repository calls
- [ ] `Log::info()` calls are **outside** `DB::transaction()`
- [ ] No `DB::beginTransaction()`, `DB::commit()`, or `DB::rollBack()` in the code

---

## Step 10 — Audit Report

Produce a summary:

```
DRY Audit Report — {ModuleName}
================================
Model Type: Central | Tenant

Repository:   REUSE existing / CREATE new / INJECT {RepositoryName}
Services:     REUSE {ServiceName} / NONE needed
Traits:       APPLY {TraitName} / NONE needed
Filters:      REUSE {FilterName} / CREATE {NewFilter}
Policy:       REUSE existing / CREATE new — uses hasPermissionTo() ✅
Requests:     REUSE existing / CREATE new
Constraints:  All pass ✅ | Issues: {list any ❌}
Transactions: Pattern correct ✅ | Issues: {list any ❌}

Verdict: SAFE TO PROCEED | VIOLATIONS FOUND — fix before generating code
```

---

## Instructions for Copilot

1. Run through all 10 steps systematically
2. Use `grep_search` and `file_search` tools to check for existing implementations
3. Report findings clearly — do not skip steps
4. If any **VIOLATION** is found, stop and explain what must be fixed before generating code
5. Only produce the final "SAFE TO PROCEED" verdict when all checks pass
