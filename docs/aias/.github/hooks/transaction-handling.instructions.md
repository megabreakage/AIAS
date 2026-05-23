---
applyTo: 'app/Http/Controllers/**'
---

# Database Transaction Handling — AIAS (Adaptive Intelligent Audit System)

**CRITICAL: This defines the ONLY acceptable pattern for database transactions in the AIAS codebase.**

---

## Core Principle

Use `DB::transaction(function () { ... })` closures for ALL database write operations. Laravel automatically handles rollback on exceptions.

**Never use manual `DB::beginTransaction`, `DB::commit`, or `DB::rollBack`.**

---

## Mandatory Rules

### Rule 1 — Authorization BEFORE Transactions

```php
// ✅ CORRECT
Gate::authorize('create', Finding::class);
$finding = DB::transaction(function () use ($data) {
    return $this->findingRepository->createFinding($data);
});

// ❌ WRONG — authorization inside transaction holds database locks
DB::transaction(function () use ($data) {
    Gate::authorize('create', Finding::class);
    return $this->findingRepository->createFinding($data);
});
```

**Why**: Authorization checks may fail and are external operations. They must not hold database locks.

---

### Rule 2 — Validation BEFORE Transactions

```php
// ✅ CORRECT
$data = $request->validated();
$finding = DB::transaction(function () use ($data) {
    return $this->findingRepository->createFinding($data);
});

// ❌ WRONG — validation inside transaction
DB::transaction(function () use ($request) {
    $data = $request->validated();
    return $this->findingRepository->createFinding($data);
});
```

**Why**: Form request validation is an external operation. Prepare all data before entering the transaction.

---

### Rule 3 — Wrap ONLY Repository / Database Calls

```php
// ✅ CORRECT
$finding = DB::transaction(function () use ($data) {
    return $this->findingRepository->createFinding($data);
});

// ❌ WRONG — external services inside transaction
DB::transaction(function () use ($data) {
    $this->notificationService->notifyAuditor($data);
    $finding = $this->findingRepository->createFinding($data);
    $this->eventBus->publish(new FindingCreated($finding));
    return $finding;
});
```

**Why**: Transactions should ONLY contain database writes. External services, API calls, and events belong outside.

---

### Rule 4 — Logging OUTSIDE Transactions

```php
// ✅ CORRECT
Log::info('Creating audit finding', ['engagement_id' => $data['audit_engagement_id']]);
$finding = DB::transaction(function () use ($data) {
    return $this->findingRepository->createFinding($data);
});
Log::info('Audit finding created', ['id' => $finding->id]);

// ❌ WRONG — logging inside transaction increases lock duration
DB::transaction(function () use ($data) {
    Log::info('Creating finding');
    $finding = $this->findingRepository->createFinding($data);
    Log::info('Finding created');
    return $finding;
});
```

**Why**: Logging is I/O that increases transaction duration, holding locks longer and reducing throughput.

---

### Rule 5 — NO Manual Transaction Management

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

**Why**: Manual transaction management is error-prone. Laravel's closure-based transactions handle commit and rollback automatically.

---

### Rule 6 — Let Exceptions Bubble Naturally

```php
// ✅ CORRECT — exception caught outside, no manual rollback
try {
    $finding = DB::transaction(function () use ($data) {
        return $this->findingRepository->createFinding($data);
    });
} catch (\Throwable $e) {
    // Laravel already rolled back — just handle the error
    Log::error('Failed to create finding', ['error' => $e->getMessage()]);
}

// ❌ WRONG — manual rollback inside closure
DB::transaction(function () use ($data) {
    try {
        return $this->findingRepository->createFinding($data);
    } catch (\Exception $e) {
        DB::rollBack();  // Redundant and incorrect!
        throw $e;
    }
});
```

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

---

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

---

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

---

### Restore (Soft-Delete Restore)

```php
public function restore(Request $request, string $id): JsonResponse
{
    try {
        // 1. Fetch with trashed BEFORE authorization
        $finding = $this->findingRepository->readFinding($id, withTrashed: true);

        // 2. Authorization BEFORE transaction
        Gate::authorize('restore', $finding);

        Log::info('Restoring audit finding', ['id' => $id]);

        // 3. Transaction wraps ONLY the repository call
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

## Correct Execution Order (Visual)

```
Request
  → Gate::authorize()          ← fails? 403 returned, no DB touched
  → $request->validated()      ← fails? 422 returned, no DB touched
  → Log::info('intent...')     [optional]
  → DB::transaction() {
       $repo->operation()      ← exception? Laravel auto-rolls back
    }
  → Log::info('success...')
  → return new Resource(...)
```

| Step | Location | On Failure |
|------|----------|-----------|
| `Gate::authorize()` | Before transaction | Throws `AuthorizationException` → 403 |
| `$request->validated()` | Before transaction | Throws `ValidationException` → 422 |
| `DB::transaction()` | Repository calls only | Throws any `\Throwable` → auto-rollback |
| `Log::info()` | Outside transaction | No DB impact |
| Events / notifications | Outside transaction | No DB impact |

---

## Anti-Pattern Summary

| Anti-Pattern | Correct Pattern |
|-------------|-----------------|
| `Gate::authorize()` inside `DB::transaction()` | Authorize before transaction |
| `$request->validated()` inside `DB::transaction()` | Validate before transaction |
| `Log::info()` inside `DB::transaction()` | Log outside transaction |
| Notification service inside `DB::transaction()` | Dispatch events/notifications after transaction |
| `DB::beginTransaction() / DB::commit() / DB::rollBack()` | Use `DB::transaction(fn() => ...)` |
| Manual `DB::rollBack()` in catch | Let Laravel auto-rollback |
| `Model::create()` directly in controller | Use repository method |
