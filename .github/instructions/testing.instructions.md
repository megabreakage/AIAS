---
description: "Use when creating or editing tests, running test suites, writing assertions, or debugging test failures. Covers PEST, parallel testing, MySQL isolation, and tenant-aware testing."
applyTo: "tests/**"
---
# Testing Standards

## Framework
- PEST v4 + PHPUnit v12 on MySQL (not SQLite)
- **ALWAYS use `./test.sh`** — never raw `php artisan test` or `composer test`
- `./test.sh` creates a unique MySQL database per run — prevents lock issues and ensures isolation

## Test Class Requirements
- All tests use `RefreshDatabaseWithTenancy` trait — **NEVER** `RefreshDatabase`
- Use factories for model creation — check factory states before manual setup
- Use `fake()` for faker calls (project convention)

## Structure
- Feature tests: `tests/Feature/` — most tests go here
- Unit tests: `tests/Unit/` — pure logic, no DB
- Create: `php artisan make:test --pest {Name}` (no directory prefix)

## Running
```bash
./test.sh                                        # All tests (REQUIRED)
./test.sh tests/Feature/SomeTest.php             # Specific file
./test.sh --filter=testName                      # Specific test
./test.sh --parallel                             # Parallel (requires paratest)
```

## Tenant-Aware Test Setup
- Create tenant resources inside `$this->tenant->run(fn)` context
- Set Spatie team before role assignment in `setUp()`:
  ```php
  setPermissionsTeamId($this->tenant->id);
  $this->user->assignRole('admin');
  ```

## Required Test Coverage
- Happy paths (successful operation)
- Failure paths (validation errors, not found)
- Authorization (403 for wrong roles, wrong tenant)
- Tenant isolation — assert cross-tenant data is **never** leaked
- Soft deletes and restore operations
- Filter combinations (search, status, pagination)
