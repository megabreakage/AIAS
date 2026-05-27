# Postman Collection Reference

Comprehensive reference for the Postman Collection v2.1 format as used in **AirTrafficEng**.

---

## Collection Schema

```
https://schema.getpostman.com/json/collection/v2.1.0/collection.json
```

---

## Top-Level Structure

```json
{
  "info": {
    "_postman_id": "<uuid>",
    "name": "AirTrafficEng API",
    "description": "...",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "auth": { ... },
  "variable": [ ... ],
  "item": [ ...folders... ]
}
```

### `info` object

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `_postman_id` | string (UUID) | Yes | Stable identifier; don't regenerate unless creating a brand-new collection |
| `name` | string | Yes | Human-readable collection name |
| `description` | string | No | Markdown supported |
| `schema` | string | Yes | Must be the v2.1.0 URL above |

---

## Authentication Block

AirTrafficEng uses **Bearer token** (Passport OAuth2 Password Grant).

```json
"auth": {
  "type": "bearer",
  "bearer": [
    {
      "key": "token",
      "value": "{{token}}",
      "type": "string"
    }
  ]
}
```

Setting `auth` at the collection level applies it to **all requests** that do not override it.  
The **Login** request overrides with `"auth": { "type": "noauth" }` since it doesn't need a token.

---

## Collection Variables

Defined in the top-level `variable` array. Overridable by environment variables.

```json
"variable": [
  { "key": "base_url",       "value": "http://localhost:8000/api", "type": "string" },
  { "key": "token",          "value": "",                          "type": "string" },
  { "key": "client_id",      "value": "2",                         "type": "string" },
  { "key": "client_secret",  "value": "",                          "type": "string" },
  { "key": "username",       "value": "admin@airtrafficeng.test",  "type": "string" },
  { "key": "password",       "value": "password",                  "type": "string" }
]
```

**Best practice**: always prefer environment variables over collection variables for sensitive values (`token`, `client_secret`, `password`).

---

## Folder (Group) Structure

Each API resource is a **folder** (`item` containing child `item` entries).

```json
{
  "name": "Continents",
  "description": "Manage continent records.",
  "item": [
    { /* Index request  */ },
    { /* Store request  */ },
    { /* Show request   */ },
    { /* Update request */ },
    { /* Destroy request*/ },
    { /* Restore request*/ }
  ]
}
```

---

## Request Object

### Minimal skeleton

```json
{
  "name": "List Continents",
  "request": {
    "method": "GET",
    "header": [
      { "key": "Accept",       "value": "application/json" },
      { "key": "Content-Type", "value": "application/json" }
    ],
    "url": {
      "raw": "{{base_url}}/continents",
      "host": ["{{base_url}}"],
      "path": ["continents"]
    },
    "description": "Returns a paginated list of continents."
  },
  "response": []
}
```

### Request with query parameters (Index)

```json
"url": {
  "raw": "{{base_url}}/continents?page=1&per_page=15&search=&sort_by=name&sort_order=asc",
  "host": ["{{base_url}}"],
  "path": ["continents"],
  "query": [
    { "key": "page",       "value": "1",    "description": "Page number (min 1)" },
    { "key": "per_page",   "value": "15",   "description": "Items per page (max 100)" },
    { "key": "search",     "value": "",     "description": "Full-text search term" },
    { "key": "sort_by",    "value": "name", "description": "Column to sort by" },
    { "key": "sort_order", "value": "asc",  "description": "asc | desc" }
  ]
}
```

### Request with JSON body (Store / Update)

```json
"body": {
  "mode": "raw",
  "raw": "{\n  \"name\": \"Africa\",\n  \"status\": true\n}",
  "options": {
    "raw": { "language": "json" }
  }
}
```

### Request with route parameter (Show / Update / Destroy)

```json
"url": {
  "raw": "{{base_url}}/continents/{{continent_identifier}}",
  "host": ["{{base_url}}"],
  "path": ["continents", "{{continent_identifier}}"]
}
```

---

## Standard CRUD Request Set

For every resource at route `/api/{resources}`:

| # | Name | Method | URL pattern | Body |
|---|------|--------|-------------|------|
| 1 | List {Resources} | GET | `/{resources}?page=1&per_page=15&...` | None |
| 2 | Create {Resource} | POST | `/{resources}` | JSON payload |
| 3 | Get {Resource} | GET | `/{resources}/{{resource_identifier}}` | None |
| 4 | Update {Resource} | PUT | `/{resources}/{{resource_identifier}}` | JSON payload |
| 5 | Delete {Resource} | DELETE | `/{resources}/{{resource_identifier}}` | None |
| 6 | Restore {Resource} | POST | `/{resources}/{{resource_identifier}}/restore` | None |

---

## HTTP Methods

| Laravel route | HTTP method | Postman method |
|---------------|-------------|----------------|
| `index` | GET | GET |
| `store` | POST | POST |
| `show` | GET | GET |
| `update` | PUT/PATCH | PUT |
| `destroy` | DELETE | DELETE |
| custom `restore` | POST | POST |
| custom `activate` | POST | POST |

---

## Pre-request Scripts

Run before the request fires. Used to set dynamic values.

```json
"event": [
  {
    "listen": "prerequest",
    "script": {
      "type": "text/javascript",
      "exec": [
        "// Ensure token is present",
        "if (!pm.environment.get('token')) {",
        "    console.warn('No token set. Run the Login request first.');",
        "}"
      ]
    }
  }
]
```

---

## Test Scripts (Post-response)

Run after the response is received.

### Auto-save token after login

```json
"event": [
  {
    "listen": "test",
    "script": {
      "type": "text/javascript",
      "exec": [
        "if (pm.response.code === 200) {",
        "    const json = pm.response.json();",
        "    pm.environment.set('token', json.access_token);",
        "    pm.environment.set('token_type', json.token_type);",
        "    console.log('Token saved:', json.access_token.substring(0, 20) + '...');",
        "}",
        "pm.test('Login successful', () => {",
        "    pm.response.to.have.status(200);",
        "    pm.expect(pm.response.json()).to.have.property('access_token');",
        "});"
      ]
    }
  }
]
```

### Generic response assertions

```json
"event": [
  {
    "listen": "test",
    "script": {
      "type": "text/javascript",
      "exec": [
        "pm.test('Response is JSON', () => {",
        "    pm.response.to.be.json;",
        "});",
        "pm.test('Status field is success', () => {",
        "    pm.expect(pm.response.json().status).to.eql('success');",
        "});"
      ]
    }
  }
]
```

---

## AirTrafficEng API Response Envelope

All API responses follow this envelope:

```json
{
  "status": "success",
  "message": "Retrieved successfully",
  "data": [ ... ],
  "metadata": { "filters_applied": { "search": "value" } },
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42
  }
}
```

- **201** — resource created (`store`)
- **200** — all other successful operations
- **422** — validation error (`errors` key present)
- **403** — authorization failure
- **404** — not found
- **500** — server error

---

## Auth Endpoints (Passport Password Grant)

### Login

```
POST /oauth/token
Body (form-data or JSON):
  grant_type    password
  client_id     {{client_id}}
  client_secret {{client_secret}}
  username      {{username}}
  password      {{password}}
  scope         *
```

Response:
```json
{
  "token_type": "Bearer",
  "expires_in": 31536000,
  "access_token": "eyJ...",
  "refresh_token": "def..."
}
```

### Refresh Token

```
POST /oauth/token
Body:
  grant_type    refresh_token
  refresh_token {{refresh_token}}
  client_id     {{client_id}}
  client_secret {{client_secret}}
  scope         *
```

### Logout (revoke token)

```
DELETE /api/oauth/tokens/{{token_id}}
Authorization: Bearer {{token}}
```

---

## Nested Resources (Shallow)

Work Order Details are a shallow nested resource:

```
POST   /work-orders/{work_order}/details          → store
PUT    /details/{detail}                          → update
DELETE /details/{detail}                          → destroy
```

Postman URL construction:
```json
"url": {
  "raw": "{{base_url}}/work-orders/{{work_order_identifier}}/details",
  "host": ["{{base_url}}"],
  "path": ["work-orders", "{{work_order_identifier}}", "details"]
}
```

---

## Naming Conventions

| Pattern | Example |
|---------|---------|
| Folder name | `Continents`, `Work Orders`, `Certifying Staff` |
| Request name | `List Continents`, `Create Continent`, `Get Continent`, `Update Continent`, `Delete Continent`, `Restore Continent` |
| Route variable | `{{continent_identifier}}`, `{{work_order_identifier}}` |
| Collection variable | `{{base_url}}`, `{{token}}` |

---

## Folder-Level Auth Override

To disable auth for a specific folder (e.g. Auth folder):
```json
{
  "name": "Auth",
  "auth": { "type": "noauth" },
  "item": [ ... ]
}
```

---

## Tips & Gotchas

1. **UUID identifiers** — never use numeric IDs. All route params are UUIDs stored as `{{resource_identifier}}`.
2. **`PUT` vs `PATCH`** — Laravel's `apiResource` maps `update` to both; use `PUT` in Postman for clarity.
3. **`soft_deleted` filter** — some `index` endpoints accept `show_deleted=1` query param.
4. **`per_page` cap** — server enforces `max(100)`. Don't set higher in test requests.
5. **`scope: *`** — always use `*` scope for Passport password grant in development.
6. **Re-import** — Postman merge-imports by `_postman_id`; keep the ID stable across regenerations to preserve history.
7. **Environment vs Collection vars** — sensitive values (`token`, passwords) should live in a Postman Environment, not baked into the collection JSON.
