---
description: "Use when creating or editing tests, running test suites, writing assertions, or debugging test failures. Covers PEST, parallel testing, MySQL isolation, and tenant-aware testing."
applyTo: "tests/**"
---
# Testing Standards

## Framework
- PEST v4 + PHPUnit v12 on MySQL (not SQLite)
- Parallel testing: `composer test` (4 processes)
- Sequential: `composer test:seq`

## Test Class Requirements
- All tests use `RefreshDatabaseWithTenancy` trait — NEVER `RefreshDatabase`
- Use factories for model creation — check factory states before manual setup
- Use `fake()` for faker calls (project convention)

## Structure
- Feature tests: `tests/Feature/` — most tests go here
- Unit tests: `tests/Unit/` — pure logic, no DB
- Create: `php artisan make:test --pest {Name}` (no directory prefix)

## Patterns
- Assert JSON structure, status codes, database state
- Test authorization (forbidden for wrong roles/tenants)
- Test validation (missing fields, invalid data)
- Test soft deletes and restore operations
- Test filter combinations (search, status, pagination)

## Running
```bash
composer test                                    # All parallel
composer test:seq                                # Sequential
php artisan test --compact --filter=testName     # Single test
```
