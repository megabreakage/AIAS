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
- See [CLAUDE.md](../../CLAUDE.md) for transaction patterns and architecture

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
