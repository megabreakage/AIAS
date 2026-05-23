---
description: "Use when writing, reviewing, or refactoring any code in this workspace. Enforces SOLID, DRY, Clean Architecture, and production-ready engineering conventions across Laravel/PHP, React/TypeScript, Node.js, and Kotlin."
applyTo: "**/*.{php,ts,tsx,js,jsx,kt}"
---
# Engineering Standards

## Principles (All Languages)
- SOLID, DRY, Clean Architecture — no exceptions
- Modular, testable, TDD-friendly design
- Reusable services, repositories, DTOs, actions, components
- RESTful + event-driven patterns
- Performance, security, scalability, observability first

## PHP/Laravel
- Repository pattern for all DB operations — never query models in controllers
- Form Requests for validation, Policies for authorization
- `DB::transaction(fn() => ...)` closures — never manual begin/commit/rollback
- Gate checks BEFORE transactions
- Logging AFTER transactions
- Run `vendor/bin/pint --dirty` before finalizing any PHP changes
- Run `composer analyse` to enforce repository-only boundaries in production layers
- See [CLAUDE.md](../../CLAUDE.md) for transaction patterns and architecture

## Layer Boundaries (Enforced)
- Production layers (`app/Http/Controllers`, `app/Jobs`, `app/Services`) MUST use repositories for all record access and mutations
- Direct model queries in production layers are **FORBIDDEN**: `Model::query()`, `Model::find()`, `Model::where()`, `Model::create()`, `Model::update()`, `Model::delete()`
- Privileged scripts (`tests/`, `database/factories/`, `database/seeders/`) may use Eloquent directly
- Enforce this boundary with `composer analyse` (PHPStan/Larastan) in CI

## Code Quality
- Explicit return types on all methods
- PHP 8 constructor property promotion
- Curly braces on all control structures (even single-line)
- PHPDoc blocks over inline comments
- No `dd()` — use `Log::debug()` for debugging
- Eloquent over raw `DB::` queries
- No `env()` outside config files — use `config()` instead
- Production-ready code only — no debugging artifacts

## TypeScript/React
- Functional components + hooks only
- Modular component-driven architecture
- TypeScript strict mode — no `any` unless truly unavoidable
- Shadcn UI + Tailwind CSS for styling
- Responsive, accessible, performant UI/UX

## Kotlin/Android
- Jetpack Compose + MVI (Model-View-Intent)
- Offline-first where appropriate
- Secure mobile-first architecture
- Coroutines for async, Flow for reactive streams

## Security (All Stacks)
- OWASP Top 10 compliance
- Input validation at system boundaries
- Parameterized queries, no raw SQL concatenation
- JWT/OAuth for API auth, biometric for mobile
- RBAC enforcement — never trust client-side checks

## Communication
- Remove filler words. No 'a', 'the', 'is', 'am', 'are'
- Direct answers only. Short 3-6 word sentences
- Run tools first, show result, stop. No narration
