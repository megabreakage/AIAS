---
description: "Use when writing, reviewing, or refactoring any code in this workspace. Enforces SOLID, DRY, Clean Architecture, and production-ready engineering conventions across Laravel/PHP, React/TypeScript, Node.js, and Kotlin."
applyTo: "**/*.{php,ts,tsx,js,jsx,kt}"
---
# Engineering Standards

## Principles (All Languages)
- SOLID, DRY, Clean Architecture — no exceptions
- Modular, testable, TDD-friendly design
- Reusable repositories, services, DTOs, actions, components
- RESTful + event-driven patterns
- Performance, security, scalability, observability first

## Architecture Overview
- **Repository Pattern**: All DB operations through repositories — never query models in controllers
- **Resource Pattern**: Consistent API responses via Laravel Resources (`BaseResource`)
- **Policy-Based Authorization**: Gate-driven permission checks — `Gate::authorize()` before any DB work
- **Form Request Validation**: Dedicated validation classes — no inline `$request->validate()`
- **Filter Pattern**: Composable `EloquentFilter` query filters
- **DRY Principle**: Search for existing implementations before creating new code; inject repositories instead of duplicating logic

## Layer Boundaries (Strictly Enforced)
- **FORBIDDEN** in production layers (`app/Http/Controllers`, `app/Jobs`, `app/Services`): `Model::query()`, `Model::find()`, `Model::where()`, `Model::create()`, `Model::update()`, `Model::delete()`
- `tests/`, `database/factories/`, `database/seeders/` may use Eloquent directly
- Verify boundaries: `composer analyse` (PHPStan/Larastan)

## Transaction Pattern (Critical)

```php
// ✅ CORRECT
Gate::authorize('create', Model::class);       // 1. Authorization BEFORE transaction
$data = $request->validated();                 // 2. Validation BEFORE transaction
Log::info('Creating...', [...]);               // 3. Pre-tx logging (optional)
$record = DB::transaction(fn() =>             // 4. Transaction wraps ONLY repository call
    $this->repository->createRecord($data)
);
Log::info('Created', ['id' => $record->id]);  // 5. Logging AFTER transaction
```

```php
// ❌ NEVER
DB::beginTransaction();          // manual begin/commit/rollback
Gate::authorize(...);            // authorization inside transaction
Log::info(...);                  // logging inside transaction
DB::transaction(fn() => {
    Gate::authorize(...);        // WRONG
    $this->repository->...();
});
```

## PHP/Laravel Code Quality
- PHP 8 constructor property promotion: `public function __construct(protected Repo $repo) {}`
- Explicit return type declarations on all methods
- Curly braces on all control structures — even single-line bodies
- PHPDoc blocks over inline comments; array shape types in PHPDoc
- TitleCase for Enum keys: `Active`, `InProgress`, `Closed`
- No `dd()` — use `Log::debug()`
- No `env()` outside config files — use `config()`
- Eloquent over raw `DB::` query builder
- `vendor/bin/pint --dirty` before finalizing any PHP changes

## API Response Envelope (Strictly Default)
All responses use `BaseResource` — **never** deviate from this envelope:
- `status` — HTTP status code
- `message` — human-readable message
- `data` — resource payload
- `metadata` — optional context

```php
return (new SomeResource($record))
    ->setMessage('Record retrieved successfully')
    ->addMetadata('count', $count)
    ->response()
    ->setStatusCode(Response::HTTP_OK);
```

## Database — Central vs Tenant
| Aspect | Central | Tenant |
|---|---|---|
| Connection | `protected $connection = 'central'` | `TenantConnection` trait (no explicit connection) |
| Auditing | ✅ `Auditable` trait | ❌ Never — cross-DB complexity |
| `tenant_id` | ❌ Not scoped | ✅ Required — plain string field, no FK |
| Migration location | `database/migrations/` | `database/migrations/tenant/` |
| Repository filtering | Role/permission-based | Mandatory tenant filter for non-super-admin |

## Security
- OWASP Top 10 compliance
- Input validation at system boundaries via Form Requests
- Parameterized queries — no raw SQL string concatenation
- JWT/OAuth (Passport) for API auth, biometric for mobile
- RBAC enforcement — `Gate::authorize()` and Policies always; never trust client-side checks
- No FK constraints from tenant tables to central database tables

## TypeScript/React
- Functional components + hooks only
- TypeScript strict mode — no `any` unless truly unavoidable
- Shadcn UI + Tailwind CSS for styling
- Responsive, accessible, performant UI

## Kotlin/Android
- Jetpack Compose + MVI (Model-View-Intent)
- Coroutines for async, Flow for reactive streams
- Offline-first where appropriate

## Communication
- Remove filler words. No 'a', 'the', 'is', 'am', 'are'
- Direct answers only. Short 3-6 word sentences
- Run tools first, show result, stop. No narration
