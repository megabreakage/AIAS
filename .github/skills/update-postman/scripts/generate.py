#!/usr/bin/env python3
"""
AIAS Postman Collection Generator — v3
========================================
Generates a Postman Collection v3 YAML directory and four environment JSON files
for the AIAS (Adaptive Intelligent Audit System) — a Laravel 13 multi-tenant API
for audit engagements, compliance tracking, risk assessments, and audit workflows.

Output format:  Postman Collection Schema v3.0.0 (multi-file YAML directory)
Runtime:        Postman CLI  (postman collection run …)
Note:           Newman cannot run v3 collections; use the Postman CLI instead.

Auth:     Laravel Passport — personal access tokens via /api/v1/auth/login
Guard:    api  (Bearer token)
Tenancy:  Stancl/Tenancy v3 — InitializeTenancyByBodyParam (tenant_id in body/query)

Usage:
    # From AIAS project root (default output: postman/collections/AIAS_APIS/):
    python3 .github/skills/update-postman/scripts/generate.py

    # Custom output directory:
    python3 .github/skills/update-postman/scripts/generate.py \\
        --output postman/collections/AIAS_APIS

    # Inspect a single folder after generation:
    python3 .github/skills/update-postman/scripts/generate.py \\
        --extract-folder "Audit Engagements"

Dependencies:
    pip3 install PyYAML          (required for YAML output)
    pip3 install jq              (optional — for post-generation summary only)
"""

from __future__ import annotations

import argparse
import json
import re
import shutil
import sys
from pathlib import Path
from typing import Any, Optional

try:
    import yaml
except ModuleNotFoundError as _e:
    print("ERROR: PyYAML is required.  Install with: pip3 install PyYAML", file=sys.stderr)
    raise SystemExit(1) from _e

# ---------------------------------------------------------------------------
# CONFIG
# ---------------------------------------------------------------------------

COLLECTION_NAME = "AIAS API"
COLLECTION_DESCRIPTION = (
    "# AIAS — Adaptive Intelligent Audit System\n\n"
    "A multi-tenant Laravel 13 REST API for quality management, audit workflows, compliance tracking, "
    "risk assessments, and findings management.\n\n"
    "**Version:** 1.0 | **Framework:** Laravel 13, PHP 8.4 | "
    "**Auth:** Laravel Passport (Personal Access Tokens) | **Tenancy:** Stancl Tenancy v3\n\n"
    "---\n\n"
    "## ✅ Currently Implemented Endpoints\n\n"
    "**Central API** (`{{base_url}}/v1/...`)\n"
    "- Auth: login, logout, me\n"
    "- Tenants: CRUD + create tenant user\n"
    "- Users: create (POST only)\n"
    "- Continents: full CRUD + restore\n"
    "- Countries: full CRUD + restore\n\n"
    "**Tenant API** (`{{tenant_base_url}}/v1/...` — requires `tenant_id`)\n"
    "- Auth: register, login, logout, me\n"
    "- Priority Levels: full CRUD + restore\n"
    "- Preambles: full CRUD + restore\n"
    "- Users: full CRUD + restore\n"
    "- Checklist Types: full CRUD + restore\n"
    "- Section Styles: full CRUD + restore\n"
    "- Checklists: full CRUD + restore\n"
    "- Companies: full CRUD + restore\n"
    "- Departments: full CRUD + restore\n"
    "- Audits: full CRUD + restore\n\n"
    "**⚠️ Planned / Not Yet Implemented:** Roles, Service Users, API Keys, MFA, Audit Standards, "
    "Regulation Frameworks, Risk Categories, Clients, Audit Templates, Audit Engagements, "
    "Audit Plans, Audit Procedures, Workpapers, Findings, Finding Responses, Risk Assessments, "
    "Risk Matrices, Compliance Requirements, Compliance Evidence, Control Objectives, "
    "Control Tests, Reports, Firm Settings.\n\n"
    "---\n\n"
    "## Authentication\n\n"
    "All protected endpoints require a `Bearer` token in the `Authorization` header.\n\n"
    "**Central login:** `POST {{base_url}}/v1/auth/login` — email + password only. "
    "Returns a token for super-admin operations (tenants, users, reference data).\n\n"
    "**Tenant login:** `POST {{tenant_base_url}}/v1/auth/login` — requires `tenant_id` in the JSON body "
    "alongside email and password. Returns a token scoped to that tenant's database.\n\n"
    "After login, `access_token`, `user_id`, and `tenant_id` are auto-saved to the environment "
    "by the test script.\n\n"
    "---\n\n"
    "## Route Prefixes\n\n"
    "| Scope | Base Variable | Example URL |\n"
    "|-------|--------------|------------|\n"
    "| Central | `{{base_url}}` | `{{base_url}}/v1/tenants` |\n"
    "| Tenant | `{{tenant_base_url}}` | `{{tenant_base_url}}/v1/audits` |\n\n"
    "- `base_url` includes `/api` (e.g. `http://localhost:8020/api`) — used for central routes.\n"
    "- `tenant_base_url` is without `/api` (e.g. `http://localhost:8020`) — used for tenant routes.\n\n"
    "---\n\n"
    "## Tenant Identification\n\n"
    "Tenancy is initialised via the `tenant_id` body/query parameter "
    "(`InitializeTenancyByBodyParam` middleware).\n\n"
    "Pass `tenant_id` (identifier string, e.g. `AT.1.1748000000`) in every tenant-scoped request:\n"
    "- **POST / PUT / PATCH** — include `tenant_id` in the JSON body\n"
    "- **GET / DELETE** — include `tenant_id` as a query parameter\n\n"
    "The `{{tenant_id}}` variable is auto-populated after login and stored in the environment.\n\n"
    "---\n\n"
    "## Response Envelope\n\n"
    "All responses follow a consistent `BaseResource` envelope:\n"
    "```json\n"
    "{\n"
    '  "status": "success",\n'
    '  "message": "Resource retrieved successfully",\n'
    '  "data": { ... }\n'
    "}\n"
    "```\n"
    "Paginated list responses include `metadata` with `current_page`, `last_page`, `per_page`, `total`.\n\n"
    "---\n\n"
    "## Pagination\n\n"
    "List endpoints accept: `?page=1&per_page=15&search=&sort_by=created_at&sort_order=desc&show_deleted=0`\n\n"
    "---\n\n"
    "## Soft Deletes\n\n"
    "All resources support soft-delete. Deleted records are hidden by default. "
    "Restore with `POST /{identifier}/restore`. Pass `?show_deleted=1` to include deleted records in lists.\n\n"
    "---\n\n"
    "## ID / Identifier Convention\n\n"
    "Route binding uses the string `identifier` field (format: `AT.{n}.{timestamp}`), **not** the "
    "numeric database `id`. All env vars store identifier strings (e.g. `{{preamble_id}}` = `\"AT.5.1748000000\"`).\n\n"
    "**⚠️ Integer FK Exception:** Some relationship ID fields in request bodies accept numeric integer DB IDs "
    "(not identifier strings). These are explicitly documented in each endpoint's description:\n"
    "- `continent_id` in Country requests — numeric integer\n"
    "- `department_id`, `lead_auditor_id`, `quality_manager_id`, `checklist_id`, `task_type_id` in Audit requests — numeric integer\n"
    "- `quality_controller_id`, `preamble_id`, `checklist_type_id` in Checklist requests — numeric integer\n"
    "- `department_head`, `department_members.*.user_id` in Department requests — numeric integer\n"
    "- `company_contacts.*.user_id` in Company requests — numeric integer\n\n"
    "---\n\n"
    "## Environment Variables\n\n"
    "Key variables auto-populated by test scripts after each Create request:\n"
    "- `access_token` — Bearer token (set after login)\n"
    "- `user_id` — Authenticated user identifier (set after login/me)\n"
    "- `tenant_id` — Current tenant identifier (set after login)\n"
    "- `<resource>_id` — Resource identifier saved after each successful Create (e.g. `preamble_id`, `audit_id`)\n\n"
    "---\n\n"
    "## Tech Stack\n\n"
    "Laravel 13 · PHP 8.4 · MySQL · Passport OAuth2 (Personal Access Tokens) · "
    "Stancl Tenancy v3 (body-param identification) · Spatie Permission v7 · Pest v4"
)
DEFAULT_BASE_URL = "http://localhost:8020/api"
DEFAULT_TENANT_BASE_URL = "http://localhost:8020"

# Output directory (relative to AIAS project root) for the v3 YAML collection.
DEFAULT_OUTPUT = "postman/collections/AIAS_APIS"

# Keep stable so Postman recognises re-imports as updates, not new collections.
STABLE_COLLECTION_ID = "aias-a1b2c3d4-e5f6-7890-abcd-ef1234567890"

# ---------------------------------------------------------------------------
# MODULES
# ---------------------------------------------------------------------------
# Each entry describes one API resource folder.
#
# Fields:
#   name          — Human-readable folder name (plural)
#   route         — URL segment after /api/   e.g. "audit-engagements"
#   param         — Laravel route model binding name (camelCase)
#   scope         — "central" (no tenant middleware) | "tenant" (InitializeTenancyFromUser)
#   description   — Folder-level description
#   sample_body   — Dict used for Create / Update request bodies
#   extra_actions — List of dicts for non-CRUD endpoints:
#                   { "method": "POST", "path": "restore", "name": "Restore X" }
#                   Add "no_id": True for collection-level actions (no /{id} prefix)
#   skip_crud     — True to suppress standard CRUD requests (keep only extra_actions)
# ---------------------------------------------------------------------------

MODULES: list[dict[str, Any]] = [
    # ── Auth (special — built by build_auth_folder) ─────────────────────────
    # Handled separately; do not add here.

    # ── Central / IAM ────────────────────────────────────────────────────────
    {
        "name": "Tenants",
        "route": "tenants",
        "param": "tenant",
        "scope": "central",
        "description": (
            "Manage tenants (organisations / audit firms). "
            "Super-admin access only for create and delete operations.\n\n"
            "A tenant represents an isolated audit firm with its own database. "
            "`owner_id` must be the `identifier` string of an existing central user who becomes the firm owner. "
            "`data_center` specifies the cloud region for tenant DB provisioning (e.g. `us-east-1`). "
            "`status` values: `active`, `inactive`, `suspended`, `pending_setup`.\n\n"
            "**Note:** There is no restore endpoint for tenants — deletion is permanent (soft-delete only)."
        ),
        "sample_body": {
            "name": "Pinnacle Audit Partners",
            "owner_id": "{{user_id}}",
            "domain": "pinnacleaudit.aias.app",
            "country_id": 1,
            "data_center": "us-east-1",
            "status": "active",
        },
        "extra_actions": [
            {
                "method": "POST",
                "path": "users",
                "name": "Create Tenant User",
                "description": (
                    "Create a new user account scoped to this tenant and assign them a role.\n\n"
                    "The user is created in the tenant's isolated database. "
                    "`role` is **required** and must be one of: `tenant`, `auditor`, `client`, `viewer`.\n\n"
                    "Required fields: `first_name`, `last_name`, `username` (unique within tenant), "
                    "`email` (unique within tenant), `password` (min 8 chars, letters + numbers), `role`.\n\n"
                    "Optional: `title`, `middle_name`, `country_code`, `phone`, `preferred_timezone`, "
                    "`office_location`, `avatar`, `notes`, `is_active`.\n\n"
                    "On success the user `identifier` is saved to `{{tenant_user_id}}` in the active environment."
                ),
                "body": {
                    "title": "Ms",
                    "first_name": "Jane",
                    "middle_name": "",
                    "last_name": "Auditor",
                    "username": "jane.auditor",
                    "email": "jane.auditor@pinnacleaudit.test",
                    "country_code": "+254",
                    "phone": "0712345678",
                    "password": "Password123!",
                    "preferred_timezone": "Africa/Nairobi",
                    "office_location": "Nairobi, Kenya",
                    "is_active": True,
                    "role": "auditor",
                },
                "save_var": "tenant_user_id",
            },
        ],
    },
    {
        "name": "Continents",
        "route": "continents",
        "param": "continent",
        "scope": "central",
        "description": (
            "Global continent reference data shared across all tenants.\n\n"
            "Continents are top-level geographic groupings used to organise countries. "
            "`short_code` is a 2-character abbreviation (e.g. `AF`). "
            "`iso_code` is the ISO 3166-1 alpha-2 code (e.g. `AF`). "
            "`slug` is a URL-friendly identifier auto-generated from the name if not provided.\n\n"
            "Soft-deleted continents can be restored via `POST /continents/{identifier}/restore`."
        ),
        "sample_body": {
            "name": "Africa",
            "slug": "africa",
            "short_code": "AF",
            "iso_code": "AF",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Continent",
             "description": "Restore a soft-deleted continent record."},
        ],
    },
    {
        "name": "Countries",
        "route": "countries",
        "param": "country",
        "scope": "central",
        "description": (
            "Global country reference data linked to continents.\n\n"
            "`continent_id` is the **numeric integer ID** of the continent record (not the identifier string). "
            "Use the continent's database `id` — not the `identifier` string.\n\n"
            "`short_code` and `iso_code` are 2-character country codes (e.g. `KE`). "
            "`currency` is the 3-letter ISO currency code (e.g. `KES`). "
            "`country_code` is the telephone dialling prefix (e.g. `+254`). "
            "`phone_digits` is the number of local digits after the country code (e.g. `9`).\n\n"
            "Soft-deleted countries can be restored via `POST /countries/{identifier}/restore`."
        ),
        "sample_body": {
            "name": "Kenya",
            "slug": "kenya",
            "continent_id": 1,
            "short_code": "KE",
            "iso_code": "KE",
            "currency": "KES",
            "currency_name": "Kenyan Shilling",
            "currency_sign": "KSh",
            "country_code": "+254",
            "phone_digits": 9,
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Country",
             "description": "Restore a soft-deleted country record."},
        ],
    },
    {
        "name": "Users",
        "route": "users",
        "param": "user",
        "scope": "central",
        "description": (
            "Register central platform users (super-admins and tenant owners).\n\n"
            "**✅ Implemented:** `POST /v1/users` — create a central user.\n"
            "**⚠️ Not yet implemented:** List, Get, Update, Delete central users — "
            "user management is primarily performed through tenant-scoped endpoints.\n\n"
            "Required: `first_name`, `last_name`, `username` (unique globally), `email` (unique globally), "
            "`password` (min 8 chars, must contain letters and numbers).\n\n"
            "Optional: `title`, `middle_name`, `country_code`, `phone`, `preferred_timezone`, "
            "`office_location`, `avatar`, `notes`, `is_active` (boolean, default `true`).\n\n"
            "Note: `password_confirmation` is **not** required. "
            "Role assignment is handled via the tenant user management endpoints. "
            "The `identifier` of the created user is auto-saved to `{{user_id}}` in the environment."
        ),
        "sample_body": {
            "title": "Dr",
            "first_name": "Jane",
            "middle_name": "",
            "last_name": "Auditor",
            "username": "jane.auditor",
            "email": "jane.auditor@pinnacleaudit.test",
            "country_code": "+254",
            "phone": "0712345678",
            "password": "Password123!",
            "preferred_timezone": "Africa/Nairobi",
            "office_location": "Nairobi, Kenya",
            "is_active": True,
        },
        "extra_actions": [],
        "skip_crud": False,
        "unimplemented_crud": ["index", "show", "update", "destroy"],
    },
    {
        "name": "Roles",
        "route": "roles",
        "param": "role",
        "scope": "central",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/api.php`.\n\n"
            "Manage RBAC roles and permission assignments (Spatie Permission). "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "name": "auditor",
            "permissions": ["view audit-engagements", "create workpapers"],
        },
        "extra_actions": [
            {"method": "GET", "path": "permissions", "name": "List All Permissions", "no_id": True},
        ],
    },
    {
        "name": "Service Users",
        "route": "service-users",
        "param": "id",
        "scope": "central",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/api.php`.\n\n"
            "System-level service accounts for machine-to-machine auth. "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "name": "Integration Bot",
            "email": "bot@pinnacleaudit.test",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore",    "name": "Restore Service User"},
            {"method": "POST", "path": "activate",   "name": "Activate Service User"},
            {"method": "POST", "path": "deactivate", "name": "Deactivate Service User"},
            {"method": "GET",  "path": "api-keys",   "name": "Get Service User API Keys"},
        ],
    },
    {
        "name": "API Keys",
        "route": "api-keys",
        "param": "id",
        "scope": "central",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/api.php`.\n\n"
            "Manage API keys for service users. This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "service_user_id": "{{service_user_id}}",
            "name": "Production Key",
            "expires_at": "2027-12-31",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore",    "name": "Restore API Key"},
            {"method": "POST", "path": "regenerate", "name": "Regenerate API Key"},
            {"method": "POST", "path": "rotate",     "name": "Rotate API Key"},
            {"method": "POST", "path": "revoke",     "name": "Revoke API Key"},
        ],
    },
    {
        "name": "MFA",
        "route": "mfa",
        "param": "mfa",
        "scope": "central",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/api.php`.\n\n"
            "Multi-factor authentication management (TOTP-based). This folder is a placeholder for future implementation."
        ),
        "sample_body": {"method": "totp"},
        "extra_actions": [
            {"method": "GET",  "path": "status",       "name": "MFA Status",              "no_id": True},
            {"method": "POST", "path": "setup",        "name": "Setup MFA",               "no_id": True},
            {"method": "POST", "path": "confirm",      "name": "Confirm MFA Setup",       "no_id": True},
            {"method": "POST", "path": "disable",      "name": "Disable MFA",             "no_id": True},
            {"method": "POST", "path": "backup-codes", "name": "Regenerate Backup Codes", "no_id": True},
            {"method": "PUT",  "path": "method",       "name": "Update MFA Method",       "no_id": True},
        ],
        "skip_crud": True,
    },

    # ── Global Audit Reference Data ──────────────────────────────────────────
    {
        "name": "Audit Standards",
        "route": "audit-standards",
        "param": "auditStandard",
        "scope": "central",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/api.php`.\n\n"
            "Global audit standards reference data (ISA, IIA, GAAS, PCAOB). "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "name": "ISA 315",
            "code": "ISA-315",
            "description": "Identifying and Assessing Risks of Material Misstatement",
            "issuing_body": "IAASB",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Audit Standard"},
        ],
    },
    {
        "name": "Regulation Frameworks",
        "route": "regulation-frameworks",
        "param": "regulationFramework",
        "scope": "central",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/api.php`.\n\n"
            "Global regulatory frameworks (SOX, GDPR, HIPAA, Basel III). "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "name": "SOX",
            "code": "SOX",
            "description": "Sarbanes-Oxley Act — US public company accounting reform",
            "jurisdiction": "United States",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Regulation Framework"},
        ],
    },
    {
        "name": "Risk Categories",
        "route": "risk-categories",
        "param": "riskCategory",
        "scope": "central",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/api.php`.\n\n"
            "Global risk category definitions shared across all tenants. "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "name": "Financial Risk",
            "code": "FIN",
            "description": "Risks related to financial reporting and transactions",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Risk Category"},
        ],
    },

    # ── Tenant-Scoped Reference Data ─────────────────────────────────────────
    {
        "name": "Priority Levels",
        "route": "priority-levels",
        "param": "priorityLevel",
        "scope": "tenant",
        "description": (
            "Manage priority level definitions used to classify urgency across audits, findings, and tasks.\n\n"
            "`level` is a required integer ranking (e.g. `1` = highest priority). "
            "`color` is an optional hex colour code for UI display (e.g. `#FF0000`). "
            "`is_active` controls visibility in priority level selection dropdowns.\n\n"
            "Soft-deleted priority levels can be restored via `POST /priority-levels/{identifier}/restore`."
        ),
        "sample_body": {
            "name": "Critical",
            "description": "Immediate action required — business-critical impact.",
            "level": 1,
            "color": "#FF0000",
            "is_active": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Priority Level",
             "description": "Restore a soft-deleted priority level record."},
        ],
    },
    {
        "name": "Preambles",
        "route": "preambles",
        "param": "preamble",
        "scope": "tenant",
        "description": (
            "Manage quality management preamble documents.\n\n"
            "Preambles establish the scope, objectives, and authority declarations for audit quality frameworks. "
            "`status` values: `draft`, `active`, `archived`. "
            "`effective_date` is when the preamble comes into force (YYYY-MM-DD). "
            "`is_featured` pins the preamble to the top of lists.\n\n"
            "Soft-deleted preambles can be restored via `POST /preambles/{identifier}/restore`."
        ),
        "sample_body": {
            "name": "Quality Management Framework Preamble",
            "description": "Establishes the scope and objectives of the quality management framework.",
            "status": "draft",
            "effective_date": "2026-01-01",
            "is_featured": False,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Preamble",
             "description": "Restore a soft-deleted preamble record."},
        ],
    },
    {
        "name": "Checklist Types",
        "route": "checklist-types",
        "param": "checklistType",
        "scope": "tenant",
        "description": (
            "Manage checklist type definitions used across audit engagements.\n\n"
            "Checklist types categorise the nature of checklists (e.g. Pre-Engagement, Field Work, Closing). "
            "`is_active` controls visibility in checklist selection dropdowns. "
            "`is_featured` pins the type to the top of lists.\n\n"
            "Soft-deleted types can be restored via `POST /checklist-types/{identifier}/restore`."
        ),
        "sample_body": {
            "name": "Pre-Engagement Checklist",
            "description": "Checklist items to complete before commencing an audit engagement.",
            "is_active": True,
            "is_featured": False,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Checklist Type",
             "description": "Restore a soft-deleted checklist type record."},
        ],
    },
    {
        "name": "Section Styles",
        "route": "section-styles",
        "param": "sectionStyle",
        "scope": "tenant",
        "description": (
            "Manage section style definitions that control layout and presentation of checklist sections.\n\n"
            "`columns` defines the number of columns in the section layout (e.g. `1`, `2`, `3`). "
            "`is_active` controls visibility in section style dropdowns. "
            "`is_featured` pins the style to the top of lists.\n\n"
            "Soft-deleted section styles can be restored via `POST /section-styles/{identifier}/restore`."
        ),
        "sample_body": {
            "name": "Two Column Layout",
            "description": "A two-column layout for side-by-side checklist items.",
            "columns": 2,
            "is_active": True,
            "is_featured": False,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Section Style",
             "description": "Restore a soft-deleted section style record."},
        ],
    },
    {
        "name": "Checklists",
        "route": "checklists",
        "param": "checklist",
        "scope": "tenant",
        "description": (
            "Manage audit checklists with quality controller assignment, preamble linkage, and type classification.\n\n"
            "**⚠️ Integer FK fields** (pass numeric DB `id`, not the identifier string):\n"
            "- `quality_controller_id` — DB `id` of the user responsible for quality control.\n"
            "- `preamble_id` — DB `id` of the preamble to link.\n"
            "- `checklist_type_id` — DB `id` of the checklist type classification.\n\n"
            "All three ID fields are nullable — pass `null` to leave unassigned.\n"
            "`is_active` controls visibility in selection dropdowns. "
            "`is_featured` pins the checklist to the top of lists.\n\n"
            "Soft-deleted checklists can be restored via `POST /checklists/{identifier}/restore`."
        ),
        "sample_body": {
            "name": "Q1 Financial Audit Checklist",
            "quality_controller_id": None,
            "preamble_id": None,
            "checklist_type_id": None,
            "is_active": True,
            "is_featured": False,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Checklist",
             "description": "Restore a soft-deleted checklist record."},
        ],
    },
    {
        "name": "Users",
        "route": "users",
        "param": "tenant_user",
        "scope": "tenant",
        "description": (
            "Manage tenant-scoped user accounts within the audit firm.\n\n"
            "Required: `first_name`, `last_name`, `username` (unique within tenant), "
            "`email` (unique within tenant), `password` (min 8 chars, must contain letters and numbers).\n\n"
            "Optional: `title`, `middle_name`, `country_code`, `phone`, `preferred_timezone`, "
            "`office_location`, `avatar`, `notes`, `is_active` (boolean).\n\n"
            "Note: Role assignment is managed separately via `POST /api/v1/tenants/{id}/users` (central endpoint). "
            "The `is_active` field (boolean) controls account access. "
            "The `identifier` of the created user is auto-saved to `{{tenant_user_id}}` in the environment.\n\n"
            "Soft-deleted users can be restored via `POST /users/{identifier}/restore`."
        ),
        "sample_body": {
            "title": "Ms",
            "first_name": "Jane",
            "middle_name": "",
            "last_name": "Auditor",
            "username": "jane.auditor",
            "email": "jane.auditor@example.test",
            "country_code": "+254",
            "phone": "0712345678",
            "password": "Password123!",
            "preferred_timezone": "Africa/Nairobi",
            "office_location": "Nairobi, Kenya",
            "is_active": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore User",
             "description": "Restore a soft-deleted tenant user account."},
        ],
    },
    {
        "name": "Companies",
        "route": "companies",
        "param": "company",
        "scope": "tenant",
        "description": (
            "Manage tenant companies — the client organisations being audited.\n\n"
            "`level_of_operations` values: `local`, `regional`, `international`.\n\n"
            "**⚠️ Integer FK fields** (pass numeric DB `id`, not the identifier string):\n"
            "- `country_id` — numeric DB `id` of the country record (nullable).\n"
            "- `company_contacts.*.user_id` — numeric DB `id` of the contact user (nullable).\n\n"
            "`contact_type` values: `primary`, `secondary`, `billing`, `technical`.\n\n"
            "**⚠️ Logo upload:** The `logo` field accepts a file image upload (`multipart/form-data`, max 2MB). "
            "When sending JSON, omit `logo` or pass `null` (no file). "
            "To upload a logo, switch the request body to `form-data` and include the file.\n\n"
            "`latitude` / `longitude` are decimal geocoding coordinates (optional). "
            "`postal_code` is a string up to 20 chars.\n\n"
            "Soft-deleted companies can be restored via `POST /companies/{identifier}/restore`."
        ),
        "sample_body": {
            "name": "Apex Holdings Ltd",
            "address": "123 Business Park, Westlands",
            "office_location": "Nairobi, Kenya",
            "latitude": -1.2921,
            "longitude": 36.8219,
            "postal_code": "00100",
            "country_id": None,
            "level_of_operations": "local",
            "trading_name": "Apex",
            "website": "https://apexholdings.test",
            "email": "info@apexholdings.test",
            "phone": "+254712345678",
            "description": "Leading investment and holding company.",
            "is_active": True,
            "is_featured": False,
            "company_contacts": [
                {"user_id": None, "contact_type": "primary"},
            ],
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Company",
             "description": "Restore a soft-deleted company record."},
        ],
    },
    {
        "name": "Departments",
        "route": "departments",
        "param": "department",
        "scope": "tenant",
        "description": (
            "Manage tenant departments with geocoding support, member assignments, and department head linkage.\n\n"
            "**⚠️ Integer FK fields** (pass numeric DB `id`, not the identifier string):\n"
            "- `department_head` — numeric DB `id` of the user appointed as department head (nullable).\n"
            "- `department_members.*.user_id` — numeric DB `id` of each member user (nullable).\n"
            "- `country_id` — numeric DB `id` of the country record (nullable).\n\n"
            "`latitude` and `longitude` are decimal geocoding coordinates (optional). "
            "`postal_code` is a string up to 20 chars.\n\n"
            "Soft-deleted departments can be restored via `POST /departments/{identifier}/restore`."
        ),
        "sample_body": {
            "name": "Finance & Accounts",
            "address": "2nd Floor, HQ Tower",
            "office_location": "Nairobi, Kenya",
            "latitude": -1.2921,
            "longitude": 36.8219,
            "postal_code": "00100",
            "country_id": None,
            "department_head": None,
            "description": "Manages all financial operations and reporting.",
            "is_active": True,
            "is_featured": False,
            "department_members": [
                {"user_id": None},
            ],
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Department",
             "description": "Restore a soft-deleted department record."},
        ],
    },
    {
        "name": "Audits",
        "route": "audits",
        "param": "audit",
        "scope": "tenant",
        "description": (
            "Manage tenant audit records — the core audit execution entity.\n\n"
            "`scope` values: `internal`, `external`, `service_provider`, `supplier`. "
            "`audit_start_date` is **required** (format: `YYYY-MM-DD`). "
            "`audit_end_date` must be on or after `audit_start_date`.\n\n"
            "**⚠️ Integer FK fields** (pass numeric DB `id`, not the identifier string):\n"
            "- `checklist_id` — numeric DB `id` of the checklist to link (nullable).\n"
            "- `task_type_id` — numeric DB `id` of the task type (nullable).\n"
            "- `department_id` — numeric DB `id` of the department being audited (nullable).\n"
            "- `lead_auditor_id` — numeric DB `id` of the lead auditor user (nullable).\n"
            "- `quality_manager_id` — numeric DB `id` of the quality manager user (nullable).\n\n"
            "`add_appendix` (boolean) indicates whether to attach an appendix to the audit report. "
            "`is_featured` pins the audit to the top of lists.\n\n"
            "Status progression is tracked via status stages loaded in the response. "
            "Soft-deleted audits can be restored via `POST /audits/{identifier}/restore`."
        ),
        "sample_body": {
            "name": "Annual Financial Compliance Audit 2026",
            "audit_start_date": "2026-07-01",
            "audit_end_date": "2026-07-31",
            "scope": "internal",
            "checklist_id": None,
            "task_type_id": None,
            "department_id": None,
            "lead_auditor_id": None,
            "quality_manager_id": None,
            "add_appendix": False,
            "description": "Annual internal audit covering financial compliance and controls.",
            "is_featured": False,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Audit",
             "description": "Restore a soft-deleted audit record."},
        ],
    },
    {
        "name": "Clients",
        "route": "clients",
        "param": "client",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage audit client entities (organisations being audited). "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "name": "Apex Manufacturing Ltd",
            "registration_number": "PVT-20220001",
            "industry": "Manufacturing",
            "country_id": "{{country_id}}",
            "contact_person": "James Mwangi",
            "contact_email": "james.mwangi@apexmfg.test",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore",         "name": "Restore Client"},
            {"method": "GET",  "path": "generate-number", "name": "Generate Client Number", "no_id": True},
        ],
    },

    # ── Audit Templates ──────────────────────────────────────────────────────
    {
        "name": "Audit Templates",
        "route": "audit-templates",
        "param": "auditTemplate",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage reusable audit program templates. This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "name": "Financial Statement Audit Template",
            "audit_standard_id": "{{audit_standard_id}}",
            "description": "Standard template for financial statement audits",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore",   "name": "Restore Audit Template"},
            {"method": "POST", "path": "duplicate", "name": "Duplicate Audit Template"},
        ],
    },

    # ── Core Audit ───────────────────────────────────────────────────────────
    {
        "name": "Audit Engagements",
        "route": "audit-engagements",
        "param": "auditEngagement",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage audit engagements (projects) — the top-level audit entity. "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "title": "FY2025 Financial Statement Audit — Apex Manufacturing",
            "client_id": "{{client_id}}",
            "audit_standard_id": "{{audit_standard_id}}",
            "regulation_framework_id": "{{regulation_framework_id}}",
            "audit_type": "financial",
            "start_date": "2026-01-15",
            "end_date": "2026-03-31",
            "lead_auditor_id": "{{user_id}}",
            "status": "planning",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore",      "name": "Restore Engagement"},
            {"method": "POST", "path": "approve",      "name": "Approve Engagement"},
            {"method": "POST", "path": "close",        "name": "Close Engagement"},
            {"method": "POST", "path": "assign",       "name": "Assign Auditors"},
            {"method": "GET",  "path": "summary",      "name": "Engagement Summary",  "no_id": True},
            {"method": "GET",  "path": "stats/status", "name": "Status Statistics",   "no_id": True},
        ],
    },
    {
        "name": "Audit Plans",
        "route": "audit-plans",
        "param": "auditPlan",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage detailed audit plans for engagements. This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "audit_engagement_id": "{{audit_engagement_id}}",
            "title": "Risk Assessment Phase Plan",
            "objectives": "Assess material misstatement risks across all financial statement areas",
            "methodology": "Risk-based audit approach per ISA 315",
            "planned_start_date": "2026-01-15",
            "planned_end_date": "2026-01-31",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Audit Plan"},
            {"method": "POST", "path": "approve", "name": "Approve Audit Plan"},
        ],
    },
    {
        "name": "Audit Procedures",
        "route": "audit-procedures",
        "param": "auditProcedure",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage individual audit steps and procedures within a plan. "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "audit_plan_id": "{{audit_plan_id}}",
            "title": "Agree revenue per ledger to source documents",
            "description": "Obtain the revenue ledger and agree totals to the trial balance",
            "procedure_type": "substantive",
            "assigned_to": "{{user_id}}",
            "due_date": "2026-01-25",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Audit Procedure"},
            {"method": "POST", "path": "execute", "name": "Execute Procedure"},
            {"method": "POST", "path": "review",  "name": "Mark Procedure Reviewed"},
        ],
    },
    {
        "name": "Workpapers",
        "route": "workpapers",
        "param": "workpaper",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage audit evidence and working paper documentation. "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "audit_engagement_id": "{{audit_engagement_id}}",
            "audit_procedure_id": "{{audit_procedure_id}}",
            "title": "Revenue Substantive Testing — Q4 2025",
            "reference": "WP-REV-001",
            "description": "Revenue sampling and vouching procedures",
            "status": "draft",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore",  "name": "Restore Workpaper"},
            {"method": "POST", "path": "review",   "name": "Submit for Review"},
            {"method": "POST", "path": "sign-off", "name": "Sign Off Workpaper"},
            {"method": "POST", "path": "reject",   "name": "Reject Workpaper"},
        ],
    },
    {
        "name": "Findings",
        "route": "findings",
        "param": "finding",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage audit observations and findings. This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "audit_engagement_id": "{{audit_engagement_id}}",
            "workpaper_id": "{{workpaper_id}}",
            "title": "Revenue recognition policy not consistently applied",
            "description": "Management has not consistently applied IFRS 15 revenue recognition criteria",
            "severity": "high",
            "risk_category_id": "{{risk_category_id}}",
            "recommendation": "Implement documented revenue recognition policy and staff training",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore",  "name": "Restore Finding"},
            {"method": "POST", "path": "escalate", "name": "Escalate Finding"},
            {"method": "POST", "path": "resolve",  "name": "Resolve Finding"},
            {"method": "GET",  "path": "summary",  "name": "Findings Summary",  "no_id": True},
            {"method": "GET",  "path": "export",   "name": "Export Findings",   "no_id": True},
        ],
    },
    {
        "name": "Finding Responses",
        "route": "finding-responses",
        "param": "findingResponse",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage management responses to audit findings. This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "finding_id": "{{finding_id}}",
            "response": "Management accepts the finding. A formal revenue recognition policy will be developed and staff trained by Q2 2026.",
            "action_plan": "Engage external consultant to develop policy",
            "target_date": "2026-06-30",
            "responsible_person": "CFO",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Finding Response"},
            {"method": "POST", "path": "accept",  "name": "Accept Response"},
            {"method": "POST", "path": "reject",  "name": "Reject Response"},
        ],
    },

    # ── Risk ─────────────────────────────────────────────────────────────────
    {
        "name": "Risk Assessments",
        "route": "risk-assessments",
        "param": "riskAssessment",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage entity and process risk assessments. This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "audit_engagement_id": "{{audit_engagement_id}}",
            "risk_category_id": "{{risk_category_id}}",
            "title": "Revenue Completeness Risk",
            "description": "Risk that revenue is not completely recorded",
            "inherent_risk_level": "high",
            "control_risk_level": "medium",
            "detection_risk_level": "low",
            "assessed_risk_level": "high",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Risk Assessment"},
            {"method": "POST", "path": "approve", "name": "Approve Risk Assessment"},
            {"method": "GET",  "path": "matrix",  "name": "Risk Heat Map Matrix", "no_id": True},
        ],
    },
    {
        "name": "Risk Matrices",
        "route": "risk-matrices",
        "param": "riskMatrix",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage risk scoring matrices for engagements. This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "audit_engagement_id": "{{audit_engagement_id}}",
            "name": "FY2025 Risk Matrix",
            "likelihood_scale": 5,
            "impact_scale": 5,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Risk Matrix"},
        ],
    },

    # ── Compliance ───────────────────────────────────────────────────────────
    {
        "name": "Compliance Requirements",
        "route": "compliance-requirements",
        "param": "complianceRequirement",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage compliance tracking items against regulation frameworks. "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "audit_engagement_id": "{{audit_engagement_id}}",
            "regulation_framework_id": "{{regulation_framework_id}}",
            "title": "Section 302 CEO/CFO Certification",
            "description": "Chief executives must certify accuracy of financial reports",
            "compliance_type": "mandatory",
            "due_date": "2026-03-31",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Compliance Requirement"},
            {"method": "POST", "path": "track",   "name": "Update Compliance Status"},
            {"method": "GET",  "path": "summary", "name": "Compliance Summary", "no_id": True},
        ],
    },
    {
        "name": "Compliance Evidence",
        "route": "compliance-evidence",
        "param": "complianceEvidence",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage evidence records for compliance requirements. "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "compliance_requirement_id": "{{compliance_requirement_id}}",
            "title": "Signed CEO Certification Letter",
            "description": "CEO certification signed and filed with SEC",
            "evidence_date": "2026-03-25",
            "collected_by": "{{user_id}}",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Compliance Evidence"},
            {"method": "POST", "path": "verify",  "name": "Verify Evidence"},
        ],
    },

    # ── Controls ──────────────────────────────────────────────────────────────
    {
        "name": "Control Objectives",
        "route": "control-objectives",
        "param": "controlObjective",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage internal control objective definitions. "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "audit_engagement_id": "{{audit_engagement_id}}",
            "title": "Revenue Authorisation Control",
            "description": "All revenue transactions must be authorised by a designated authority",
            "control_type": "preventive",
            "risk_category_id": "{{risk_category_id}}",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Control Objective"},
        ],
    },
    {
        "name": "Control Tests",
        "route": "control-tests",
        "param": "controlTest",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage tests of controls linked to control objectives. "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "control_objective_id": "{{control_objective_id}}",
            "title": "Test revenue authorisation approvals",
            "description": "Select a sample of 30 revenue transactions and verify authorisation signatures",
            "test_type": "reperformance",
            "sample_size": 30,
            "assigned_to": "{{user_id}}",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Control Test"},
            {"method": "POST", "path": "execute", "name": "Execute Control Test"},
            {"method": "POST", "path": "review",  "name": "Review Control Test"},
        ],
    },

    # ── Reports ───────────────────────────────────────────────────────────────
    {
        "name": "Reports",
        "route": "reports",
        "param": "report",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Generate and manage audit reports (draft, final, management letter). "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "audit_engagement_id": "{{audit_engagement_id}}",
            "report_type": "audit_report",
            "title": "Independent Auditor's Report — FY2025",
            "period_from": "2025-01-01",
            "period_to": "2025-12-31",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore",   "name": "Restore Report"},
            {"method": "POST", "path": "approve",   "name": "Approve Report"},
            {"method": "POST", "path": "finalise",  "name": "Finalise Report"},
            {"method": "POST", "path": "schedule",  "name": "Schedule Report Delivery"},
            {"method": "GET",  "path": "export",    "name": "Export Report (PDF)", "no_id": False},
        ],
    },

    # ── Settings ──────────────────────────────────────────────────────────────
    {
        "name": "Firm Settings",
        "route": "firm-settings",
        "param": "firmSettings",
        "scope": "tenant",
        "description": (
            "⚠️ **Not yet implemented** — routes not yet defined in `routes/tenant.php`.\n\n"
            "Manage tenant firm profile and AIAS configuration settings. "
            "This folder is a placeholder for future implementation."
        ),
        "sample_body": {
            "firm_name": "Pinnacle Audit Partners LLP",
            "registration_number": "LLP-2020-001",
            "address": "Upper Hill, Nairobi",
            "phone": "+254700000000",
            "email": "info@pinnacleaudit.test",
            "country_id": "{{country_id}}",
            "default_audit_standard_id": "{{audit_standard_id}}",
        },
        "extra_actions": [],
    },
]

# ---------------------------------------------------------------------------
# ENVIRONMENTS
# ---------------------------------------------------------------------------

ENVIRONMENTS = [
    {
        "name": "AIAS Local",
        "file": "postman/environments/AIAS_Local.postman_environment.json",
        "id": "aias-local-env-0000-0000-0000-000000000001",
        "base_url": "http://localhost:8020/api",
        "tenant_base_url": "http://localhost:8020",
        "user_email": "superadmin@aias.test",
        "user_password": "password",
    },
    {
        "name": "AIAS Development",
        "file": "postman/environments/AIAS_Development.postman_environment.json",
        "id": "aias-dev-env-00000-0000-0000-000000000002",
        "base_url": "https://dev.aias.app/api",
        "tenant_base_url": "https://dev.aias.app",
        "user_email": "dev@aias.app",
        "user_password": "",
    },
    {
        "name": "AIAS Staging",
        "file": "postman/environments/AIAS_Staging.postman_environment.json",
        "id": "aias-staging-env-000-0000-0000-000000000003",
        "base_url": "https://staging.aias.app/api",
        "tenant_base_url": "https://staging.aias.app",
        "user_email": "staging@aias.app",
        "user_password": "",
    },
    {
        "name": "AIAS Production",
        "file": "postman/environments/AIAS_Production.postman_environment.json",
        "id": "aias-prod-env-00000-0000-0000-000000000004",
        "base_url": "https://app.aias.app/api",
        "tenant_base_url": "https://app.aias.app",
        "user_email": "",
        "user_password": "",
    },
]

# ---------------------------------------------------------------------------
# RESOURCE ID VARIABLES
# All environment files include these; values are auto-populated by test scripts.
# ---------------------------------------------------------------------------

RESOURCE_ID_VARS: list[tuple[str, str, str]] = [
    # --- Auto-populated by login / me scripts ---
    ("tenant_id",                   "", "Current tenant identifier string (auto-populated after login)"),
    ("user_id",                     "", "Authenticated user identifier (auto-populated after login)"),
    # --- Global Reference ---
    ("continent_id",                "", "Continent identifier string (auto-populated after Create Continent)"),
    ("country_id",                  "", "Country identifier string (auto-populated after Create Country)"),
    ("audit_standard_id",           "", "Audit Standard identifier string"),
    ("regulation_framework_id",     "", "Regulation Framework identifier string"),
    ("risk_category_id",            "", "Risk Category identifier string"),
    # --- IAM ---
    ("role_id",                     "", "Role identifier string"),
    ("service_user_id",             "", "Service User identifier string"),
    ("api_key_id",                  "", "API Key identifier string"),
    # --- Tenant Reference ---
    ("priority_level_id",           "", "Priority Level identifier string (auto-populated after Create Priority Level)"),
    ("preamble_id",                 "", "Preamble identifier string"),
    ("checklist_type_id",           "", "Checklist Type identifier string"),
    ("section_style_id",            "", "Section Style identifier string"),
    ("checklist_id",                "", "Checklist identifier string"),
    ("tenant_user_id",              "", "Tenant User identifier string (auto-populated after Create User in tenant scope)"),
    ("company_id",                  "", "Company identifier string"),
    ("department_id",               "", "Department identifier string"),
    ("audit_id",                    "", "Audit identifier string"),
    ("client_id",                   "", "Client identifier string"),
    ("group_id",                    "", "Group identifier string"),
    # --- Audit Templates ---
    ("audit_template_id",           "", "Audit Template identifier string"),
    # --- Core Audit ---
    ("audit_engagement_id",         "", "Audit Engagement identifier string"),
    ("audit_plan_id",               "", "Audit Plan identifier string"),
    ("audit_procedure_id",          "", "Audit Procedure identifier string"),
    ("workpaper_id",                "", "Workpaper identifier string"),
    ("finding_id",                  "", "Finding identifier string"),
    ("finding_response_id",         "", "Finding Response identifier string"),
    # --- Risk ---
    ("risk_assessment_id",          "", "Risk Assessment identifier string"),
    ("risk_matrix_id",              "", "Risk Matrix identifier string"),
    # --- Compliance ---
    ("compliance_requirement_id",   "", "Compliance Requirement identifier string"),
    ("compliance_evidence_id",      "", "Compliance Evidence identifier string"),
    # --- Controls ---
    ("control_objective_id",        "", "Control Objective identifier string"),
    ("control_test_id",             "", "Control Test identifier string"),
    # --- Reports ---
    ("report_id",                   "", "Report identifier string"),
]

# ---------------------------------------------------------------------------
# YAML UTILITIES
# ---------------------------------------------------------------------------

# Stable bearer auth UUID (keep stable so Postman recognises re-imports).
BEARER_AUTH_ID = "cba390d9-dacd-4cef-8639-728436a27ea3"


class _LiteralStr(str):
    """Forces YAML literal block scalar style (|-) for multi-line strings."""


def _literal_representer(dumper: yaml.Dumper, data: "_LiteralStr") -> yaml.ScalarNode:
    return dumper.represent_scalar("tag:yaml.org,2002:str", data, style="|")


class _V3Dumper(yaml.Dumper):
    """Custom YAML Dumper: literal blocks for _LiteralStr, key order preserved."""


_V3Dumper.add_representer(_LiteralStr, _literal_representer)


def _write_yaml(path: Path, data: dict) -> None:
    """Write a v3 definition or request YAML file."""
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        yaml.dump(data, Dumper=_V3Dumper, allow_unicode=True, sort_keys=False, default_flow_style=False)
    )


def _name_to_filename(name: str) -> str:
    """Convert a display name to a filesystem-safe stem (no extension)."""
    return re.sub(r"[/\\:]", "-", name).strip()


def _lines_to_code(lines: list[str]) -> _LiteralStr:
    """Join exec lines into a single literal-block YAML string."""
    return _LiteralStr("\n".join(lines))


# ---------------------------------------------------------------------------
# SCRIPT CONSTANTS
# ---------------------------------------------------------------------------

INDEX_QUERY_PARAMS: list[dict] = [
    {"key": "page",         "value": "1",          "description": "Page number (min: 1)"},
    {"key": "per_page",     "value": "15",          "description": "Items per page (max: 100)"},
    {"key": "search",       "value": "",            "description": "Full-text search term"},
    {"key": "sort_by",      "value": "created_at",  "description": "Sort column"},
    {"key": "sort_order",   "value": "desc",        "description": "asc | desc"},
    {"key": "show_deleted", "value": "0",           "description": "Include soft-deleted: 0|1", "disabled": True},
]

LOGIN_TEST_SCRIPT = [
    "pm.test('Login successful', () => {",
    "    pm.response.to.have.status(200);",
    "    const j = pm.response.json();",
    "    pm.expect(j).to.have.property('data');",
    "    pm.expect(j.data).to.have.property('token');",
    "});",
    "if (pm.response.code === 200) {",
    "    const j = pm.response.json();",
    "    if (j.data && j.data.token) {",
    "        pm.environment.set('access_token', j.data.token);",
    "        console.log('Token saved:', j.data.token.substring(0, 20) + '...');",
    "    }",
    "    if (j.data && j.data.user) {",
    "        // Resources use 'identifier' string, not numeric 'id'",
    "        const userId = j.data.user.identifier || j.data.user.id;",
    "        pm.environment.set('user_id', String(userId));",
    "        console.log('User ID saved:', userId);",
    "        if (j.data.user.tenant_id) {",
    "            pm.environment.set('tenant_id', j.data.user.tenant_id);",
    "            console.log('Tenant ID saved:', j.data.user.tenant_id);",
    "        }",
    "    }",
    "}",
]

ME_TEST_SCRIPT = [
    "pm.test('Me endpoint successful', () => {",
    "    pm.response.to.have.status(200);",
    "});",
    "if (pm.response.code === 200) {",
    "    const json = pm.response.json();",
    "    if (json.data) {",
    "        // Resources use 'identifier' string, not numeric 'id'",
    "        const userId = json.data.identifier || json.data.id;",
    "        pm.environment.set('user_id', String(userId));",
    "        const userName = (json.data.first_name + ' ' + (json.data.last_name || '')).trim();",
    "        pm.environment.set('user_name', userName);",
    "        if (json.data.tenant_id) {",
    "            pm.environment.set('tenant_id', json.data.tenant_id);",
    "        }",
    "        console.log('User context saved:', json.data.email, '| tenant:', json.data.tenant_id);",
    "    }",
    "}",
]

CREATE_TEST_SCRIPT_TEMPLATE = """\
pm.test('Status code is 201', () => {{ pm.response.to.have.status(201); }});
pm.test('Response envelope valid', () => {{
    const j = pm.response.json();
    pm.expect(j).to.have.property('status');
    pm.expect(j).to.have.property('data');
}});
if (pm.response.code === 201) {{
    const j = pm.response.json();
    // Save the identifier string (e.g. AT.1.1234567890) — NOT the numeric id
    const resourceId = j.data && (j.data.identifier || j.data.id);
    if (resourceId) {{
        pm.environment.set('{var_name}', String(resourceId));
        console.log('Saved {var_name}:', resourceId);
    }}
}}"""

GENERIC_TEST_SCRIPT = [
    "pm.test('Response is JSON', () => { pm.response.to.be.json; });",
    "pm.test('Status is success', () => {",
    "    const j = pm.response.json();",
    "    pm.expect(j).to.have.property('status', 'success');",
    "});",
]

# ---------------------------------------------------------------------------
# SHARED HELPERS
# ---------------------------------------------------------------------------


def param_to_var_name(param: str) -> str:
    """Convert camelCase route param to snake_case env var name."""
    import re
    s1 = re.sub(r"(.)([A-Z][a-z]+)", r"\1_\2", param)
    return re.sub(r"([a-z0-9])([A-Z])", r"\1_\2", s1).lower()


def singular(name: str) -> str:
    """Very light singularisation for display names."""
    if name.endswith("ies"):
        return name[:-3] + "y"
    if name.endswith("ses") or name.endswith("xes"):
        return name[:-2]
    if name.endswith("s") and not name.endswith("ss"):
        return name[:-1]
    return name


def get_host_var(mod: dict) -> str:
    """Return the Postman env variable name for the base URL based on module scope."""
    return "tenant_base_url" if mod["scope"] == "tenant" else "base_url"


# ---------------------------------------------------------------------------
# V3 REQUEST BUILDERS
# Each builder returns (filename_stem, request_dict).
# ---------------------------------------------------------------------------


def _std_headers() -> dict:
    return {"Accept": "application/json", "Content-Type": "application/json"}


def _tenant_qp() -> dict:
    return {"key": "tenant_id", "value": "{{tenant_id}}", "description": "Tenant identifier string (required)"}


def _scripts(exec_lines: list[str]) -> list[dict]:
    return [{"type": "afterResponse", "code": _lines_to_code(exec_lines), "language": "text/javascript"}]


def _json_body(payload: dict) -> dict:
    return {"type": "json", "content": _LiteralStr(json.dumps(payload, indent=2))}


def _make_request(
    name: str,
    description: str,
    url: str,
    method: str,
    query_params: Optional[list[dict]] = None,
    body: Optional[dict] = None,
    exec_lines: Optional[list[str]] = None,
    order: int = 1000,
) -> tuple[str, dict]:
    data: dict[str, Any] = {
        "$kind": "http-request",
        "name": name,
        "description": description,
        "url": url,
        "method": method,
        "headers": _std_headers(),
    }
    if body is not None:
        data["body"] = body
    if query_params:
        data["queryParams"] = query_params
    if exec_lines:
        data["scripts"] = _scripts(exec_lines)
    data["order"] = order
    return _name_to_filename(name), data


def build_index_request(mod: dict, order: int = 1000) -> tuple[str, dict]:
    host_var = get_host_var(mod)
    url = f"{{{{{host_var}}}}}/v1/{mod['route']}"
    qp: list[dict] = []
    if mod["scope"] == "tenant":
        qp.append(_tenant_qp())
    qp.extend(INDEX_QUERY_PARAMS)
    return _make_request(
        name=f"List {mod['name']}",
        description=(
            f"Retrieve a paginated list of {mod['name'].lower()}.\n\n"
            f"Supports filtering via `search`, sorting via `sort_by`/`sort_order`, "
            f"and pagination via `page`/`per_page` (max 100 per page). "
            f"Pass `show_deleted=1` to include soft-deleted records."
            + (" Pass `tenant_id` as a query parameter to scope results to the tenant." if mod["scope"] == "tenant" else "")
        ),
        url=url, method="GET", query_params=qp,
        exec_lines=GENERIC_TEST_SCRIPT, order=order,
    )


def build_store_request(mod: dict, order: int = 2000) -> tuple[str, dict]:
    host_var = get_host_var(mod)
    url = f"{{{{{host_var}}}}}/v1/{mod['route']}"
    var_name = param_to_var_name(mod["param"]) + "_id"
    create_script = CREATE_TEST_SCRIPT_TEMPLATE.format(var_name=var_name).splitlines()
    payload = {**mod.get("sample_body", {})}
    if mod["scope"] == "tenant":
        payload = {"tenant_id": "{{tenant_id}}", **payload}
    singular_name = singular(mod['name'])
    return _make_request(
        name=f"Create {singular_name}",
        description=(
            f"Create a new {singular_name.lower()} record.\n\n"
            f"On success the `identifier` of the new record is automatically saved to "
            f"`{{{{{var_name}}}}}` in the active environment via the post-response test script."
        ),
        url=url, method="POST", body=_json_body(payload),
        exec_lines=create_script, order=order,
    )


def build_show_request(mod: dict, order: int = 3000) -> tuple[str, dict]:
    host_var = get_host_var(mod)
    var_name = param_to_var_name(mod["param"]) + "_id"
    url = f"{{{{{host_var}}}}}/v1/{mod['route']}/{{{{{var_name}}}}}"
    qp: list[dict] = []
    if mod["scope"] == "tenant":
        qp.append(_tenant_qp())
    singular_name = singular(mod['name'])
    return _make_request(
        name=f"Get {singular_name}",
        description=(
            f"Retrieve a single {singular_name.lower()} by its `identifier` string.\n\n"
            f"Replace `{{{{{var_name}}}}}` with the identifier saved after creation."
        ),
        url=url, method="GET", query_params=qp or None,
        exec_lines=GENERIC_TEST_SCRIPT, order=order,
    )


def build_update_request(mod: dict, order: int = 4000) -> tuple[str, dict]:
    host_var = get_host_var(mod)
    var_name = param_to_var_name(mod["param"]) + "_id"
    url = f"{{{{{host_var}}}}}/v1/{mod['route']}/{{{{{var_name}}}}}"
    payload = {**mod.get("sample_body", {})}
    if mod["scope"] == "tenant":
        payload = {"tenant_id": "{{tenant_id}}", **payload}
    singular_name = singular(mod['name'])
    return _make_request(
        name=f"Update {singular_name}",
        description=(
            f"Update an existing {singular_name.lower()} record.\n\n"
            f"Send only the fields you want to change — all fields are optional on update. "
            f"Replace `{{{{{var_name}}}}}` with the identifier of the record to update."
        ),
        url=url, method="PUT", body=_json_body(payload),
        exec_lines=GENERIC_TEST_SCRIPT, order=order,
    )


def build_destroy_request(mod: dict, order: int = 5000) -> tuple[str, dict]:
    host_var = get_host_var(mod)
    var_name = param_to_var_name(mod["param"]) + "_id"
    url = f"{{{{{host_var}}}}}/v1/{mod['route']}/{{{{{var_name}}}}}"
    qp: list[dict] = []
    if mod["scope"] == "tenant":
        qp.append(_tenant_qp())
    singular_name = singular(mod['name'])
    return _make_request(
        name=f"Delete {singular_name}",
        description=(
            f"Soft-delete a {singular_name.lower()} record. The record is not permanently removed. "
            f"Restore it with the corresponding `POST /{mod['route']}/{{{{{var_name}}}}}/restore` request."
        ),
        url=url, method="DELETE", query_params=qp or None,
        exec_lines=GENERIC_TEST_SCRIPT, order=order,
    )


def build_extra_action_request(mod: dict, action: dict, order: int = 6000) -> tuple[str, dict]:
    host_var = get_host_var(mod)
    var_name = param_to_var_name(mod["param"]) + "_id"
    action_path = action["path"]
    no_id = action.get("no_id", False)
    method = action.get("method", "POST")

    if no_id:
        url = f"{{{{{host_var}}}}}/v1/{mod['route']}/{action_path}"
    else:
        url = f"{{{{{host_var}}}}}/v1/{mod['route']}/{{{{{var_name}}}}}/{action_path}"

    qp: Optional[list[dict]] = None
    body: Optional[dict] = None

    if method in ("GET", "DELETE"):
        raw_qp: list[dict] = []
        if mod["scope"] == "tenant":
            raw_qp.append(_tenant_qp())
        qp = raw_qp or None
    elif method in ("POST", "PUT", "PATCH"):
        # Allow action to supply a custom body; fall back to empty payload
        if "body" in action:
            payload: dict[str, Any] = dict(action["body"])
        else:
            payload = {}
        if mod["scope"] == "tenant" and "tenant_id" not in payload:
            payload = {"tenant_id": "{{tenant_id}}", **payload}
        body = _json_body(payload)

    description = action.get("description", action["name"])

    # Allow action to supply custom exec_lines or a save_var shortcut
    if "exec_lines" in action:
        action_exec_lines = action["exec_lines"]
    elif "save_var" in action:
        action_exec_lines = CREATE_TEST_SCRIPT_TEMPLATE.format(var_name=action["save_var"]).splitlines()
    else:
        action_exec_lines = GENERIC_TEST_SCRIPT

    return _make_request(
        name=action["name"],
        description=description,
        url=url, method=method, query_params=qp, body=body,
        exec_lines=action_exec_lines, order=order,
    )


def build_module_requests(mod: dict) -> list[tuple[str, dict]]:
    """Return ordered list of (filename_stem, request_dict) for a module."""
    items: list[tuple[str, dict]] = []
    order = 1000

    if not mod.get("skip_crud", False):
        items.append(build_index_request(mod, order));   order += 1000
        items.append(build_store_request(mod, order));   order += 1000
        items.append(build_show_request(mod, order));    order += 1000
        items.append(build_update_request(mod, order));  order += 1000
        items.append(build_destroy_request(mod, order)); order += 1000

    for action in mod.get("extra_actions", []):
        items.append(build_extra_action_request(mod, action, order))
        order += 1000

    return items


def build_auth_requests() -> list[tuple[str, dict]]:
    """Build Auth folder requests for the v3 collection."""
    return [
        _make_request(
            name="Health Check",
            description=(
                "Verify the API server is running and reachable.\n\n"
                "No authentication required. Returns `200 OK` with a status message when the server is healthy. "
                "Both central (`{{base_url}}/v1/health`) and tenant (`{{tenant_base_url}}/v1/health`) health "
                "endpoints are available."
            ),
            url="{{base_url}}/v1/health", method="GET",
            exec_lines=GENERIC_TEST_SCRIPT, order=1000,
        ),
        _make_request(
            name="Login (Central / Super-Admin)",
            description=(
                "Authenticate as a central super-admin user.\n\n"
                "Returns a Bearer `access_token` in `data.token`. "
                "The test script automatically saves `access_token`, `user_id`, and `tenant_id` "
                "to the active environment. "
                "Use `{{base_url}}/v1/auth/login` — no `tenant_id` is required for central login."
            ),
            url="{{base_url}}/v1/auth/login", method="POST",
            body=_json_body({"email": "{{user_email}}", "password": "{{user_password}}"}),
            exec_lines=LOGIN_TEST_SCRIPT, order=2000,
        ),
        _make_request(
            name="Login (Tenant User)",
            description=(
                "Authenticate as a tenant-scoped user.\n\n"
                "`tenant_id` is the tenant identifier string (e.g. `AT.1.1748000000`). "
                "Returns a Bearer `access_token` saved automatically to `{{access_token}}`. "
                "After login, all tenant-scoped requests use `{{tenant_id}}` from the environment."
            ),
            url="{{tenant_base_url}}/v1/auth/login", method="POST",
            body=_json_body({"tenant_id": "{{tenant_id}}", "email": "{{user_email}}", "password": "{{user_password}}"}),
            exec_lines=LOGIN_TEST_SCRIPT, order=3000,
        ),
        _make_request(
            name="Register (Tenant User)",
            description=(
                "Register a new user account within a tenant context (public endpoint — no auth required).\n\n"
                "This endpoint is the self-service registration route for tenant users. "
                "It only accepts the minimal user identity fields — role assignment is done separately "
                "via the tenant user management endpoints.\n\n"
                "Required fields: `first_name`, `last_name`, `email` (unique within tenant), "
                "`password` (min 8 chars, must include both letters and numbers), `password_confirmation` (must match `password`).\n\n"
                "Note: `tenant_id`, `username`, `phone`, `title`, and other profile fields are **not accepted** "
                "by this endpoint. Use the Create Tenant User endpoint for full profile creation."
            ),
            url="{{tenant_base_url}}/v1/auth/register", method="POST",
            body=_json_body({
                "first_name": "Jane",
                "last_name": "Auditor",
                "email": "jane.auditor@example.test",
                "password": "Password123!",
                "password_confirmation": "Password123!",
            }),
            exec_lines=GENERIC_TEST_SCRIPT, order=4000,
        ),
        _make_request(
            name="Get Authenticated User (Tenant)",
            description=(
                "Retrieve the currently authenticated tenant user with their roles, permissions, and tenant context.\n\n"
                "The test script saves `user_id` (identifier string) and `tenant_id` to the active environment. "
                "Pass `tenant_id` as a query parameter to scope the request."
            ),
            url="{{tenant_base_url}}/v1/auth/me", method="GET",
            query_params=[{"key": "tenant_id", "value": "{{tenant_id}}", "description": "Tenant identifier string"}],
            exec_lines=ME_TEST_SCRIPT, order=5000,
        ),
        _make_request(
            name="Get Authenticated User (Central)",
            description=(
                "Retrieve the currently authenticated central/super-admin user.\n\n"
                "No `tenant_id` required. Returns user profile with roles. "
                "The test script saves `user_id` (identifier string) to the active environment."
            ),
            url="{{base_url}}/v1/auth/me", method="GET",
            exec_lines=ME_TEST_SCRIPT, order=6000,
        ),
        _make_request(
            name="Logout (Tenant)",
            description=(
                "Revoke the current Bearer access token for a tenant user.\n\n"
                "After logout the `access_token` is invalidated. "
                "Pass `tenant_id` to ensure the correct tenant context is used for token revocation."
            ),
            url="{{tenant_base_url}}/v1/auth/logout", method="POST",
            body=_json_body({"tenant_id": "{{tenant_id}}"}),
            exec_lines=GENERIC_TEST_SCRIPT, order=7000,
        ),
        _make_request(
            name="Logout (Central)",
            description=(
                "Revoke the current Bearer access token for a central / super-admin user.\n\n"
                "No `tenant_id` required. After logout the `access_token` is invalidated."
            ),
            url="{{base_url}}/v1/auth/logout", method="POST",
            body=_json_body({}),
            exec_lines=GENERIC_TEST_SCRIPT, order=8000,
        ),
    ]


# ---------------------------------------------------------------------------
# V3 COLLECTION WRITER
# ---------------------------------------------------------------------------


def write_v3_collection(output_dir: Path, base_url: str, tenant_base_url: str, collection_id: str) -> None:
    """Write the full v3 collection as a directory of YAML files."""
    if output_dir.exists():
        shutil.rmtree(output_dir)

    # ── Collection root definition ─────────────────────────────────────────
    _write_yaml(output_dir / ".resources" / "definition.yaml", {
        "$kind": "collection",
        "id": collection_id,
        "name": COLLECTION_NAME,
        "description": COLLECTION_DESCRIPTION,
        "variables": {
            "base_url": base_url,
            "tenant_base_url": tenant_base_url,
        },
        "auth": [{
            "id": BEARER_AUTH_ID,
            "type": "bearer",
            "name": "bearer auth",
            "credentials": {"token": "{{access_token}}"},
        }],
    })

    # ── Central group ──────────────────────────────────────────────────────
    _write_yaml(output_dir / "Central" / ".resources" / "definition.yaml", {
        "$kind": "collection",
        "description": (
            "## Central Resources\n\n"
            "Cross-tenant (central database) resources accessible to **super-admin** users "
            "and shared infrastructure services.\n\n"
            "### ✅ Implemented Endpoints\n\n"
            "| Resource | Endpoints |\n"
            "|---------|---------|\n"
            "| **Auth** | POST /login, POST /logout, GET /me (central) |\n"
            "| **Tenants** | GET list, POST create, GET show, PUT update, DELETE, POST /{id}/users |\n"
            "| **Users** | POST create only (list/show/update/delete not yet implemented) |\n"
            "| **Continents** | Full CRUD + POST restore |\n"
            "| **Countries** | Full CRUD + POST restore |\n\n"
            "### ⚠️ Planned (Not Yet Implemented)\n"
            "Roles, Service Users, API Keys, MFA, Audit Standards, Regulation Frameworks, Risk Categories\n\n"
            "**Base URL:** `{{base_url}}` (e.g. `http://localhost:8020/api`)\n\n"
            "All requests use Bearer token authentication. No `tenant_id` required for central-scope requests."
        ),
        "order": 1000,
    })

    _write_yaml(output_dir / "Central" / "Auth" / ".resources" / "definition.yaml", {
        "$kind": "collection",
        "description": (
            "## Authentication\n\n"
            "Authentication endpoints for both central and tenant users.\n\n"
            "### Workflow\n\n"
            "1. Call **Health Check** to verify the server is running.\n"
            "2. Call **Login (Central / Super-Admin)** to get a Bearer token for central operations. "
            "No `tenant_id` needed.\n"
            "3. Call **Login (Tenant User)** with `tenant_id` in the JSON body to authenticate "
            "as a tenant user.\n"
            "4. Test scripts auto-save `access_token`, `user_id`, and `tenant_id` to the environment.\n"
            "5. All subsequent protected requests use `{{access_token}}` via Bearer auth.\n\n"
            "### Tenant Identification\n\n"
            "Tenancy uses `InitializeTenancyByBodyParam` middleware — pass `tenant_id` as a body param "
            "(POST requests) or query param (GET requests). "
            "Central login does **not** require `tenant_id`.\n\n"
            "**Central login URL:** `{{base_url}}/v1/auth/login`\n"
            "**Tenant login URL:** `{{tenant_base_url}}/v1/auth/login`\n"
            "**Tenant register URL:** `{{tenant_base_url}}/v1/auth/register` (public endpoint)"
        ),
        "order": 1000,
    })
    for stem, req in build_auth_requests():
        _write_yaml(output_dir / "Central" / "Auth" / f"{stem}.request.yaml", req)

    central_mods = [m for m in MODULES if m["scope"] == "central"]
    for idx, mod in enumerate(central_mods):
        folder_order = (idx + 2) * 1000  # Auth is 1000; modules start at 2000
        _write_yaml(output_dir / "Central" / mod["name"] / ".resources" / "definition.yaml", {
            "$kind": "collection",
            "description": mod.get("description", ""),
            "order": folder_order,
        })
        for stem, req in build_module_requests(mod):
            _write_yaml(output_dir / "Central" / mod["name"] / f"{stem}.request.yaml", req)

    # ── Tenant group ───────────────────────────────────────────────────────
    _write_yaml(output_dir / "Tenant" / ".resources" / "definition.yaml", {
        "$kind": "collection",
        "description": (
            "## Tenant Resources\n\n"
            "Resources isolated per organisation (tenant) — each tenant has its own MySQL database.\n\n"
            "### How to Scope Requests\n\n"
            "- **POST / PUT / PATCH:** include `tenant_id` in the JSON body.\n"
            "- **GET / DELETE:** pass `tenant_id` as a query parameter.\n"
            "- `tenant_id` is the tenant identifier string (e.g. `AT.1.1748000000`), "
            "auto-saved to `{{tenant_id}}` after login.\n\n"
            "**Base URL:** `{{tenant_base_url}}` (e.g. `http://localhost:8020`)\n\n"
            "### ✅ Implemented Endpoints\n\n"
            "| Resource | Endpoints |\n"
            "|---------|---------|\n"
            "| **Auth (Tenant)** | POST register, POST login, POST logout, GET me |\n"
            "| **Preambles** | Full CRUD + POST restore |\n"
            "| **Users** | Full CRUD + POST restore |\n"
            "| **Checklist Types** | Full CRUD + POST restore |\n"
            "| **Section Styles** | Full CRUD + POST restore |\n"
            "| **Checklists** | Full CRUD + POST restore |\n"
            "| **Companies** | Full CRUD + POST restore |\n"
            "| **Departments** | Full CRUD + POST restore |\n"
            "| **Audits** | Full CRUD + POST restore |\n\n"
            "### ⚠️ Planned (Not Yet Implemented)\n"
            "Clients, Audit Templates, Audit Engagements, Audit Plans, Audit Procedures, "
            "Workpapers, Findings, Finding Responses, Risk Assessments, Risk Matrices, "
            "Compliance Requirements, Compliance Evidence, Control Objectives, Control Tests, "
            "Reports, Firm Settings.\n\n"
            "All soft-deleted records can be restored via `POST /{resource}/{identifier}/restore`."
        ),
        "order": 2000,
    })

    tenant_mods = [m for m in MODULES if m["scope"] == "tenant"]
    for idx, mod in enumerate(tenant_mods):
        _write_yaml(output_dir / "Tenant" / mod["name"] / ".resources" / "definition.yaml", {
            "$kind": "collection",
            "description": mod.get("description", ""),
            "order": (idx + 1) * 1000,
        })
        for stem, req in build_module_requests(mod):
            _write_yaml(output_dir / "Tenant" / mod["name"] / f"{stem}.request.yaml", req)


# ---------------------------------------------------------------------------
# ENVIRONMENT FILE BUILDER
# ---------------------------------------------------------------------------


def build_env_file(env: dict) -> dict:
    base_vars = [
        {"key": "base_url",        "value": env["base_url"],        "enabled": True, "type": "default"},
        {"key": "tenant_base_url", "value": env["tenant_base_url"], "enabled": True, "type": "default"},
        {"key": "user_email",      "value": env["user_email"],      "enabled": True, "type": "default"},
        {"key": "user_password",   "value": env["user_password"],   "enabled": True, "type": "secret"},
        {"key": "access_token",    "value": "",                     "enabled": True, "type": "secret"},
    ]

    resource_vars = [
        {"key": k, "value": v, "enabled": True, "type": "default", "description": desc}
        for k, v, desc in RESOURCE_ID_VARS
    ]

    return {
        "id":   env["id"],
        "name": env["name"],
        "_postman_variable_scope": "environment",
        "values": base_vars + resource_vars,
    }


# ---------------------------------------------------------------------------
# SUMMARY
# ---------------------------------------------------------------------------


def print_summary(output_dir: Path) -> None:
    """Print a folder/request count summary by walking the v3 directory."""
    if not output_dir.exists():
        return

    line = "\u2500" * 60
    display_rows: list[tuple[str, int]] = []
    total_folders = 0
    total_requests = 0

    for group_dir in sorted(output_dir.iterdir()):
        if not group_dir.is_dir() or group_dir.name.startswith("."):
            continue
        display_rows.append((f"\u2500\u2500 {group_dir.name} \u2500\u2500", -1))
        for folder_dir in sorted(group_dir.iterdir()):
            if not folder_dir.is_dir() or folder_dir.name.startswith("."):
                continue
            req_count = sum(1 for f in folder_dir.iterdir() if f.name.endswith(".request.yaml"))
            total_folders += 1
            total_requests += req_count
            display_rows.append((f"  {folder_dir.name}", req_count))

    print(f"\n{line}")
    print(f"  Collection  : {COLLECTION_NAME}  (v3)")
    print(f"  Folders     : {total_folders}")
    print(f"  Requests    : {total_requests}")
    print(f"{line}")

    for label, count in display_rows:
        if count == -1:
            print(f"\n  {label}")
        else:
            print(f"  {label:<32}{count:>3}")

    print(f"{line}\n")


def extract_folder_files(output_dir: Path, folder_name: str) -> None:
    """Print all request YAML files in a named folder."""
    for group_dir in output_dir.iterdir():
        if not group_dir.is_dir():
            continue
        folder_dir = group_dir / folder_name
        if folder_dir.exists():
            for req_file in sorted(folder_dir.iterdir()):
                if req_file.name.endswith(".request.yaml"):
                    print(f"\n=== {req_file.name} ===")
                    print(req_file.read_text())
            return
    print(f"Folder not found: {folder_name}")


# ---------------------------------------------------------------------------
# MAIN
# ---------------------------------------------------------------------------


def resolve_output_paths(args: argparse.Namespace) -> tuple[Path, list[dict]]:
    """Resolve collection output directory and environment file paths."""
    script_dir = Path(__file__).resolve().parent
    # .github/skills/update-postman/scripts/generate.py → project root is 4 levels up
    aias_root = script_dir.parent.parent.parent.parent

    collection_out = Path(args.output)
    if not collection_out.is_absolute():
        collection_out = aias_root / collection_out

    environments_out = []
    for env in ENVIRONMENTS:
        env_path = Path(env["file"])
        if not env_path.is_absolute():
            env_path = aias_root / env_path
        environments_out.append({**env, "_resolved_path": env_path})

    return collection_out, environments_out


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Generate AIAS Postman v3 collection (YAML directory) and environment files."
    )
    parser.add_argument(
        "--base-url",
        default=DEFAULT_BASE_URL,
        help=f"API base URL (default: {DEFAULT_BASE_URL})",
    )
    parser.add_argument(
        "--tenant-base-url",
        default=DEFAULT_TENANT_BASE_URL,
        help=f"Tenant API base URL without /api prefix (default: {DEFAULT_TENANT_BASE_URL})",
    )
    parser.add_argument(
        "--output",
        default=DEFAULT_OUTPUT,
        help=f"Collection output directory (default: {DEFAULT_OUTPUT})",
    )
    parser.add_argument(
        "--collection-id",
        default=STABLE_COLLECTION_ID,
        help="Stable Postman collection ID (keep default for update-in-place imports)",
    )
    parser.add_argument(
        "--extract-folder",
        metavar="FOLDER_NAME",
        help="Print request YAML files for a named folder after generation",
    )
    parser.add_argument(
        "--env-only",
        metavar="ENV",
        choices=["local", "dev", "staging", "production"],
        help="Regenerate only the specified environment file",
    )
    args = parser.parse_args()

    collection_out, resolved_envs = resolve_output_paths(args)

    if not args.env_only:
        write_v3_collection(collection_out, args.base_url, args.tenant_base_url, args.collection_id)
        print(f"Collection written \u2192 {collection_out}  (v3 YAML)")

    env_filter_map = {
        "local": "Local",
        "dev": "Development",
        "staging": "Staging",
        "production": "Production",
    }

    for env_meta in resolved_envs:
        if args.env_only:
            label = env_filter_map[args.env_only]
            if label not in env_meta["name"]:
                continue
        env_path: Path = env_meta["_resolved_path"]
        env_path.parent.mkdir(parents=True, exist_ok=True)
        env_data = build_env_file(env_meta)
        env_path.write_text(json.dumps(env_data, indent=2))
        print(f"Environment written \u2192 {env_path}")

    if not args.env_only:
        print_summary(collection_out)

        if args.extract_folder:
            extract_folder_files(collection_out, args.extract_folder)


if __name__ == "__main__":
    main()

