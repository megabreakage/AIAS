---
name: update-postman-skill
description: "Use this skill when the user wants to generate, update, or maintain a Postman collection for the AirTrafficEng REST API. Triggers include: 'update postman', 'generate postman collection', 'add postman requests', 'sync postman with routes', 'postman collection', 'add to postman', or any request that involves creating or modifying API request collections. This skill produces a valid Postman Collection v2.1 JSON file with all CRUD endpoints, auth headers, environment variables, and pre-request scripts. Use it alongside new module scaffolding to keep the collection in sync."
---

# update-postman-skill

Generates and maintains a Postman Collection (v2.1) for the **AirTrafficEng** Laravel 12 REST API.

---

## Quick Reference

| Task | Approach |
|------|----------|
| Generate full collection from scratch | Run `scripts/generate.py` |
| Add a new resource/module | Add a folder entry to `MODULES` in `generate.py`, then re-run |
| Update auth configuration | Edit the `auth` block in `generate.py` |
| Review request format | See `examples/auth-management.md` |
| Postman Collection spec details | See `reference.md` |

---

## Files in This Skill

```
update-postman-skill/
├── SKILL.md                     ← You are here — overview & navigation
├── reference.md                 ← Full Postman Collection v2.1 spec & patterns
├── examples/
│   └── auth-management.md       ← Sample folder JSON for Auth Management CRUD
└── scripts/
    └── generate.py              ← Python generator script (produces .json output)
```

---

## Workflow

### 1. Read context files first
Before generating or editing a collection, read:
- `reference.md` — for spec details, variable conventions, auth patterns
- `examples/auth-management.md` — for the canonical folder/request format

### 2. Identify what has changed
- New route file added? → add matching entry to `MODULES` in `generate.py`
- New custom action (e.g. `/restore`, `/activate`)? → add to the module's `extra_actions` list
- Auth scheme changed? → update the `auth` block constants in `generate.py`

### 3. Run the generator
```bash
cd /path/to/project
python .claude/skills/update-postman-skill/scripts/generate.py \
    --base-url "{{base_url}}" \
    --output docs/AirTrafficEng.postman_collection.json
```

### 4. Validate output
```bash
# Confirm valid JSON
python -m json.tool docs/AirTrafficEng.postman_collection.json > /dev/null && echo "Valid JSON"

# Check item count matches expected modules
python -c "
import json
with open('docs/AirTrafficEng.postman_collection.json') as f:
    col = json.load(f)
folders = col['item']
print(f'Folders: {len(folders)}')
total = sum(len(f[\"item\"]) for f in folders)
print(f'Total requests: {total}')
"
```

### 5. Import into Postman
- Open Postman → Import → Upload file → select `docs/AirTrafficEng.postman_collection.json`
- Set environment variables: `base_url`, `token`, `client_id`, `client_secret`, `username`, `password`

---

## Environment Variables (required in Postman)

| Variable | Example value | Purpose |
|----------|---------------|---------|
| `base_url` | `http://localhost:8000/api` | API root |
| `token` | _(auto-set by auth request)_ | Bearer token |
| `client_id` | `2` | Passport password-grant client ID |
| `client_secret` | `secret` | Passport client secret |
| `username` | `admin@airtrafficeng.test` | Test user email |
| `password` | `password` | Test user password |

---

## Collection Structure

```
AirTrafficEng API
├── Auth                         ← Login / Logout / Refresh
├── Users                        ← CRUD + restore + activate/deactivate
├── Continents                   ← CRUD + restore
├── Countries                    ← CRUD + restore
├── Departments                  ← CRUD + restore
├── License Categories           ← CRUD + restore
├── License Types                ← CRUD + restore
├── Internal Authorizations      ← CRUD + restore
├── Certifying Staff             ← CRUD + restore
├── Document Categories          ← CRUD + restore
├── Documents                    ← CRUD + restore
├── Training Courses             ← CRUD + restore
├── Trainings                    ← CRUD + restore
├── Engine Trend Types           ← CRUD + restore
├── Inspection Types             ← CRUD + restore
├── Airport Codes                ← CRUD + restore
├── Flight Technical Logs        ← CRUD + restore
├── Maintenance Types            ← CRUD + restore
├── Workshops                    ← CRUD + restore
├── Clients                      ← CRUD + restore
├── Work Orders                  ← CRUD + restore
├── Work Order Details           ← store / update / destroy (shallow nested)
├── Task Categories              ← CRUD + restore
├── Schedules                    ← CRUD + restore
├── Work Packs                   ← CRUD + restore
├── Pilot Reports                ← CRUD + restore
├── Propellers                   ← CRUD + restore
├── Leave Types                  ← CRUD + restore
├── Leave Requests               ← CRUD + restore
├── Crew Duty Times              ← CRUD + restore
└── ...                          ← future modules added via MODULES list
```

---

## Adding a New Module

1. Open `scripts/generate.py`
2. Append to the `MODULES` list:
```python
{
    "name": "My New Resource",
    "route": "my-new-resources",
    "param": "my_new_resource",        # route parameter name (snake_case)
    "sample_body": {                    # representative create/update payload
        "name": "Example",
        "status": True,
    },
    "extra_actions": [                  # optional non-CRUD endpoints
        {"method": "POST", "path": "restore", "name": "Restore My New Resource"},
    ],
}
```
3. Re-run `generate.py` → re-import the collection.

---

## Auth Pattern (Passport Password Grant)

All requests (except Login) carry:
```
Authorization: Bearer {{token}}
Accept: application/json
Content-Type: application/json
```

The **Login** request includes a `Tests` script that auto-saves the token:
```javascript
if (pm.response.code === 200) {
    const json = pm.response.json();
    pm.environment.set("token", json.access_token);
}
```

---

## Conventions

- All route identifiers are UUIDs (`{{resource_identifier}}` variables).
- Pagination params: `page`, `per_page` (max 100), `search`, `sort_by`, `sort_order`.
- Soft-deleted records are restored via `POST /{resource}/{id}/restore`.
- API prefix: `/api/` — already included in `{{base_url}}`.
- Collection schema: `https://schema.getpostman.com/json/collection/v2.1.0/collection.json`
