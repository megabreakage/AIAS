# Claude Code Hooks — AIAS (Adaptive Intelligent Audit System)

## Overview

This directory contains hook files that provide guidance and constraints to the Claude AI Agent during code generation and task execution for the **AIAS** application.

AIAS is a multi-tenant Laravel 13 API for audit management. Hooks enforce the architectural patterns established in the codebase to maintain consistency, security, and quality.

## Available Hooks

### Pre-Execution DRY Principles Hook

**File:** `pre-execution-dry-principles.md`

**Purpose:** Enforces Don't Repeat Yourself (DRY) principles by requiring the agent to search for and reuse existing code before creating new implementations.

**When it runs:** Before any code generation task

**Key enforcement areas:**

- Repository method reuse (especially `FindingRepository` for audit finding operations)
- Service class injection
- Validation rule sharing
- Filter and query scope reuse
- Audit finding management patterns
- Tenant filtering consistency

### Transaction Handling Hook

**File:** `transaction-handling.md`

**Purpose:** Enforces correct database transaction patterns using Laravel's `DB::transaction()` closures. Prevents manual transaction management and ensures proper separation of concerns.

**When it runs:** When creating or modifying controller methods that perform database writes

**Key enforcement areas:**

- Authorization before transactions
- Validation before transactions
- Transaction wraps only repository calls
- Logging outside transactions
- No manual `DB::beginTransaction/commit/rollBack`
- Exception handling without manual rollback

### Quick Reference

**File:** `QUICK-REFERENCE.md`

**Purpose:** Provides quick-access patterns and anti-patterns for DRY principles and transaction handling, using AIAS domain examples.

**When it runs:** Quick reference during development

**Key sections:**

- DRY decision tree
- AIAS-specific code patterns (AuditEngagement, Finding, WorkingPaper)
- Transaction handling patterns
- Quick search commands

### Pre-Execution Shell Hook

**File:** `pre-execution.sh`

**Purpose:** Validates that tests are run via `./test.sh` rather than directly through `php artisan test`, ensuring proper MySQL database isolation.

**When it runs:** Before executing test commands

## How Hooks Work

Claude Code hooks are instruction files that the AI agent must review and follow during specific workflow stages. They act as quality gates and best practice enforcers.

### Using Hooks

1. **Automatic Enforcement:**
   - Claude Code automatically references hooks in `.claude/hooks/` directory
   - The agent must acknowledge and follow hook instructions

2. **Manual Reference:**
   - Reference hooks in your prompts: "Follow the DRY principles hook"
   - Include hook content in context when needed

## Hook Development Guidelines

When creating new hooks:

1. **Be Specific:** Provide concrete examples of correct and incorrect patterns
2. **Be Enforceable:** Include checkable criteria and verification steps
3. **Be Actionable:** Give clear search commands and decision trees
4. **Be Project-Specific:** Align with actual AIAS codebase patterns

## AIAS-Specific Architectural Patterns That Hooks Enforce

| Pattern | Description | Key Class |
|---------|-------------|-----------|
| **Repository Pattern** | All database queries through repositories | `BaseRepository`, `FindingRepository` |
| **Dependency Injection** | Constructor injection over static calls | All repositories / services |
| **Single Responsibility** | One responsibility per class/method | All layers |
| **Transaction Pattern** | `DB::transaction()` closures for all writes | All controllers |
| **Audit Finding Management** | Always use `FindingRepository` for findings | `FindingRepository` |
| **Tenant Isolation** | Automatic tenant filtering in repositories | All tenant repositories |
| **DRY Principle** | Reuse over duplication | All code |
| **Authorization Flow** | `Gate::authorize()` before transactions | All controllers |
| **No Auditing on Tenant Models** | Only `User` and `Tenant` use `Auditable` | Central models only |
| **No FK to Central DB** | Tenant tables store central IDs as plain fields | All tenant migrations |

## Domain Model Overview

### Central Models (central DB)

- `User` — Auth, roles/permissions, `HasApiTokens`, `HasRoles`, `Auditable`
- `Tenant` — Tenant lifecycle, soft deletes, `Auditable`
- `Continent` — Reference data (example central read-only resource)

### Tenant Models (per-tenant DB — `aias_tenant_{id}_db`)

- `AuditEngagement` — Core engagement record, injects `FindingRepository`
- `Finding` — Audit findings; managed exclusively via `FindingRepository`
- `WorkingPaper` — Evidence and working papers attached to findings
- `Risk` — Risk register entries
- `Control` — Internal controls
- `Department` — Organizational unit within a tenant
- `Group` — Grouping/category construct

## Maintaining Hooks

Hooks should be updated when:

- New architectural patterns are established
- Common anti-patterns are discovered in code reviews
- New reusable components are added (services, repositories, traits)
- Team coding standards evolve
- New AIAS domain entities are introduced

## Related Documentation

- `CLAUDE.md` — Primary dev guidance
- `AGENTS.md` — GitHub Copilot + Laravel Boost rules
- `architectural-patterns.md` — Full code pattern templates
- `references.md` — Real code examples for all layers
- `GEMINI.md` — Gemini AI agent guide
- `WARP.md` — WARP terminal dev guide
