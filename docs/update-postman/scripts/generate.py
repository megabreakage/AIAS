#!/usr/bin/env python3
"""
AirTrafficEng Postman Collection Generator
==========================================
Generates a Postman Collection v2.1 JSON for the AirTrafficEng Laravel 12 REST API.

Usage:
    python generate.py [--base-url URL] [--output PATH] [--collection-id UUID]

Examples:
    python .claude/skills/update-postman-skill/scripts/generate.py
    python .claude/skills/update-postman-skill/scripts/generate.py \
        --base-url "http://localhost:8000/api" \
        --output docs/AirTrafficEng.postman_collection.json

Dependencies:
    pip install jq --break-system-packages   (optional — for post-generation querying)
    Standard library only required for generation.
"""

import argparse
import json
import sys
import uuid
from pathlib import Path
from typing import Any

# ---------------------------------------------------------------------------
# CONFIG — edit MODULES to add / remove resources
# ---------------------------------------------------------------------------

COLLECTION_NAME = "AirTrafficEng API"
COLLECTION_DESCRIPTION = (
    "REST API collection for AirTrafficEng — a comprehensive aviation operations "
    "and maintenance management platform built with Laravel 12 + Passport OAuth2."
)
DEFAULT_BASE_URL = "http://localhost:8000/api"
DEFAULT_OUTPUT = "docs/AirTrafficEng.postman_collection.json"
# Keep this stable so Postman recognises re-imports as updates, not new collections.
STABLE_COLLECTION_ID = "a1b2c3d4-e5f6-7890-abcd-ef1234567890"

# Each module entry describes one API resource folder.
# Fields:
#   name         — Human-readable folder/resource name (singular for actions, plural shown in folder)
#   route        — URL segment, e.g. "continents" → /api/continents
#   param        — Route parameter name (snake_case), e.g. "continent" → {{continent_identifier}}
#   description  — Folder-level description (optional)
#   sample_body  — Dict used for Create / Update request bodies
#   extra_actions — List of dicts for non-CRUD endpoints:
#                   { "method": "POST", "path": "restore", "name": "Restore X", "body": None }
#   nested       — True if this is a shallow-nested resource (special URL construction)
#   parent_route — Required when nested=True, e.g. "work-orders"
#   parent_param — Required when nested=True, e.g. "work_order"

MODULES: list[dict[str, Any]] = [
    # ── Reference / Lookup data ────────────────────────────────────────────
    {
        "name": "Continents",
        "route": "continents",
        "param": "continent",
        "description": "Manage continent reference records.",
        "sample_body": {"name": "Africa", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Continent"},
        ],
    },
    {
        "name": "Countries",
        "route": "countries",
        "param": "country",
        "description": "Manage country records, associated with continents.",
        "sample_body": {"name": "Kenya", "code": "KE", "continent_id": "{{continent_identifier}}", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Country"},
        ],
    },
    {
        "name": "Departments",
        "route": "departments",
        "param": "department",
        "description": "Manage internal organisational departments.",
        "sample_body": {"name": "Engineering", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Department"},
        ],
    },
    {
        "name": "License Categories",
        "route": "license-categories",
        "param": "license_category",
        "description": "Manage aviation license categories (e.g. ATPL, CPL, PPL).",
        "sample_body": {"name": "ATPL", "description": "Airline Transport Pilot Licence", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore License Category"},
        ],
    },
    {
        "name": "License Types",
        "route": "license-types",
        "param": "license_type",
        "description": "Manage license types within license categories.",
        "sample_body": {"name": "Multi-Engine", "license_category_id": "{{license_category_identifier}}", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore License Type"},
        ],
    },
    {
        "name": "Internal Authorizations",
        "route": "internal-authorizations",
        "param": "internal_authorization",
        "description": "Manage internal maintenance authorizations.",
        "sample_body": {"name": "Line Maintenance Auth A", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Internal Authorization"},
        ],
    },
    {
        "name": "Engine Trend Types",
        "route": "engine-trend-types",
        "param": "engine_trend_type",
        "description": "Manage engine trend monitoring type definitions.",
        "sample_body": {"name": "EGT", "unit": "°C", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Engine Trend Type"},
        ],
    },
    {
        "name": "Inspection Types",
        "route": "inspection-types",
        "param": "inspection_type",
        "description": "Manage aircraft inspection type definitions.",
        "sample_body": {"name": "100-Hour Inspection", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Inspection Type"},
        ],
    },
    {
        "name": "Airport Codes",
        "route": "airport-codes",
        "param": "airport_code",
        "description": "Manage ICAO/IATA airport code records.",
        "sample_body": {"icao": "HKJK", "iata": "NBO", "name": "Jomo Kenyatta International Airport", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Airport Code"},
        ],
    },
    {
        "name": "Maintenance Types",
        "route": "maintenance-types",
        "param": "maintenance_type",
        "description": "Manage maintenance type definitions.",
        "sample_body": {"name": "Scheduled Maintenance", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Maintenance Type"},
        ],
    },
    {
        "name": "Task Categories",
        "route": "task-categories",
        "param": "task_category",
        "description": "Manage work order task category definitions.",
        "sample_body": {"name": "Avionics", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Task Category"},
        ],
    },
    # ── Staff & Crew ───────────────────────────────────────────────────────
    {
        "name": "Users",
        "route": "users",
        "param": "user",
        "description": "Manage platform users. Supports RBAC roles via Spatie Permission.",
        "sample_body": {
            "first_name": "John",
            "last_name": "Doe",
            "email": "john.doe@airtrafficeng.test",
            "password": "Password123!",
            "password_confirmation": "Password123!",
            "role": "admin",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore",      "name": "Restore User"},
            {"method": "POST", "path": "activate",     "name": "Activate User"},
            {"method": "POST", "path": "deactivate",   "name": "Deactivate User"},
            {"method": "POST", "path": "verify-email", "name": "Verify User Email"},
        ],
    },
    {
        "name": "Certifying Staff",
        "route": "certifying-staff",
        "param": "certifying_staff",
        "description": "Manage aircraft certifying staff (Part-66 licensed engineers).",
        "sample_body": {
            "user_id": "{{user_identifier}}",
            "license_number": "KE-AME-001",
            "license_category_id": "{{license_category_identifier}}",
            "expiry_date": "2026-12-31",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Certifying Staff"},
        ],
    },
    {
        "name": "Crew Duty Times",
        "route": "crew-duty-times",
        "param": "crew_duty_time",
        "description": "Log and manage crew duty time records.",
        "sample_body": {
            "user_id": "{{user_identifier}}",
            "duty_start": "2024-01-15T06:00:00Z",
            "duty_end": "2024-01-15T14:00:00Z",
            "notes": "Regular duty shift",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Crew Duty Time"},
        ],
    },
    # ── Training ───────────────────────────────────────────────────────────
    {
        "name": "Training Courses",
        "route": "training-courses",
        "param": "training_course",
        "description": "Manage crew training course definitions.",
        "sample_body": {"name": "CRM Training", "duration_hours": 8, "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Training Course"},
        ],
    },
    {
        "name": "Trainings",
        "route": "trainings",
        "param": "training",
        "description": "Manage individual crew training records.",
        "sample_body": {
            "user_id": "{{user_identifier}}",
            "training_course_id": "{{training_course_identifier}}",
            "completed_at": "2024-01-10",
            "expiry_date": "2026-01-10",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Training"},
        ],
    },
    # ── Leave ──────────────────────────────────────────────────────────────
    {
        "name": "Leave Types",
        "route": "leave-types",
        "param": "leave_type",
        "description": "Manage leave type definitions (Annual, Sick, etc.).",
        "sample_body": {"name": "Annual Leave", "max_days": 21, "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Leave Type"},
        ],
    },
    {
        "name": "Leave Requests",
        "route": "leave-requests",
        "param": "leave_request",
        "description": "Manage staff leave requests and approvals.",
        "sample_body": {
            "user_id": "{{user_identifier}}",
            "leave_type_id": "{{leave_type_identifier}}",
            "start_date": "2024-03-01",
            "end_date": "2024-03-07",
            "reason": "Annual family vacation",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Leave Request"},
        ],
    },
    # ── Documents ──────────────────────────────────────────────────────────
    {
        "name": "Document Categories",
        "route": "document-categories",
        "param": "document_category",
        "description": "Manage document category definitions.",
        "sample_body": {"name": "Airworthiness Directives", "status": True},
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Document Category"},
        ],
    },
    {
        "name": "Documents",
        "route": "documents",
        "param": "document",
        "description": "Manage technical and regulatory documents.",
        "sample_body": {
            "title": "AD 2024-001",
            "document_category_id": "{{document_category_identifier}}",
            "reference": "FAA-AD-2024-001",
            "effective_date": "2024-01-15",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Document"},
        ],
    },
    # ── Operations ─────────────────────────────────────────────────────────
    {
        "name": "Flight Technical Logs",
        "route": "flight-technical-logs",
        "param": "flight_technical_log",
        "description": "Manage aircraft flight technical log entries.",
        "sample_body": {
            "aircraft_id": "{{aircraft_identifier}}",
            "flight_date": "2024-01-15",
            "departure": "HKJK",
            "arrival": "HTDA",
            "flight_hours": 2.5,
            "cycles": 1,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Flight Technical Log"},
        ],
    },
    {
        "name": "Pilot Reports",
        "route": "pilot-reports",
        "param": "pilot_report",
        "description": "Manage pilot safety and technical reports (PIREPs).",
        "sample_body": {
            "user_id": "{{user_identifier}}",
            "aircraft_id": "{{aircraft_identifier}}",
            "report_date": "2024-01-15",
            "description": "Minor fuel leak observed on left engine nacelle.",
            "severity": "low",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Pilot Report"},
        ],
    },
    {
        "name": "Propellers",
        "route": "propellers",
        "param": "propeller",
        "description": "Manage propeller component records.",
        "sample_body": {
            "serial_number": "PROP-001",
            "model": "Hartzell HC-C2YR",
            "aircraft_id": "{{aircraft_identifier}}",
            "total_hours": 0,
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Propeller"},
        ],
    },
    # ── Workshops & Clients ────────────────────────────────────────────────
    {
        "name": "Workshops",
        "route": "workshops",
        "param": "workshop",
        "description": "Manage external maintenance workshop records.",
        "sample_body": {
            "name": "Nairobi Avionics Workshop",
            "contact_email": "info@nairobiavionic.co.ke",
            "phone": "+254700000000",
            "country_id": "{{country_identifier}}",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Workshop"},
        ],
    },
    {
        "name": "Clients",
        "route": "clients",
        "param": "client",
        "description": "Manage airline client records.",
        "sample_body": {
            "name": "Kenya Airways",
            "code": "KQ",
            "contact_email": "maintenance@kenya-airways.com",
            "status": True,
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Client"},
        ],
    },
    # ── Work Orders ────────────────────────────────────────────────────────
    {
        "name": "Work Orders",
        "route": "work-orders",
        "param": "work_order",
        "description": "Manage aircraft maintenance work orders.",
        "sample_body": {
            "aircraft_id": "{{aircraft_identifier}}",
            "maintenance_type_id": "{{maintenance_type_identifier}}",
            "client_id": "{{client_identifier}}",
            "scheduled_date": "2024-02-01",
            "description": "100-hour scheduled inspection",
            "status": "open",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Work Order"},
        ],
    },
    {
        "name": "Work Order Details",
        "route": "work-orders/{work_order_identifier}/details",
        "param": "detail",
        "description": "Manage individual task line-items on a work order (shallow nested resource).",
        "sample_body": {
            "task_category_id": "{{task_category_identifier}}",
            "description": "Replace brake pads — left main gear",
            "part_number": "BP-737-001",
            "quantity": 2,
            "labour_hours": 1.5,
        },
        "extra_actions": [],
        "nested": True,
        "parent_route": "work-orders",
        "parent_param": "work_order",
        # Shallow: store on parent, update/delete directly on child
        "shallow": True,
    },
    # ── Schedules & Work Packs ─────────────────────────────────────────────
    {
        "name": "Schedules",
        "route": "schedules",
        "param": "schedule",
        "description": "Manage aircraft maintenance schedules.",
        "sample_body": {
            "aircraft_id": "{{aircraft_identifier}}",
            "schedule_type_id": "{{schedule_type_identifier}}",
            "due_date": "2024-03-15",
            "status": "pending",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Schedule"},
        ],
    },
    {
        "name": "Work Packs",
        "route": "work-packs",
        "param": "work_pack",
        "description": "Manage maintenance work packs (groupings of scheduled tasks).",
        "sample_body": {
            "name": "WP-2024-001",
            "schedule_id": "{{schedule_identifier}}",
            "description": "Q1 2024 Scheduled Maintenance Pack",
            "status": "open",
        },
        "extra_actions": [
            {"method": "POST", "path": "restore", "name": "Restore Work Pack"},
        ],
    },
]

# ---------------------------------------------------------------------------
# BUILDER HELPERS
# ---------------------------------------------------------------------------

STD_HEADERS = [
    {"key": "Accept",       "value": "application/json"},
    {"key": "Content-Type", "value": "application/json"},
]

INDEX_QUERY_PARAMS = [
    {"key": "page",         "value": "1",    "description": "Page number (min: 1)"},
    {"key": "per_page",     "value": "15",   "description": "Items per page (max: 100)"},
    {"key": "search",       "value": "",     "description": "Full-text search term"},
    {"key": "sort_by",      "value": "name", "description": "Column to sort (name|status|created_at|updated_at)"},
    {"key": "sort_order",   "value": "asc",  "description": "Sort direction: asc | desc"},
    {"key": "show_deleted", "value": "0",    "description": "Include soft-deleted: 0|1", "disabled": True},
]

LOGIN_TEST_SCRIPT = [
    "if (pm.response.code === 200) {",
    "    const json = pm.response.json();",
    "    pm.environment.set('token', json.access_token);",
    "    pm.environment.set('refresh_token', json.refresh_token);",
    "    console.log('Token saved:', json.access_token.substring(0, 20) + '...');",
    "}",
    "pm.test('Login successful', () => {",
    "    pm.response.to.have.status(200);",
    "    pm.expect(pm.response.json()).to.have.property('access_token');",
    "});",
]

GENERIC_TEST_SCRIPT = [
    "pm.test('Response is JSON', () => { pm.response.to.be.json; });",
    "pm.test('Status is success', () => {",
    "    pm.expect(pm.response.json().status).to.eql('success');",
    "});",
]


def make_url(raw: str, path_parts: list[str], query: list[dict] | None = None) -> dict:
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


def singular(name: str) -> str:
    """Very basic singularisation for display names."""
    if name.endswith("ies"):
        return name[:-3] + "y"
    if name.endswith("s") and not name.endswith("ss"):
        return name[:-1]
    return name


# ---------------------------------------------------------------------------
# REQUEST BUILDERS
# ---------------------------------------------------------------------------

def build_index_request(module: dict, base_url: str) -> dict:
    route = module["route"]
    folder_name = module["name"]
    query = [dict(p) for p in INDEX_QUERY_PARAMS]  # copy so we can mutate

    raw_url = f"{base_url}/{route}?page=1&per_page=15&search=&sort_by=name&sort_order=asc"
    return {
        "name": f"List {folder_name}",
        "request": {
            "method": "GET",
            "header": list(STD_HEADERS),
            "url": make_url(raw_url, [route], query),
            "description": f"Returns a paginated list of {folder_name.lower()}.",
        },
        "response": [],
    }


def build_store_request(module: dict, base_url: str) -> dict:
    route = module["route"]
    single = singular(module["name"])
    return {
        "name": f"Create {single}",
        "request": {
            "method": "POST",
            "header": list(STD_HEADERS),
            "body": make_body(module["sample_body"]),
            "url": make_url(f"{base_url}/{route}", [route]),
            "description": f"Create a new {single.lower()} record. Returns 201 on success.",
        },
        "response": [],
    }


def build_show_request(module: dict, base_url: str) -> dict:
    route = module["route"]
    param = module["param"]
    single = singular(module["name"])
    var = f"{{{{{param}_identifier}}}}"
    raw = f"{base_url}/{route}/{var}"
    return {
        "name": f"Get {single}",
        "request": {
            "method": "GET",
            "header": list(STD_HEADERS),
            "url": make_url(raw, [route, var]),
            "description": f"Retrieve a single {single.lower()} by UUID identifier.",
        },
        "response": [],
    }


def build_update_request(module: dict, base_url: str) -> dict:
    route = module["route"]
    param = module["param"]
    single = singular(module["name"])
    var = f"{{{{{param}_identifier}}}}"
    raw = f"{base_url}/{route}/{var}"
    return {
        "name": f"Update {single}",
        "request": {
            "method": "PUT",
            "header": list(STD_HEADERS),
            "body": make_body(module["sample_body"]),
            "url": make_url(raw, [route, var]),
            "description": f"Update an existing {single.lower()}. Only include fields to change (uses `sometimes` validation).",
        },
        "response": [],
    }


def build_destroy_request(module: dict, base_url: str) -> dict:
    route = module["route"]
    param = module["param"]
    single = singular(module["name"])
    var = f"{{{{{param}_identifier}}}}"
    raw = f"{base_url}/{route}/{var}"
    return {
        "name": f"Delete {single}",
        "request": {
            "method": "DELETE",
            "header": list(STD_HEADERS),
            "url": make_url(raw, [route, var]),
            "description": f"Soft-delete a {single.lower()}. Record can be restored via the Restore endpoint.",
        },
        "response": [],
    }


def build_extra_action_request(module: dict, action: dict, base_url: str) -> dict:
    route = module["route"]
    param = module["param"]
    var = f"{{{{{param}_identifier}}}}"
    path_parts = [route, var, action["path"]]
    raw = f"{base_url}/{route}/{var}/{action['path']}"

    req: dict[str, Any] = {
        "name": action["name"],
        "request": {
            "method": action["method"],
            "header": list(STD_HEADERS),
            "url": make_url(raw, path_parts),
        },
        "response": [],
    }
    if action.get("body"):
        req["request"]["body"] = make_body(action["body"])
    if action.get("description"):
        req["request"]["description"] = action["description"]
    return req


def build_nested_requests(module: dict, base_url: str) -> list[dict]:
    """Build requests for shallow-nested resources (e.g. Work Order Details)."""
    parent_route = module["parent_route"]
    parent_param = module["parent_param"]
    param = module["param"]
    single = singular(module["name"])

    parent_var = f"{{{{{parent_param}_identifier}}}}"
    child_var = f"{{{{{param}_identifier}}}}"

    # Store: POST /work-orders/{id}/details
    store_raw = f"{base_url}/{parent_route}/{parent_var}/details"
    store_req = {
        "name": f"Create {single}",
        "request": {
            "method": "POST",
            "header": list(STD_HEADERS),
            "body": make_body(module["sample_body"]),
            "url": make_url(store_raw, [parent_route, parent_var, "details"]),
            "description": f"Create a new {single.lower()} under a work order.",
        },
        "response": [],
    }

    # Update: PUT /details/{id}  (shallow)
    update_raw = f"{base_url}/details/{child_var}"
    update_req = {
        "name": f"Update {single}",
        "request": {
            "method": "PUT",
            "header": list(STD_HEADERS),
            "body": make_body(module["sample_body"]),
            "url": make_url(update_raw, ["details", child_var]),
            "description": f"Update an existing {single.lower()} (shallow route).",
        },
        "response": [],
    }

    # Destroy: DELETE /details/{id}  (shallow)
    destroy_raw = f"{base_url}/details/{child_var}"
    destroy_req = {
        "name": f"Delete {single}",
        "request": {
            "method": "DELETE",
            "header": list(STD_HEADERS),
            "url": make_url(destroy_raw, ["details", child_var]),
            "description": f"Delete a {single.lower()} (shallow route).",
        },
        "response": [],
    }

    return [store_req, update_req, destroy_req]


# ---------------------------------------------------------------------------
# AUTH FOLDER
# ---------------------------------------------------------------------------

def build_auth_folder(base_url: str) -> dict:
    # The oauth/token endpoint is at /oauth/token — one level above /api/
    oauth_url = base_url.rstrip("/api").rstrip("/") + "/oauth/token" if "/api" in base_url else base_url + "/../oauth/token"

    login = {
        "name": "Login",
        "event": [make_event("test", LOGIN_TEST_SCRIPT)],
        "request": {
            "method": "POST",
            "header": [
                {"key": "Accept",       "value": "application/json"},
                {"key": "Content-Type", "value": "application/json"},
            ],
            "body": {
                "mode": "raw",
                "raw": json.dumps({
                    "grant_type":    "password",
                    "client_id":     "{{client_id}}",
                    "client_secret": "{{client_secret}}",
                    "username":      "{{username}}",
                    "password":      "{{password}}",
                    "scope":         "*",
                }, indent=2),
                "options": {"raw": {"language": "json"}},
            },
            "url": {
                "raw": oauth_url,
                "host": [oauth_url],
                "path": [],
            },
            "description": "Exchange credentials for a Bearer access token (Passport Password Grant). Saves `token` and `refresh_token` to the active environment automatically.",
        },
        "response": [],
    }

    refresh = {
        "name": "Refresh Token",
        "event": [make_event("test", [
            "if (pm.response.code === 200) {",
            "    const json = pm.response.json();",
            "    pm.environment.set('token', json.access_token);",
            "    pm.environment.set('refresh_token', json.refresh_token);",
            "}",
            "pm.test('Token refreshed', () => { pm.response.to.have.status(200); });",
        ])],
        "request": {
            "method": "POST",
            "header": [
                {"key": "Accept",       "value": "application/json"},
                {"key": "Content-Type", "value": "application/json"},
            ],
            "body": {
                "mode": "raw",
                "raw": json.dumps({
                    "grant_type":    "refresh_token",
                    "refresh_token": "{{refresh_token}}",
                    "client_id":     "{{client_id}}",
                    "client_secret": "{{client_secret}}",
                    "scope":         "*",
                }, indent=2),
                "options": {"raw": {"language": "json"}},
            },
            "url": {
                "raw": oauth_url,
                "host": [oauth_url],
                "path": [],
            },
            "description": "Use a refresh token to obtain a new access token.",
        },
        "response": [],
    }

    me = {
        "name": "Get Authenticated User",
        "request": {
            "method": "GET",
            "header": [{"key": "Accept", "value": "application/json"}],
            "url": make_url(f"{base_url}/user", ["user"]),
            "description": "Return the currently authenticated user's profile.",
        },
        "response": [],
    }

    return {
        "name": "Auth",
        "description": "OAuth2 Password Grant authentication via Laravel Passport.",
        "auth": {"type": "noauth"},
        "item": [login, refresh, me],
    }


# ---------------------------------------------------------------------------
# MODULE FOLDER BUILDER
# ---------------------------------------------------------------------------

def build_module_folder(module: dict, base_url: str) -> dict:
    items: list[dict] = []

    if module.get("nested"):
        items.extend(build_nested_requests(module, base_url))
    else:
        items.append(build_index_request(module, base_url))
        items.append(build_store_request(module, base_url))
        items.append(build_show_request(module, base_url))
        items.append(build_update_request(module, base_url))
        items.append(build_destroy_request(module, base_url))
        for action in module.get("extra_actions", []):
            items.append(build_extra_action_request(module, action, base_url))

    return {
        "name": module["name"],
        "description": module.get("description", ""),
        "item": items,
    }


# ---------------------------------------------------------------------------
# COLLECTION ASSEMBLER
# ---------------------------------------------------------------------------

def build_collection(base_url: str, collection_id: str) -> dict:
    folders: list[dict] = [build_auth_folder(base_url)]
    for module in MODULES:
        folders.append(build_module_folder(module, base_url))

    return {
        "info": {
            "_postman_id": collection_id,
            "name": COLLECTION_NAME,
            "description": COLLECTION_DESCRIPTION,
            "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        },
        "auth": {
            "type": "bearer",
            "bearer": [
                {"key": "token", "value": "{{token}}", "type": "string"}
            ],
        },
        "variable": [
            {"key": "base_url",      "value": base_url,                        "type": "string"},
            {"key": "token",         "value": "",                               "type": "string"},
            {"key": "client_id",     "value": "2",                              "type": "string"},
            {"key": "client_secret", "value": "",                               "type": "string"},
            {"key": "username",      "value": "admin@airtrafficeng.test",       "type": "string"},
            {"key": "password",      "value": "password",                       "type": "string"},
        ],
        "item": folders,
    }


# ---------------------------------------------------------------------------
# JQ QUERY HELPERS  (requires: pip install jq --break-system-packages)
# ---------------------------------------------------------------------------

def jq_summary(collection_path: str) -> None:
    """Use jq to print a concise summary of the generated collection."""
    try:
        import jq  # type: ignore

        with open(collection_path) as f:
            data = json.load(f)

        folders = jq.compile(".item | length").input(data).first()
        total_requests = jq.compile("[.item[].item | length] | add").input(data).first()
        folder_names = jq.compile("[.item[].name]").input(data).first()

        print(f"\n{'─' * 50}")
        print(f"  Collection : {data['info']['name']}")
        print(f"  Folders    : {folders}")
        print(f"  Requests   : {total_requests}")
        print(f"{'─' * 50}")
        for name in folder_names:
            count = jq.compile(f'.item[] | select(.name == "{name}") | .item | length').input(data).first()
            print(f"  {name:<35} {count} requests")
        print(f"{'─' * 50}\n")

    except ImportError:
        # jq not installed — skip summary
        print("\n  Tip: install jq for a detailed summary → pip install jq --break-system-packages\n")
    except Exception as exc:  # noqa: BLE001
        print(f"\n  jq summary failed: {exc}\n")


def jq_extract_folder(collection_path: str, folder_name: str) -> None:
    """Extract and print a single folder's requests using jq (useful for debugging)."""
    try:
        import jq  # type: ignore

        with open(collection_path) as f:
            data = json.load(f)

        result = (
            jq.compile(f'.item[] | select(.name == "{folder_name}")')
            .input(data)
            .first()
        )
        print(json.dumps(result, indent=2))
    except ImportError:
        print("jq library not installed. Run: pip install jq --break-system-packages")
    except StopIteration:
        print(f"Folder '{folder_name}' not found in collection.")


# ---------------------------------------------------------------------------
# CLI ENTRY POINT
# ---------------------------------------------------------------------------

def main() -> None:
    parser = argparse.ArgumentParser(
        description="Generate a Postman Collection v2.1 for AirTrafficEng.",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    parser.add_argument(
        "--base-url",
        default=DEFAULT_BASE_URL,
        help=f"API base URL without trailing slash (default: {DEFAULT_BASE_URL})",
    )
    parser.add_argument(
        "--output",
        default=DEFAULT_OUTPUT,
        help=f"Output file path (default: {DEFAULT_OUTPUT})",
    )
    parser.add_argument(
        "--collection-id",
        default=STABLE_COLLECTION_ID,
        help="Stable UUID for the collection (keep fixed for Postman merge-imports)",
    )
    parser.add_argument(
        "--extract-folder",
        metavar="FOLDER_NAME",
        default=None,
        help="Print a single folder's JSON using jq (for debugging)",
    )
    args = parser.parse_args()

    output_path = Path(args.output)
    output_path.parent.mkdir(parents=True, exist_ok=True)

    collection = build_collection(
        base_url=args.base_url.rstrip("/"),
        collection_id=args.collection_id,
    )

    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(collection, f, indent=2, ensure_ascii=False)
        f.write("\n")  # trailing newline for git-friendliness

    print(f"✓ Collection written to: {output_path.resolve()}")

    if args.extract_folder:
        jq_extract_folder(str(output_path), args.extract_folder)
    else:
        jq_summary(str(output_path))


if __name__ == "__main__":
    main()
