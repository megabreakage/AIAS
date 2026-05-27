# PriorityLevel — Tenant-Scoped Model Spec

## Overview

`PriorityLevel` is a tenant-configurable lookup table that defines the priority tiers available within a tenant's workspace. It allows each tenant to customise the names, ordering, colours, and descriptions of their priority scale (e.g. Critical → High → Medium → Low → Informational).

Priority levels are referenced by audit findings, risks, tasks, and other tenant-scoped entities to express urgency or importance.

## Scope

- **Tenant-scoped** — every record belongs to a single tenant via `tenant_id`
- **Soft-deleted** — supports restore via the `DELETE /restore` endpoint
- **No auditing** — tenant models do not use `OwenIt\Auditing\Auditable` (central models only)

## Database Table: `priority_levels`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `bigint unsigned` | No | auto | Primary key |
| `identifier` | `uuid` | No | `uuid()` | Public-facing unique ID used in API routes |
| `tenant_id` | `varchar(255)` | No | — | Tenant reference (no FK to central DB) |
| `name` | `varchar(255)` | No | — | Display name, e.g. "Critical" |
| `description` | `text` | Yes | `null` | Optional description |
| `level` | `int` | No | — | Numeric rank for ordering (lower = higher priority, e.g. 1 = highest) |
| `color` | `varchar(255)` | Yes | `null` | Hex colour code, e.g. `#FF0000` |
| `is_active` | `boolean` | No | `true` | Whether this level is selectable |
| `created_by` | `bigint unsigned` | Yes | `null` | User who created the record |
| `updated_by` | `bigint unsigned` | Yes | `null` | User who last updated the record |
| `created_at` | `timestamp` | Yes | `null` | |
| `updated_at` | `timestamp` | Yes | `null` | |
| `deleted_at` | `timestamp` | Yes | `null` | Soft-delete support |

**Unique constraint**: `(tenant_id, name)` — each tenant may not have two levels with the same name.

## Model: `App\Models\PriorityLevel`

- Extends `Illuminate\Database\Eloquent\Model`
- Uses `HasUuidIdentifier`, `SoftDeletes`, `HasFactory`
- Route key: `identifier`
- Fillable: `identifier`, `tenant_id`, `name`, `description`, `level`, `color`, `is_active`, `created_by`, `updated_by`
- Casts: `is_active → boolean`, `level → integer`, timestamps

## Relationships

None required at this stage; other modules (Findings, Risks, Tasks) will add `belongsTo(PriorityLevel::class)`.

## Permissions

| Permission slug | Description |
|---|---|
| `priority-levels.view` | List and retrieve priority levels |
| `priority-levels.create` | Create a new priority level |
| `priority-levels.edit` | Update an existing priority level |
| `priority-levels.delete` | Soft-delete and restore priority levels |

### Role Assignments

| Role | Permissions |
|---|---|
| `tenant-admin` | All four permissions |
| `tenant-manager` | `view`, `create`, `edit` |
| `tenant-auditor` | `view` |
| `tenant-viewer` | `view` |

## API Endpoints

All endpoints live under the authenticated tenant middleware group.

| Method | URI | Action |
|---|---|---|
| `GET` | `/v1/priority-levels` | List (paginated, filterable) |
| `POST` | `/v1/priority-levels` | Create |
| `GET` | `/v1/priority-levels/{identifier}` | Show |
| `PUT/PATCH` | `/v1/priority-levels/{identifier}` | Update |
| `DELETE` | `/v1/priority-levels/{identifier}` | Soft-delete |
| `POST` | `/v1/priority-levels/{identifier}/restore` | Restore |

### Query Parameters (index)

| Param | Type | Description |
|---|---|---|
| `search` | string | Search by `name` or `description` |
| `is_active` | boolean | Filter by active status |
| `page` | integer | Page number (default: 1) |
| `per_page` | integer | Items per page (default: 15, max: 100) |

## Validation Rules

### Create

| Field | Rule |
|---|---|
| `name` | required, string, max:255, unique per tenant |
| `description` | nullable, string |
| `level` | required, integer, min:1 |
| `color` | nullable, string, max:255 |
| `is_active` | boolean (defaults to `true`) |

### Update

Same as create but all fields are optional (PATCH semantics) except uniqueness is scoped to exclude the current record.

## Generator Command

```bash
python3 docs/prompts/aias/scripts/generate-module.py PriorityLevel \
    --fields "name:string:required,description:text:nullable,level:integer:required,color:string:nullable,is_active:boolean:required" \
    --base-dir .
```
