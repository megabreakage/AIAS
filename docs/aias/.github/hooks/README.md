# GitHub Copilot Hooks — AIAS (Adaptive Intelligent Audit System)

## Overview

This directory contains GitHub Copilot-specific hook files that guide the agent during code generation and task execution for the **AIAS** application.

AIAS is a multi-tenant Laravel 13 API for audit management. Hooks enforce the architectural patterns established in the codebase to maintain consistency, security, and quality.

---

## Directory Structure

```
.github/hooks/
  README.md                                    ← This file
  pre-execution-dry-principles.instructions.md ← DRY enforcement (auto-loaded, applyTo: **)
  transaction-handling.instructions.md         ← Transaction pattern enforcement (auto-loaded)
  QUICK-REFERENCE.md                           ← Fast lookup during development
  scripts/
    pre-execution.sh                           ← Shell validation for test commands
  prompts/
    dry-principles-skill.prompt.md             ← Invokable DRY audit skill
```

---

## Available Hooks

### 1. Pre-Execution DRY Principles

**File:** `pre-execution-dry-principles.instructions.md`
**Type:** Copilot instructions (auto-loaded via `applyTo: **`)

Enforces Don't Repeat Yourself (DRY) principles by requiring the agent to search for and reuse existing code before creating new implementations.

**Key enforcement areas:**

- Repository method reuse (`FindingRepository`, `AuditEngagementRepository`)
- Service class injection (`AuditRiskService`)
- Filter and query scope reuse
- Tenant filtering consistency (repository-only)
- No `Auditable` on tenant models
- No FK constraints from tenant tables to central DB

---

### 2. Transaction Handling

**File:** `transaction-handling.instructions.md`
**Type:** Copilot instructions (auto-loaded via `applyTo: app/Http/Controllers/**`)

Enforces correct `DB::transaction()` closure patterns. Prevents manual transaction management and ensures proper separation of concerns.

**Key enforcement areas:**

- `Gate::authorize()` before transactions
- `$request->validated()` before transactions
- Only repository calls inside transaction closures
- Logging and events outside transactions
- No `DB::beginTransaction / commit / rollBack`

---

### 3. Quick Reference

**File:** `QUICK-REFERENCE.md`
**Type:** Developer reference

Fast lookup for DRY decisions, AIAS-specific patterns, and transaction ordering. Use when needing instant access to correct patterns without reading the full instruction files.

---

### 4. Pre-Execution Shell Script

**File:** `scripts/pre-execution.sh`
**Type:** Shell validation hook

Validates that tests are run via `docs/scripts/test.sh` rather than directly through `php artisan test`, ensuring proper MySQL database isolation per test run.

---

### 5. DRY Principles Skill (Invokable Prompt)

**File:** `prompts/dry-principles-skill.prompt.md`
**Type:** Copilot prompt skill (`.prompt.md`)

An invokable Copilot skill that performs a full DRY audit on the current change set — checking all 8 categories (repositories, services, helpers, traits, middleware, policies, validation, filters) before code generation proceeds.

**Invoke with:** `/dry-principles-skill` or reference in chat.

---

## Hook Behavior in GitHub Copilot

### Auto-Loaded Instructions

Files with `applyTo` frontmatter patterns are **automatically loaded** by GitHub Copilot when working on matching files:

```yaml
---
applyTo: '**'
---
```

This means Copilot will enforce these rules without needing explicit invocation.

### Invokable Prompts

`.prompt.md` files are reusable Copilot tasks. Reference them explicitly:

- In Copilot Chat: `Use the dry-principles-skill prompt`
- Via VS Code: Command Palette → `GitHub Copilot: Run Prompt`

---

## AIAS Architectural Patterns Enforced

| Pattern | Description | Key Class |
|---------|-------------|-----------|
| **Repository Pattern** | All DB queries through repositories | `BaseRepository`, `FindingRepository` |
| **Dependency Injection** | Constructor injection over static calls | All repositories/services |
| **Transaction Pattern** | `DB::transaction()` closures for all writes | All controllers |
| **Finding Management** | Always use `FindingRepository` | `FindingRepository` |
| **Tenant Isolation** | Automatic tenant filtering in repositories | All tenant repositories |
| **DRY Principle** | Reuse over duplication | All layers |
| **Authorization Flow** | `Gate::authorize()` before transactions | All controllers |
| **No Auditing on Tenant Models** | Only `User` and `Tenant` use `Auditable` | Central models only |
| **No FK to Central DB** | Tenant tables store central IDs as plain fields | All tenant migrations |
| **Identifier Lookup** | Always `where('identifier', $id)->firstOrFail()` | All repositories |
| **Central vs Tenant Split** | Central: `CentralConnection`; Tenant: `TenantConnection` | All models |

---

## Domain Model Reference

### Central Models (`central` DB)

| Model | Key Traits |
|-------|-----------|
| `User` | `HasApiTokens`, `HasRoles`, `Auditable`, `CentralConnection` |
| `Tenant` | `SoftDeletes`, `Auditable`, `CentralConnection` |
| `Continent` | Reference data, `CentralConnection` |
| `Country` | Reference data, `CentralConnection` |

### Tenant Models (`aias_tenant_{id}_db`)

| Model | Notes |
|-------|-------|
| `AuditEngagement` | Core record; injects `FindingRepository` |
| `Finding` | Managed exclusively via `FindingRepository` |
| `WorkingPaper` | Evidence; managed via `WorkingPaperRepository` |
| `Risk` | Risk register |
| `Control` | Internal controls |
| `Department` | Org unit within a tenant |
| `Group` | Grouping/category construct |

---

## Maintaining Hooks

Update these hooks when:

- New architectural patterns are established
- Common anti-patterns are found in code reviews
- New reusable components are added (services, repositories, traits)
- New AIAS domain entities are introduced
- Team coding standards evolve

---

## Related Documentation

- `copilot-instructions.md` — Global AIAS Copilot agent guide
- `architectural-patterns.md` — Full code pattern templates
- `CLAUDE.md` — Claude AI dev guidance (parallel agent)
- `AGENTS.md` — GitHub Copilot + Laravel Boost rules
- `GEMINI.md` — Gemini AI agent guide
- `references.md` — Real code examples for all layers
