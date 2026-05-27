# Priority Levels

## Overview

The **Priority Levels** module provides each tenant with a fully configurable priority scale. Tenants can define their own priority tiers (e.g. Critical → High → Medium → Low → Informational) with custom names, numeric ranks, descriptions, and colour codes.

Priority levels are referenced by other tenant-scoped modules (audit findings, risks, tasks, action items) to express urgency or importance in a consistent, tenant-defined way.

## Key Concepts

| Concept | Description |
|---|---|
| **Tenant-Scoped** | Each record belongs to a single tenant via `tenant_id` |
| **Configurable** | Tenants can create, rename, and reorder their own levels |
| **Ordered** | The `level` integer determines sort order; lower = higher priority |
| **Soft-Deleted** | Deleted levels are retained and can be restored |
| **No Cross-Tenant Access** | The repository filters by `tenant_id` to prevent data leakage |

## Database Schema

Table: `priority_levels` (tenant database)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Auto-increment primary key |
| `identifier` | uuid | Public-facing UUID, used in API routes |
| `tenant_id` | string | Tenant reference (no FK to central DB) |
| `name` | string(255) | Unique per tenant |
| `description` | text, nullable | Optional description |
| `level` | integer | Numeric rank (1 = highest priority) |
| `color` | string(255), nullable | Hex colour code, e.g. `#FF0000` |
| `is_active` | boolean, default true | Controls availability in selectors |
| `created_by` | bigint, nullable | FK → users.id |
| `updated_by` | bigint, nullable | FK → users.id |
| `created_at` / `updated_at` | timestamp | |
| `deleted_at` | timestamp, nullable | Soft-delete support |

## Permissions

| Permission | Description |
|---|---|
| `priority-levels.view` | List and retrieve priority levels |
| `priority-levels.create` | Create a new priority level |
| `priority-levels.edit` | Update an existing priority level |
| `priority-levels.delete` | Soft-delete and restore priority levels |

### Role Assignments (defaults)

| Role | Permissions |
|---|---|
| `tenant-admin` | view, create, edit, delete |
| `auditor` | view, create, edit |
| `viewer` | view |

## API Reference

Base path (tenant domain): `GET http://{tenant}.localhost/v1/priority-levels`  
Base path (central domain): `GET http://localhost/api/v1/priority-levels`

See [`storage/api-docs/priority-levels.openapi.yaml`](../../storage/api-docs/priority-levels.openapi.yaml) for the full OpenAPI specification.

### Endpoints

| Method | Path | Permission | Description |
|---|---|---|---|
| `GET` | `/priority-levels` | view | List (paginated + filterable) |
| `POST` | `/priority-levels` | create | Create |
| `GET` | `/priority-levels/{id}` | view | Show |
| `PUT/PATCH` | `/priority-levels/{id}` | edit | Update |
| `DELETE` | `/priority-levels/{id}` | delete | Soft-delete |
| `POST` | `/priority-levels/{id}/restore` | delete | Restore |

### Query Parameters (index)

| Param | Type | Description |
|---|---|---|
| `search` | string | Searches `name` and `description` |
| `is_active` | 0\|1 | Filter by active status |
| `page` | integer | Page number (default 1) |
| `per_page` | integer | Items per page (default 15, max 100) |

### Example: Create

```http
POST /api/v1/priority-levels
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Critical",
  "description": "Requires immediate action within 24 hours",
  "level": 1,
  "color": "#FF0000",
  "is_active": true
}
```

Response `201 Created`:
```json
{
  "data": {
    "id": "9c8b4f3e-1a2b-4c5d-8e9f-0a1b2c3d4e5f",
    "name": "Critical",
    "description": "Requires immediate action within 24 hours",
    "level": 1,
    "color": "#FF0000",
    "is_active": true,
    "created_by": null,
    "updated_by": null,
    "created_at": "2026-05-27T20:00:00.000000Z",
    "updated_at": "2026-05-27T20:00:00.000000Z"
  },
  "message": "Priority Level created successfully"
}
```

## Files

| File | Description |
|---|---|
| `app/Models/PriorityLevel.php` | Eloquent model |
| `app/Repositories/PriorityLevelRepository.php` | Data access layer |
| `app/Http/Controllers/PriorityLevelController.php` | API controller |
| `app/Http/Requests/PriorityLevels/CreatePriorityLevelRequest.php` | Create validation |
| `app/Http/Requests/PriorityLevels/UpdatePriorityLevelRequest.php` | Update validation |
| `app/Http/Resources/PriorityLevels/PriorityLevelResource.php` | API resource |
| `app/Http/Resources/PriorityLevels/PriorityLevelCollection.php` | API collection |
| `app/Policies/PriorityLevelPolicy.php` | Authorization policy |
| `app/Filters/PriorityLevels/PriorityLevelFilters.php` | Filter composer |
| `database/migrations/tenant/…_create_priority_levels_table.php` | Tenant migration |
| `database/factories/PriorityLevelFactory.php` | Test factory |
| `database/seeders/PriorityLevelSeeder.php` | Sample seeder |
| `config/role-permission-map.php` | Permission definitions |
| `tests/Feature/PriorityLevelTest.php` | Feature tests |
| `tests/Unit/PriorityLevelUnitTest.php` | Unit tests |
| `storage/api-docs/priority-levels.openapi.yaml` | OpenAPI specification |

## Migration Commands

```bash
# Run tenant migrations (adds priority_levels table to all existing tenant DBs)
php artisan tenants:migrate

# Seed roles and permissions
php artisan db:seed --class=TenantDatabaseSeeder

# Seed sample priority levels (optional)
php artisan db:seed --class=PriorityLevelSeeder
```

## Testing

```bash
# Feature tests
./test.sh tests/Feature/PriorityLevelTest.php

# Unit tests
./test.sh tests/Unit/PriorityLevelUnitTest.php
```
