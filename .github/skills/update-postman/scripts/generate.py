#!/usr/bin/env python3
"""
AIAS Postman Collection Generator
==================================
Generates a Postman Collection v2.1 JSON and four environment files for the
AIAS (Adaptive Intelligent Audit System) — a Laravel 13 multi-tenant API for
audit engagements, compliance tracking, risk assessments, and audit workflows.

Auth:     Laravel Passport — password grant at /oauth/token
Guard:    api  (Bearer token)
Tenancy:  Stancl/Tenancy v3 — InitializeTenancyByPath / InitializeTenancyFromUser

Usage:
    # From MatterMiner repo root (scaffold context):
    python3 docs/prompts/aias/.github/skills/update-postman/scripts/generate.py

    # From AIAS project root:
    python3 docs/prompts/aias/.github/skills/update-postman/scripts/generate.py \\
        --base-url http://localhost:8000/api \\
        --output postman/collections/AIAS_APIS.postman_collection.json

    # Inspect a single folder:
    python3 docs/prompts/aias/.github/skills/update-postman/scripts/generate.py \\
        --extract-folder "Audit Engagements"

Dependencies:
    pip3 install jq  (optional — for post-generation summary only)
    Standard library only required for generation.
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Any, Optional

# ---------------------------------------------------------------------------
# CONFIG
# ---------------------------------------------------------------------------

COLLECTION_NAME = "AIAS API"
COLLECTION_DESCRIPTION = (
    "REST API collection for AIAS — Adaptive Intelligent Audit System. "
    "A multi-tenant Laravel 13 platform for audit engagements, compliance tracking, "
    "risk assessments, and audit workflows. Built with Passport OAuth2 and Stancl Tenancy v3."
)
DEFAULT_BASE_URL = "http://localhost:8000/api"

# Paths are relative to AIAS project root.
# When run from MatterMiner repo, --output can override.
DEFAULT_OUTPUT = "postman/collections/AIAS_APIS.postman_collection.json"

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
        "param": "id",
        "scope": "central",
        "description": "Manage tenants (organisations / audit firms). Super-admin only for create/delete.",
        "sample_body": {
            "name": "Pinnacle Audit Partners",
            "email": "admin@pinnacleaudit.test",
            "password": "Password123!",
            "password_confirmation": "Password123!",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Tenant"},
        ],
    },
    {
        "name": "Continents",
        "route": "continents",
        "param": "continent",
        "scope": "central",
        "description": "Global continent reference data shared across all tenants.",
        "sample_body": {"name": "Africa", "code": "AF", "status": True},
        "extra_actions": [],
    },
    {
        "name": "Countries",
        "route": "countries",
        "param": "country",
        "scope": "central",
        "description": "Global country reference data. Supports continent filter.",
        "sample_body": {
            "name": "Kenya",
            "code": "KE",
            "continent_id": "{{continent_id}}",
            "currency": "KES",
            "currency_sign": "KSh",
            "status": True,
        },
        "extra_actions": [
            {"method": "GET", "path": "select", "name": "Countries Select List", "no_id": True},
        ],
    },
    {
        "name": "Users",
        "route": "users",
        "param": "user",
        "scope": "central",
        "description": "Manage platform users. Supports RBAC roles via Spatie Permission.",
        "sample_body": {
            "first_name": "Jane",
            "last_name": "Auditor",
            "email": "jane.auditor@pinnacleaudit.test",
            "password": "Password123!",
            "password_confirmation": "Password123!",
            "role": "auditor",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST",  "path": "restore",        "name": "Restore User"},
            {"method": "PATCH", "path": "change-password", "name": "Change Password"},
            {"method": "PATCH", "path": "toggle-active",   "name": "Toggle Active Status"},
        ],
    },
    {
        "name": "Roles",
        "route": "roles",
        "param": "role",
        "scope": "central",
        "description": "Manage RBAC roles and permission assignments (Spatie Permission).",
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
        "description": "System-level service accounts for machine-to-machine auth.",
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
        "description": "Manage API keys for service users.",
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
        "description": "Multi-factor authentication management (TOTP-based).",
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
        "description": "Global audit standards reference data (ISA, IIA, GAAS, PCAOB).",
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
        "description": "Global regulatory frameworks (SOX, GDPR, HIPAA, Basel III).",
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
        "description": "Global risk category definitions shared across all tenants.",
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
        "name": "Departments",
        "route": "departments",
        "param": "department",
        "scope": "tenant",
        "description": "Manage internal organisational departments within the audit firm.",
        "sample_body": {"name": "Financial Audit", "code": "FIN-AUDIT", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Department"},
        ],
    },
    {
        "name": "Groups",
        "route": "groups",
        "param": "group",
        "scope": "tenant",
        "description": "Manage user groups for audit team assignments.",
        "sample_body": {"name": "Senior Audit Team", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Group"},
        ],
    },
    {
        "name": "Clients",
        "route": "clients",
        "param": "client",
        "scope": "tenant",
        "description": "Manage audit client entities (organisations being audited).",
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
        "description": "Manage reusable audit program templates.",
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
        "description": "Manage audit engagements (projects) — the top-level audit entity.",
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
        "description": "Manage detailed audit plans for engagements.",
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
        "description": "Manage individual audit steps and procedures within a plan.",
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
        "description": "Manage audit evidence and working paper documentation.",
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
        "description": "Manage audit observations and findings.",
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
        "description": "Manage management responses to audit findings.",
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
        "description": "Manage entity and process risk assessments.",
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
        "description": "Manage risk scoring matrices for engagements.",
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
        "description": "Manage compliance tracking items against regulation frameworks.",
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
        "description": "Manage evidence records for compliance requirements.",
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
        "description": "Manage internal control objective definitions.",
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
        "description": "Manage tests of controls linked to control objectives.",
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
        "description": "Generate and manage audit reports (draft, final, management letter).",
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
        "description": "Manage tenant firm profile and AIAS configuration settings.",
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
        "base_url": "http://localhost:8000/api",
        "user_email": "superadmin@aias.test",
        "user_password": "password",
        "oauth_url": "http://localhost:8000/oauth/token",
    },
    {
        "name": "AIAS Development",
        "file": "postman/environments/AIAS_Development.postman_environment.json",
        "id": "aias-dev-env-00000-0000-0000-000000000002",
        "base_url": "https://dev.aias.app/api",
        "user_email": "dev@aias.app",
        "user_password": "",
        "oauth_url": "https://dev.aias.app/oauth/token",
    },
    {
        "name": "AIAS Staging",
        "file": "postman/environments/AIAS_Staging.postman_environment.json",
        "id": "aias-staging-env-000-0000-0000-000000000003",
        "base_url": "https://staging.aias.app/api",
        "user_email": "staging@aias.app",
        "user_password": "",
        "oauth_url": "https://staging.aias.app/oauth/token",
    },
    {
        "name": "AIAS Production",
        "file": "postman/environments/AIAS_Production.postman_environment.json",
        "id": "aias-prod-env-00000-0000-0000-000000000004",
        "base_url": "https://app.aias.app/api",
        "user_email": "",
        "user_password": "",
        "oauth_url": "https://app.aias.app/oauth/token",
    },
]

# ---------------------------------------------------------------------------
# RESOURCE ID VARIABLES
# All environment files include these; values are auto-populated by test scripts.
# ---------------------------------------------------------------------------

RESOURCE_ID_VARS: list[tuple[str, str, str]] = [
    # --- Auto-populated ---
    ("tenant_id",                   "", "Current tenant ID (auto-populated after login)"),
    ("user_id",                     "", "Authenticated user ID (auto-populated after login)"),
    # --- Global Reference ---
    ("continent_id",                "", "Continent UUID"),
    ("country_id",                  "", "Country UUID"),
    ("audit_standard_id",           "", "Audit Standard UUID"),
    ("regulation_framework_id",     "", "Regulation Framework UUID"),
    ("risk_category_id",            "", "Risk Category UUID"),
    # --- IAM ---
    ("role_id",                     "", "Role UUID"),
    ("service_user_id",             "", "Service User UUID"),
    ("api_key_id",                  "", "API Key UUID"),
    # --- Tenant Reference ---
    ("department_id",               "", "Department UUID"),
    ("group_id",                    "", "Group UUID"),
    ("client_id",                   "", "Client UUID"),
    # --- Audit Templates ---
    ("audit_template_id",           "", "Audit Template UUID"),
    # --- Core Audit ---
    ("audit_engagement_id",         "", "Audit Engagement UUID"),
    ("audit_plan_id",               "", "Audit Plan UUID"),
    ("audit_procedure_id",          "", "Audit Procedure UUID"),
    ("workpaper_id",                "", "Workpaper UUID"),
    ("finding_id",                  "", "Finding UUID"),
    ("finding_response_id",         "", "Finding Response UUID"),
    # --- Risk ---
    ("risk_assessment_id",          "", "Risk Assessment UUID"),
    ("risk_matrix_id",              "", "Risk Matrix UUID"),
    # --- Compliance ---
    ("compliance_requirement_id",   "", "Compliance Requirement UUID"),
    ("compliance_evidence_id",      "", "Compliance Evidence UUID"),
    # --- Controls ---
    ("control_objective_id",        "", "Control Objective UUID"),
    ("control_test_id",             "", "Control Test UUID"),
    # --- Reports ---
    ("report_id",                   "", "Report UUID"),
]

# ---------------------------------------------------------------------------
# BUILDER HELPERS
# ---------------------------------------------------------------------------

STD_HEADERS = [
    {"key": "Accept",       "value": "application/json"},
    {"key": "Content-Type", "value": "application/json"},
]

INDEX_QUERY_PARAMS = [
    {"key": "page",         "value": "1",          "description": "Page number (min: 1)"},
    {"key": "per_page",     "value": "15",          "description": "Items per page (max: 100)"},
    {"key": "search",       "value": "",            "description": "Full-text search term"},
    {"key": "sort_by",      "value": "created_at",  "description": "Sort column"},
    {"key": "sort_order",   "value": "desc",        "description": "asc | desc"},
    {"key": "show_deleted", "value": "0",           "description": "Include soft-deleted: 0|1", "disabled": True},
]

LOGIN_TEST_SCRIPT = [
    "if (pm.response.code === 200) {",
    "    const json = pm.response.json();",
    "    pm.environment.set('access_token', json.access_token);",
    "    pm.environment.set('refresh_token', json.refresh_token);",
    "    console.log('Token saved:', json.access_token.substring(0, 20) + '...');",
    "}",
    "pm.test('Login successful', () => {",
    "    pm.response.to.have.status(200);",
    "    pm.expect(pm.response.json()).to.have.property('access_token');",
    "});",
]

ME_TEST_SCRIPT = [
    "if (pm.response.code === 200) {",
    "    const json = pm.response.json();",
    "    if (json.data) {",
    "        pm.environment.set('user_id', json.data.id);",
    "        pm.environment.set('user_name', (json.data.first_name + ' ' + json.data.last_name).trim());",
    "        if (json.data.tenant_id) {",
    "            pm.environment.set('tenant_id', json.data.tenant_id);",
    "        }",
    "        console.log('User context saved:', json.data.email);",
    "    }",
    "}",
    "pm.test('Me endpoint successful', () => {",
    "    pm.response.to.have.status(200);",
    "});",
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
    if (j.data && j.data.id) {{
        pm.environment.set('{var_name}', j.data.id);
        console.log('Saved {var_name}:', j.data.id);
    }}
}}"""

GENERIC_TEST_SCRIPT = [
    "pm.test('Response is JSON', () => { pm.response.to.be.json; });",
    "pm.test('Status is success', () => {",
    "    const j = pm.response.json();",
    "    pm.expect(j).to.have.property('status', 'success');",
    "});",
]


def make_url(raw: str, path_parts: list[str], query: Optional[list[dict]] = None) -> dict:
    url: dict[str, Any] = {
        "raw": raw,
        "host": ["{{base_url}}"],
        "path": path_parts,
    }
    if query:
        url["query"] = query
    return url


def make_body(payload: dict) -> dict:
    return {
        "mode": "raw",
        "raw": json.dumps(payload, indent=2),
        "options": {"raw": {"language": "json"}},
    }


def make_event(listen: str, exec_lines: list[str]) -> dict:
    return {
        "listen": listen,
        "script": {"type": "text/javascript", "exec": exec_lines},
    }


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


# ---------------------------------------------------------------------------
# REQUEST BUILDERS
# ---------------------------------------------------------------------------


def build_index_request(mod: dict) -> dict:
    route = mod["route"]
    raw_url = f"{{{{base_url}}}}/{route}"
    return {
        "name": f"List {mod['name']}",
        "request": {
            "method": "GET",
            "header": STD_HEADERS,
            "url": make_url(raw_url, [route], INDEX_QUERY_PARAMS),
            "description": f"Retrieve paginated list of {mod['name'].lower()}.",
        },
        "event": [make_event("test", GENERIC_TEST_SCRIPT)],
        "response": [],
    }


def build_store_request(mod: dict) -> dict:
    route = mod["route"]
    raw_url = f"{{{{base_url}}}}/{route}"
    var_name = param_to_var_name(mod["param"]) + "_id"
    create_script = CREATE_TEST_SCRIPT_TEMPLATE.format(var_name=var_name).splitlines()
    return {
        "name": f"Create {singular(mod['name'])}",
        "request": {
            "method": "POST",
            "header": STD_HEADERS,
            "body": make_body(mod.get("sample_body", {})),
            "url": make_url(raw_url, [route]),
            "description": f"Create a new {singular(mod['name']).lower()}.",
        },
        "event": [make_event("test", create_script)],
        "response": [],
    }


def build_show_request(mod: dict) -> dict:
    route = mod["route"]
    param = mod["param"]
    var_name = param_to_var_name(param) + "_id"
    raw_url = f"{{{{base_url}}}}/{route}/{{{{{var_name}}}}}"
    return {
        "name": f"Get {singular(mod['name'])}",
        "request": {
            "method": "GET",
            "header": STD_HEADERS,
            "url": make_url(raw_url, [route, f"{{{{{var_name}}}}}"]),
            "description": f"Retrieve a single {singular(mod['name']).lower()} by ID.",
        },
        "event": [make_event("test", GENERIC_TEST_SCRIPT)],
        "response": [],
    }


def build_update_request(mod: dict) -> dict:
    route = mod["route"]
    param = mod["param"]
    var_name = param_to_var_name(param) + "_id"
    raw_url = f"{{{{base_url}}}}/{route}/{{{{{var_name}}}}}"
    return {
        "name": f"Update {singular(mod['name'])}",
        "request": {
            "method": "PUT",
            "header": STD_HEADERS,
            "body": make_body(mod.get("sample_body", {})),
            "url": make_url(raw_url, [route, f"{{{{{var_name}}}}}"]),
            "description": f"Update an existing {singular(mod['name']).lower()}.",
        },
        "event": [make_event("test", GENERIC_TEST_SCRIPT)],
        "response": [],
    }


def build_destroy_request(mod: dict) -> dict:
    route = mod["route"]
    param = mod["param"]
    var_name = param_to_var_name(param) + "_id"
    raw_url = f"{{{{base_url}}}}/{route}/{{{{{var_name}}}}}"
    return {
        "name": f"Delete {singular(mod['name'])}",
        "request": {
            "method": "DELETE",
            "header": STD_HEADERS,
            "url": make_url(raw_url, [route, f"{{{{{var_name}}}}}"]),
            "description": f"Soft-delete a {singular(mod['name']).lower()}.",
        },
        "event": [make_event("test", GENERIC_TEST_SCRIPT)],
        "response": [],
    }


def build_extra_action_request(mod: dict, action: dict) -> dict:
    route = mod["route"]
    param = mod["param"]
    var_name = param_to_var_name(param) + "_id"
    action_path = action["path"]
    no_id = action.get("no_id", False)
    method = action.get("method", "POST")

    if no_id:
        raw_url = f"{{{{base_url}}}}/{route}/{action_path}"
        path_parts = [route, action_path]
    else:
        raw_url = f"{{{{base_url}}}}/{route}/{{{{{var_name}}}}}/{action_path}"
        path_parts = [route, f"{{{{{var_name}}}}}", action_path]

    request_obj: dict[str, Any] = {
        "method": method,
        "header": STD_HEADERS,
        "url": make_url(raw_url, path_parts),
        "description": action["name"],
    }

    if method in ("POST", "PUT", "PATCH"):
        request_obj["body"] = make_body({})

    return {
        "name": action["name"],
        "request": request_obj,
        "event": [make_event("test", GENERIC_TEST_SCRIPT)],
        "response": [],
    }


# ---------------------------------------------------------------------------
# FOLDER BUILDERS
# ---------------------------------------------------------------------------


def build_auth_folder(base_url: str) -> dict:
    oauth_url = base_url.replace("/api", "") + "/oauth/token"
    return {
        "name": "Auth",
        "description": "Authentication endpoints — obtain / refresh / revoke Passport tokens.",
        "item": [
            {
                "name": "Login",
                "request": {
                    "method": "POST",
                    "header": STD_HEADERS,
                    "body": make_body({
                        "grant_type": "password",
                        "client_id": "{{passport_client_id}}",
                        "client_secret": "{{passport_client_secret}}",
                        "username": "{{user_email}}",
                        "password": "{{user_password}}",
                        "scope": "",
                    }),
                    "url": {
                        "raw": oauth_url,
                        "host": [oauth_url],
                    },
                    "description": "Obtain a Bearer access token (password grant).",
                },
                "event": [make_event("test", LOGIN_TEST_SCRIPT)],
                "response": [],
            },
            {
                "name": "Refresh Token",
                "request": {
                    "method": "POST",
                    "header": STD_HEADERS,
                    "body": make_body({
                        "grant_type": "refresh_token",
                        "client_id": "{{passport_client_id}}",
                        "client_secret": "{{passport_client_secret}}",
                        "refresh_token": "{{refresh_token}}",
                        "scope": "",
                    }),
                    "url": {
                        "raw": oauth_url,
                        "host": [oauth_url],
                    },
                    "description": "Exchange a refresh token for a new access token.",
                },
                "event": [make_event("test", LOGIN_TEST_SCRIPT)],
                "response": [],
            },
            {
                "name": "Get Authenticated User",
                "request": {
                    "method": "GET",
                    "header": STD_HEADERS,
                    "url": make_url(f"{base_url}/me", ["me"]),
                    "description": "Retrieve the currently authenticated user with roles and tenant.",
                },
                "event": [make_event("test", ME_TEST_SCRIPT)],
                "response": [],
            },
            {
                "name": "Logout",
                "request": {
                    "method": "POST",
                    "header": STD_HEADERS,
                    "url": make_url(f"{base_url}/logout", ["logout"]),
                    "description": "Revoke the current access token.",
                },
                "event": [make_event("test", GENERIC_TEST_SCRIPT)],
                "response": [],
            },
        ],
    }


def build_module_folder(mod: dict) -> dict:
    items: list[dict] = []

    if not mod.get("skip_crud", False):
        items.append(build_index_request(mod))
        items.append(build_store_request(mod))
        items.append(build_show_request(mod))
        items.append(build_update_request(mod))
        items.append(build_destroy_request(mod))

    for action in mod.get("extra_actions", []):
        items.append(build_extra_action_request(mod, action))

    return {
        "name": mod["name"],
        "description": mod.get("description", ""),
        "item": items,
    }


# ---------------------------------------------------------------------------
# COLLECTION BUILDER
# ---------------------------------------------------------------------------


def build_collection(base_url: str, collection_id: str) -> dict:
    central_folders: list[dict] = []
    tenant_folders: list[dict] = []

    for mod in MODULES:
        folder = build_module_folder(mod)
        if mod["scope"] == "central":
            central_folders.append(folder)
        else:
            tenant_folders.append(folder)

    central_group = {
        "name": "Central",
        "description": (
            "Cross-tenant (central database) resources: "
            "IAM, reference data, audit standards, and regulation frameworks. "
            "Accessible without tenant context — requires Bearer token only."
        ),
        "item": central_folders,
    }

    tenant_group = {
        "name": "Tenant",
        "description": (
            "Tenant-scoped resources isolated per organisation database. "
            "Requires Bearer token with InitializeTenancyFromUser middleware. "
            "Covers audit engagements, findings, risk, compliance, controls, and reports."
        ),
        "item": tenant_folders,
    }

    return {
        "info": {
            "_postman_id": collection_id,
            "name": COLLECTION_NAME,
            "description": COLLECTION_DESCRIPTION,
            "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        },
        "auth": {
            "type": "bearer",
            "bearer": [{"key": "token", "value": "{{access_token}}", "type": "string"}],
        },
        "item": [build_auth_folder(base_url), central_group, tenant_group],
        "variable": [
            {"key": "base_url", "value": base_url, "type": "string"},
        ],
    }


# ---------------------------------------------------------------------------
# ENVIRONMENT FILE BUILDER
# ---------------------------------------------------------------------------


def build_env_file(env: dict) -> dict:
    base_vars = [
        {"key": "base_url",                "value": env["base_url"],       "enabled": True, "type": "default"},
        {"key": "oauth_url",               "value": env["oauth_url"],      "enabled": True, "type": "default"},
        {"key": "user_email",              "value": env["user_email"],     "enabled": True, "type": "default"},
        {"key": "user_password",           "value": env["user_password"],  "enabled": True, "type": "secret"},
        {"key": "access_token",            "value": "",                    "enabled": True, "type": "secret"},
        {"key": "refresh_token",           "value": "",                    "enabled": True, "type": "secret"},
        {"key": "passport_client_id",      "value": "2",                   "enabled": True, "type": "default"},
        {"key": "passport_client_secret",  "value": "",                    "enabled": True, "type": "secret"},
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
# JQ SUMMARY (optional — requires `pip3 install jq`)
# ---------------------------------------------------------------------------


def jq_summary(collection_path: Path) -> None:
    try:
        import jq as jq_lib
    except ImportError:
        print("\n  Tip: `pip3 install jq` for a post-generation folder/request summary.\n")
        return

    data = json.loads(collection_path.read_text())

    # Count total sub-folders and total requests across two-level hierarchy.
    # Structure: Auth (flat), Central (sub-folders), Tenant (sub-folders).
    total_subfolders = 0
    total_requests = 0
    display_rows: list[tuple[str, int]] = []

    for group in data.get("item", []):
        group_name: str = group.get("name", "")
        group_items: list[dict] = group.get("item", [])

        # Determine if items are sub-folders (have their own "item") or direct requests
        if group_items and "item" in group_items[0]:
            # Central / Tenant groups — items are sub-folders
            display_rows.append((f"── {group_name} ──", -1))
            for subfolder in group_items:
                req_count = len(subfolder.get("item", []))
                total_subfolders += 1
                total_requests += req_count
                display_rows.append((f"  {subfolder['name']}", req_count))
        else:
            # Auth folder — items are direct requests
            req_count = len(group_items)
            total_requests += req_count
            display_rows.append((group_name, req_count))

    line = "\u2500" * 60
    print(f"\n{line}")
    print(f"  Collection  : {COLLECTION_NAME}")
    print(f"  Sub-Folders : {total_subfolders}")
    print(f"  Requests    : {total_requests}")
    print(f"{line}")

    for name, count in display_rows:
        if count == -1:
            # Section header
            print(f"\n  {name}")
        else:
            print(f"  {name:<30}{count:>3}")

    print(f"{line}\n")


def jq_extract_folder(collection_path: Path, folder_name: str) -> None:
    try:
        import jq as jq_lib
    except ImportError:
        print("jq not installed. Run: pip3 install jq")
        return

    data = json.loads(collection_path.read_text())
    query = f'.item[] | select(.name == "{folder_name}")'
    result = jq_lib.first(query, data)
    if result is None:
        print(f"Folder not found: {folder_name}")
        return
    print(json.dumps(result, indent=2))


# ---------------------------------------------------------------------------
# MAIN
# ---------------------------------------------------------------------------


def resolve_output_paths(args: argparse.Namespace) -> tuple[Path, list[dict]]:
    """
    Resolve the collection output path and environment file paths.
    When run from the MatterMiner repo scaffold context, prepend docs/prompts/aias/.
    """
    script_dir = Path(__file__).resolve().parent
    # .github/skills/update-postman/scripts/generate.py
    # → project root is 4 levels up when inside AIAS project
    # → when inside MatterMiner scaffold: docs/prompts/aias is the AIAS project root
    aias_root = script_dir.parent.parent.parent.parent  # up from scripts/

    # Detect scaffold context: if the parent of aias_root contains PROMPT.md
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
        description="Generate AIAS Postman collection and environment files."
    )
    parser.add_argument(
        "--base-url",
        default=DEFAULT_BASE_URL,
        help=f"API base URL (default: {DEFAULT_BASE_URL})",
    )
    parser.add_argument(
        "--output",
        default=DEFAULT_OUTPUT,
        help=f"Collection output path (default: {DEFAULT_OUTPUT})",
    )
    parser.add_argument(
        "--collection-id",
        default=STABLE_COLLECTION_ID,
        help="Stable Postman collection ID (keep default for update-in-place imports)",
    )
    parser.add_argument(
        "--extract-folder",
        metavar="FOLDER_NAME",
        help="Print a single folder's JSON after generation (requires jq)",
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
        # Generate collection
        collection_out.parent.mkdir(parents=True, exist_ok=True)
        collection = build_collection(args.base_url, args.collection_id)
        collection_out.write_text(json.dumps(collection, indent=2))
        print(f"Collection written \u2192 {collection_out}")

    # Generate environment files
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
        jq_summary(collection_out)

        if args.extract_folder:
            jq_extract_folder(collection_out, args.extract_folder)


if __name__ == "__main__":
    main()
