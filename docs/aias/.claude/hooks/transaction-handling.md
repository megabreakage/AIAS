# Database Transaction Handling — AIAS (Adaptive Intelligent Audit System)

**CRITICAL: This document defines the ONLY acceptable pattern for database transactions in the AIAS codebase.**

## Core Principle

Use `DB::transaction(function () { ... })` closures for ALL database write operations. Laravel automatically handles rollback on exceptions. **Never use manual `DB::beginTransaction`, `DB::commit`, or `DB::rollBack`.**

---

## Mandatory Rules

### 1. Authorization BEFORE Transactions

```php
// ✅ CORRECT
Gate::authorize('create', Finding::class);
$finding = DB::transaction(function () use ($data) {
    return $this->findingRepository->createFinding($data);
});

// ❌ WRONG — Authorization inside transaction holds database locks
DB::transaction(function () use ($data) {
    Gate::authorize('create', Finding::class);
    return $this->findingRepository->createFinding($data);
});
```

**Why**: Authorization checks may fail and should not hold database locks. `Gate::authorize()` is an external operation that must run before entering the transaction.

---

### 2. Validation BEFORE Transactions

```php
// ✅ CORRECT
$data = $request->validated();  // Validated outside transaction
$finding = DB::transaction(function () use ($data) {
    return $this->findingRepository->createFinding($data);
});

// ❌ WRONG — Validation inside transaction
DB::transaction(function () use ($request) {
    $data = $request->validated();  // Wrong place!
    return $this->findingRepository->createFinding($data);
});
```

**Why**: Form request validation may fail and is an external operation. Prepare and validate all data before entering the transaction.

---

### 3. Wrap ONLY Repository / Database Calls

```php
// ✅ CORRECT
$finding = DB::transaction(function () use ($data) {
    return $this->findingRepository->createFinding($data);
});

// ❌ WRONG — Multiple concerns inside transaction
DB::transaction(function () use ($data) {
    $this->notificationService->notifyAuditor($data);        // External service!
    $finding = $this->findingRepository->createFinding($data);
    $this->eventBus->publish(new FindingCreated($finding));  // External event!
    return $finding;
});
```

**Why**: Transactions should ONLY contain database writes. External services, API calls, notifications, and events belong outside transactions.

---

### 4. Logging OUTSIDE Transactions

```php
// ✅ CORRECT
Log::info('Creating audit finding', ['engagement_id' => $data['audit_engagement_id']]);
$finding = DB::transaction(function () use ($data) {
    return $this->findingRepository->createFinding($data);
});
Log::info('Audit finding created successfully', ['id' => $finding->id]);

// ❌ WRONG — Logging inside transaction slows lock duration
DB::transaction(function () use ($data) {
    Log::info('Creating finding');      // Inside transaction!
    $finding = $this->findingRepository->createFinding($data);
    Log::info('Finding created');       // Inside transaction!
    return $finding;
});
```

**Why**: Logging is I/O that increases transaction duration, holding locks longer and reducing throughput.

---

### 5. NO Manual Transaction Management

```php
// ❌ NEVER DO THIS
DB::beginTransaction();
try {
    $finding = $this->findingRepository->createFinding($data);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}

// ✅ ALWAYS DO THIS
$finding = DB::transaction(function () use ($data) {
    return $this->findingRepository->createFinding($data);
});
```

**Why**: Manual transaction management is error-prone. Laravel's closure-based transactions automatically handle commit and rollback.

---

### 6. Let Exceptions Bubble Naturally

```php
// ✅ CORRECT — Exception caught outside, no manual rollback
try {
    $finding = DB::transaction(function () use ($data) {
        return $this->findingRepository->createFinding($data);
    });
} catch (\Throwable $e) {
    // Laravel already rolled back — just handle the error
    Log::error('Failed to create finding', ['error' => $e->getMessage()]);
}

// ❌ WRONG — Manual rollback inside transaction closure
DB::transaction(function () use ($data) {
    try {
        return $this->findingRepository->createFinding($data);
    } catch (\Exception $e) {
        DB::rollBack();  // Redundant and incorrect!
        throw $e;
    }
});
```

**Why**: Laravel automatically rolls back on any uncaught exception from the transaction closure. Manual rollback is redundant and can cause errors.

---

## Controller Method Templates

### Create (Store)

```php
public function store(CreateFindingRequest $request): JsonResponse
{
    // 1. Authorization BEFORE transaction
    Gate::authorize('create', Finding::class);

    try {
        // 2. Validation and data preparation BEFORE transaction
        $data = $request->validated();

        // 3. Pre-transaction logging (optional)
        Log::info('Creating audit finding', [
            'engagement_id' => $data['audit_engagement_id'],
            'title' => $data['title'],
        ]);

        // 4. Transaction wraps ONLY the repository call
        $finding = DB::transaction(function () use ($data) {
            return $this->findingRepository->createFinding($data);
        });

        // 5. Post-transaction logging
        Log::info('Audit finding created successfully', ['id' => $finding->id]);

        return (new FindingResource($finding))
            ->setMessage('Finding created successfully')
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);

    } catch (\Throwable $e) {
        // 6. Exception handling — NO manual rollback needed
        Log::error('Failed to create audit finding', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to create finding',
            'data' => null,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
```

### Update

```php
public function update(UpdateFindingRequest $request, string $id): JsonResponse
{
    try {
        // 1. Fetch resource BEFORE authorization
        $finding = $this->findingRepository->readFinding($id);

        // 2. Authorization BEFORE transaction
        Gate::authorize('update', $finding);

        // 3. Validation BEFORE transaction
        $data = $request->validated();

        Log::info('Updating audit finding', ['id' => $id]);

        // 4. Transaction wraps ONLY the repository call
        $finding = DB::transaction(function () use ($id, $data) {
            return $this->findingRepository->updateFinding($id, $data);
        });

        Log::info('Audit finding updated successfully', ['id' => $finding->id]);

        return (new FindingResource($finding))
            ->setMessage('Finding updated successfully')
            ->response();

    } catch (\ModelNotFoundException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Finding not found',
        ], Response::HTTP_NOT_FOUND);
    } catch (\Throwable $e) {
        Log::error('Failed to update audit finding', [
            'error' => $e->getMessage(),
            'id' => $id,
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update finding',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
```

### Delete (Destroy)

```php
public function destroy(Request $request, string $id): JsonResponse
{
    try {
        // 1. Fetch resource BEFORE authorization
        $finding = $this->findingRepository->readFinding($id);

        // 2. Authorization BEFORE transaction
        Gate::authorize('delete', $finding);

        Log::info('Deleting audit finding', ['id' => $id]);

        // 3. Transaction wraps ONLY the repository call
        DB::transaction(function () use ($id) {
            $this->findingRepository->deleteFinding($id);
        });

        Log::info('Audit finding deleted successfully', ['id' => $id]);

        return response()->json([
            'status' => 'success',
            'message' => 'Finding deleted successfully',
            'data' => ['id' => $finding->id],
        ], Response::HTTP_OK);

    } catch (\ModelNotFoundException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Finding not found',
        ], Response::HTTP_NOT_FOUND);
    } catch (\Throwable $e) {
        Log::error('Failed to delete audit finding', [
            'error' => $e->getMessage(),
            'id' => $id,
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to delete finding',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
```

### Restore (Soft-Delete Restore)

```php
public function restore(Request $request, string $id): JsonResponse
{
    try {
        $finding = $this->findingRepository->readFinding($id, withTrashed: true);

        Gate::authorize('restore', $finding);

        Log::info('Restoring audit finding', ['id' => $id]);

        $finding = DB::transaction(function () use ($id) {
            return $this->findingRepository->restoreFinding($id);
        });

        Log::info('Audit finding restored successfully', ['id' => $finding->id]);

        return (new FindingResource($finding))
            ->setMessage('Finding restored successfully')
            ->response();

    } catch (\ModelNotFoundException $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Finding not found',
        ], Response::HTTP_NOT_FOUND);
    }
}
```

---

## Execution Flow

### Correct Order of Operations

```
1. Authorization    → Gate::authorize()
2. Validation       → $request->validated()
3. Pre-Log          → Log::info('intent...')   [optional]
4. Transaction      → DB::transaction(fn() => $repo->createFinding($data))
5. Post-Log         → Log::info('success...')
6. Response         → return new FindingResource(...)
```

### Visual Flow

```
Request
  → Gate::authorize()         ← fails here? 403 returned, no DB touched
  → $request->validated()     ← fails here? 422 returned, no DB touched
  → Log::info('intent')
  → DB::transaction() {
       $repo->createFinding()  ← exception here? Laravel auto-rolls back
    }
  → Log::info('success')
  → FindingResource response
```

---

## Common Mistakes to Avoid

### ❌ Authorization Inside Transaction

```php
// WRONG — Holds DB locks during permission check
DB::transaction(function () use ($data) {
    Gate::authorize('create', Finding::class);
    return $this->findingRepository->createFinding($data);
});
```

### ❌ External Services Inside Transaction

```php
// WRONG — Email/notification inside transaction holds locks
DB::transaction(function () use ($data) {
    $finding = $this->findingRepository->createFinding($data);
    $this->mailService->notifyAuditee($finding);  // External I/O!
    return $finding;
});
```

### ❌ Manual Rollback

```php
// WRONG — Laravel handles this automatically
DB::beginTransaction();
try {
    $finding = $this->findingRepository->createFinding($data);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();  // Redundant
    throw $e;
}
```

### ❌ Nested Try-Catch Inside Transaction

```php
// WRONG — Exception handling inside transaction closure
DB::transaction(function () use ($data) {
    try {
        return $this->findingRepository->createFinding($data);
    } catch (\Exception $e) {
        Log::error('Error inside transaction');  // Wrong place!
        throw $e;
    }
});
```

---

## Benefits of This Pattern

1. **Automatic Rollback** — Laravel handles all exception scenarios without manual intervention
2. **Minimal Lock Duration** — Transactions only during actual database writes
3. **Better Performance** — Shorter transactions = less lock contention = higher throughput
4. **Cleaner Code** — Clear separation of authorization, validation, persistence, and logging
5. **Exception Safety** — No data corruption from failed partial commits
6. **Easier Testing** — Each concern (auth, validation, persistence) testable independently
7. **Framework Compliance** — Follows Laravel's recommended patterns

---

## Enforcement

- **Code Review**: All PRs must follow this pattern
- **Linting**: `vendor/bin/pint --dirty` enforces code style
- **Testing**: Use `./test.sh` for MySQL-based tests (NOT `php artisan test` directly)
- **Documentation**: Reference this document when building new AIAS features

---

## Questions?

If you encounter a scenario not covered here:

1. Default to the safest option: transaction wraps ONLY repository calls
2. Ask: "Does this operation touch the database?"
3. If YES → inside transaction
4. If NO → outside transaction
5. Consult this document and `CLAUDE.md`

---

**Last Updated**: 2026-05-16
**Applies To**: All controllers in `app/Http/Controllers/`
