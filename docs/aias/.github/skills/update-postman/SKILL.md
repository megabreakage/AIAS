---
name: update-postman
description: >
  Generate or update the AIAS Postman collection and environment variable files using Python.
  Use when: adding a new API module/resource, updating existing endpoints, regenerating the
  collection from scratch, syncing environment variables, running "update postman",
  "regenerate collection", "add postman endpoints", "update postman env", or "sync postman".
argument-hint: '[module name or "all"] [--env local|dev|staging|production]'
---

# AIAS — Update Postman Collection

Generates `postman/collections/AIAS_APIS.postman_collection.json` and four environment files
under `postman/environments/` from the canonical MODULES registry in
[`scripts/generate.py`](./scripts/generate.py).

## When to Use

- A new controller/resource was added to `routes/api.php`
- Extra actions (restore, approve, reject, sign-off …) were added to an existing resource
- Environment variables changed (new IDs, secrets, base URLs)
- Full regeneration after a large refactor or new domain area

## Project-Specific Conventions

| Convention | Detail |
|---|---|
| Project | AIAS — Adaptive Intelligent Audit System |
| Framework | Laravel 13, PHP 8.4+ |
| Auth | Laravel Passport — password grant at `/oauth/token` |
| Guard | `api` (Bearer token only) |
| Tenancy | `InitializeTenancyByPath` / `InitializeTenancyFromUser` middleware |
| Response envelope | `{ status, message, data, metadata? }` |
| IDs | UUID strings, stored as `{{resource_identifier}}` env vars |
| Soft deletes | All models; `restore` action at `POST /{id}/restore` |
| Pagination | `?page=&per_page=&search=&sort_by=&sort_order=` |
| Testing | Pest v3, `docs/scripts/test.sh`, `RefreshDatabaseWithTenancy` trait |
| DB naming | `aias_tenant_<uuid>_db` (tenant), `aias_test_<ts>_<pid>` (test) |

## Procedure

### 1 — Identify What Changed

Read `routes/api.php` to find new or modified route groups. Each `Route::prefix(...)` block
maps to one entry in `MODULES` inside `scripts/generate.py`.

Classify each route:

| Route type | Handling |
|---|---|
| No tenancy middleware | `scope: "central"` (Users, Tenants, Audit Standards, etc.) |
| `InitializeTenancyFromUser` / path tenant | `scope: "tenant"` |
| Extra action (restore/approve/sign-off/…) | Add to `extra_actions` list |
| Non-CRUD endpoint (stats, export, generate-number) | Add to `extra_actions` with correct HTTP method |

### 2 — Update MODULES in generate.py

Open [scripts/generate.py](./scripts/generate.py) and add or edit the relevant entry in `MODULES`.

**Standard CRUD module template:**

```python
{
    "name": "Audit Standards",         # Folder display name (plural)
    "route": "audit-standards",        # URL segment after /api/
    "param": "auditStandard",          # Route param name (camelCase matches Laravel)
    "scope": "central",                # "central" | "tenant"
    "description": "Manage global audit standards (ISA, IIA, GAAS, PCAOB).",
    "sample_body": {
        "name": "ISA 315",
        "code": "ISA-315",
        "description": "Identifying and Assessing Risks of Material Misstatement",
        "status": True,
    },
    "extra_actions": [
        {"method": "POST", "path": "restore", "name": "Restore Audit Standard"},
    ],
},
```

**Module with workflow/approval extra actions:**

```python
{
    "name": "Audit Engagements",
    "route": "audit-engagements",
    "param": "auditEngagement",
    "scope": "tenant",
    "description": "Manage audit projects/engagements lifecycle.",
    "sample_body": { ... },
    "extra_actions": [
        {"method": "POST", "path": "restore",       "name": "Restore Engagement"},
        {"method": "POST", "path": "approve",       "name": "Approve Engagement"},
        {"method": "POST", "path": "close",         "name": "Close Engagement"},
        {"method": "GET",  "path": "summary",       "name": "Engagement Summary",   "no_id": True},
        {"method": "GET",  "path": "stats/status",  "name": "Status Statistics",    "no_id": True},
    ],
},
```

Set `"no_id": True` on actions that don't take a resource ID (collection-level actions).

Set `"skip_crud": True` to suppress standard CRUD requests (Index, Show, Create, Update, Delete)
and keep only `extra_actions` — useful for settings-only or action-only resources.

### 3 — Run the Generator

```bash
# Full regeneration (all environments) from project root
python3 docs/prompts/aias/.github/skills/update-postman/scripts/generate.py

# Custom output directory (when generating inside actual AIAS project)
python3 docs/prompts/aias/.github/skills/update-postman/scripts/generate.py \
    --base-url http://localhost:8000/api \
    --output postman/collections/AIAS_APIS.postman_collection.json

# Inspect a single folder post-generation
python3 docs/prompts/aias/.github/skills/update-postman/scripts/generate.py \
    --extract-folder "Audit Engagements"

# Regenerate env files only
python3 docs/prompts/aias/.github/skills/update-postman/scripts/generate.py --env-only local
```

Output files (relative to AIAS project root):

| File | Purpose |
|---|---|
| `postman/collections/AIAS_APIS.postman_collection.json` | Main importable collection |
| `postman/environments/AIAS_Local.postman_environment.json` | Local dev |
| `postman/environments/AIAS_Development.postman_environment.json` | Dev server |
| `postman/environments/AIAS_Staging.postman_environment.json` | Staging |
| `postman/environments/AIAS_Production.postman_environment.json` | Production |

When running from the MatterMiner repo (scaffold context), output paths are prefixed with
`docs/prompts/aias/` automatically.

### 4 — Verify with jq Summary

The script prints a folder/request summary automatically. Spot-check:

```
──────────────────────────────────────────────────────────
  Collection  : AIAS API
  Folders     : 32
  Requests    : 195
──────────────────────────────────────────────────────────
  Auth                          4    Tenants                    6
  Audit Standards               6    Regulation Frameworks      6
  Risk Categories               6    Users                     10
  Audit Engagements            10    Findings                   9
  Workpapers                    8    ...
──────────────────────────────────────────────────────────
```

If counts look wrong, run:

```bash
python3 docs/prompts/aias/.github/skills/update-postman/scripts/generate.py \
    --extract-folder "Audit Engagements"
```

### 5 — Update Postman Documentation (if new module)

After regenerating, update:

- `postman/COLLECTION_GUIDE.md` — add new folder to the structure table
- `postman/QUICK_REFERENCE.md` — add new env var rows for new resource IDs
- `postman/ENVIRONMENT_VARIABLES.md` — document new `{{resource_identifier}}` variables

## Completion Checklist

- [ ] `MODULES` updated in `generate.py`
- [ ] Generator ran without errors
- [ ] jq summary shows correct folder + request counts
- [ ] New `{{resource_identifier}}` env var added to `RESOURCE_ID_VARS` and all four environment files
- [ ] `postman/COLLECTION_GUIDE.md` updated (if new module)
- [ ] `postman/QUICK_REFERENCE.md` updated (if new env var)

## Environment Variable Naming

Resource ID variables follow: `{{<resource_param_snake>_id}}`

Examples:

| Resource | Env var |
|---|---|
| Audit Engagement | `{{audit_engagement_id}}` |
| Audit Plan | `{{audit_plan_id}}` |
| Finding | `{{finding_id}}` |
| Workpaper | `{{workpaper_id}}` |
| Client | `{{client_id}}` |
| Audit Standard | `{{audit_standard_id}}` |

The login test script auto-populates `access_token`, `refresh_token`, `user_id`, `tenant_id`.

## AIAS Domain Module Reference

| Domain | Modules |
|---|---|
| **Global Reference** | Continents, Countries, Audit Standards, Regulation Frameworks, Risk Categories |
| **IAM** | Users, Roles, Service Users, API Keys, MFA |
| **Tenant Reference** | Departments, Groups, Clients |
| **Core Audit** | Audit Engagements, Audit Plans, Audit Procedures, Workpapers, Findings, Finding Responses |
| **Risk** | Risk Assessments, Risk Matrices |
| **Compliance** | Compliance Requirements, Compliance Evidence |
| **Controls** | Control Objectives, Control Tests |
| **Templates** | Audit Templates |
| **Reports** | Reports |
| **Settings** | Firm Settings |

## Troubleshooting

| Problem | Fix |
|---|---|
| `jq` ImportError | `pip3 install jq` (optional — summary only) |
| `python` not found | Use `python3` on macOS |
| Missing folder in output | Check `MODULES` entry has correct `"route"` and `"name"` |
| 401 on tenant route | Ensure `scope: "tenant"` and `InitializeTenancyFromUser` middleware header present |
| Duplicate `{{param_id}}` | Rename `param` in `MODULES` to match Laravel route model binding name |
| Python 3.9 type error | `from __future__ import annotations` is already included at top of script |
