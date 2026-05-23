# Example: Auth Management Folder

This is the canonical format for a Postman Collection v2.1 folder with all CRUD + extra actions.
It covers the **Auth** folder (Login, Refresh, Logout) and the **Users** folder (full CRUD + restore + activate/deactivate) as reference templates.

---

## Auth Folder (no bearer auth — overrides collection-level auth)

```json
{
  "name": "Auth",
  "description": "OAuth2 Password Grant authentication via Laravel Passport.",
  "auth": {
    "type": "noauth"
  },
  "item": [
    {
      "name": "Login",
      "event": [
        {
          "listen": "test",
          "script": {
            "type": "text/javascript",
            "exec": [
              "if (pm.response.code === 200) {",
              "    const json = pm.response.json();",
              "    pm.environment.set('token', json.access_token);",
              "    pm.environment.set('refresh_token', json.refresh_token);",
              "    console.log('Token saved successfully.');",
              "}",
              "pm.test('Login successful', () => {",
              "    pm.response.to.have.status(200);",
              "    pm.expect(pm.response.json()).to.have.property('access_token');",
              "});"
            ]
          }
        }
      ],
      "request": {
        "method": "POST",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"grant_type\": \"password\",\n  \"client_id\": \"{{client_id}}\",\n  \"client_secret\": \"{{client_secret}}\",\n  \"username\": \"{{username}}\",\n  \"password\": \"{{password}}\",\n  \"scope\": \"*\"\n}",
          "options": {
            "raw": { "language": "json" }
          }
        },
        "url": {
          "raw": "{{base_url}}/../../oauth/token",
          "host": ["{{base_url}}"],
          "path": ["..", "..", "oauth", "token"]
        },
        "description": "Exchange credentials for an access token (Passport Password Grant). Automatically saves `token` and `refresh_token` to the active environment."
      },
      "response": []
    },
    {
      "name": "Refresh Token",
      "event": [
        {
          "listen": "test",
          "script": {
            "type": "text/javascript",
            "exec": [
              "if (pm.response.code === 200) {",
              "    const json = pm.response.json();",
              "    pm.environment.set('token', json.access_token);",
              "    pm.environment.set('refresh_token', json.refresh_token);",
              "    console.log('Token refreshed successfully.');",
              "}",
              "pm.test('Token refreshed', () => {",
              "    pm.response.to.have.status(200);",
              "    pm.expect(pm.response.json()).to.have.property('access_token');",
              "});"
            ]
          }
        }
      ],
      "request": {
        "method": "POST",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"grant_type\": \"refresh_token\",\n  \"refresh_token\": \"{{refresh_token}}\",\n  \"client_id\": \"{{client_id}}\",\n  \"client_secret\": \"{{client_secret}}\",\n  \"scope\": \"*\"\n}",
          "options": {
            "raw": { "language": "json" }
          }
        },
        "url": {
          "raw": "{{base_url}}/../../oauth/token",
          "host": ["{{base_url}}"],
          "path": ["..", "..", "oauth", "token"]
        },
        "description": "Exchange a refresh token for a new access token."
      },
      "response": []
    },
    {
      "name": "Get Authenticated User",
      "request": {
        "method": "GET",
        "header": [
          { "key": "Accept",         "value": "application/json" },
          { "key": "Authorization",  "value": "Bearer {{token}}" }
        ],
        "url": {
          "raw": "{{base_url}}/user",
          "host": ["{{base_url}}"],
          "path": ["user"]
        },
        "description": "Returns the currently authenticated user's profile."
      },
      "response": []
    }
  ]
}
```

---

## Users Folder (full CRUD + extra actions)

This is the template for any resource that has standard CRUD plus additional custom actions (restore, activate, deactivate).

```json
{
  "name": "Users",
  "description": "Manage platform users. Requires appropriate role/permission.",
  "item": [
    {
      "name": "List Users",
      "request": {
        "method": "GET",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "url": {
          "raw": "{{base_url}}/users?page=1&per_page=15&search=&sort_by=created_at&sort_order=desc",
          "host": ["{{base_url}}"],
          "path": ["users"],
          "query": [
            { "key": "page",         "value": "1",           "description": "Page number (min: 1)" },
            { "key": "per_page",     "value": "15",          "description": "Items per page (max: 100)" },
            { "key": "search",       "value": "",            "description": "Search by name or email" },
            { "key": "sort_by",      "value": "created_at",  "description": "Sort column: name | email | status | created_at | updated_at" },
            { "key": "sort_order",   "value": "desc",        "description": "Sort direction: asc | desc" },
            { "key": "show_deleted", "value": "0",           "description": "Include soft-deleted records: 0 | 1", "disabled": true }
          ]
        },
        "description": "Returns a paginated list of users. Supports search, sort, and pagination."
      },
      "response": []
    },
    {
      "name": "Create User",
      "request": {
        "method": "POST",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"first_name\": \"John\",\n  \"last_name\": \"Doe\",\n  \"email\": \"john.doe@example.com\",\n  \"password\": \"Password123!\",\n  \"password_confirmation\": \"Password123!\",\n  \"role\": \"admin\",\n  \"status\": true\n}",
          "options": {
            "raw": { "language": "json" }
          }
        },
        "url": {
          "raw": "{{base_url}}/users",
          "host": ["{{base_url}}"],
          "path": ["users"]
        },
        "description": "Create a new platform user. Returns 201 on success."
      },
      "response": []
    },
    {
      "name": "Get User",
      "request": {
        "method": "GET",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "url": {
          "raw": "{{base_url}}/users/{{user_identifier}}",
          "host": ["{{base_url}}"],
          "path": ["users", "{{user_identifier}}"]
        },
        "description": "Retrieve a single user by their UUID identifier."
      },
      "response": []
    },
    {
      "name": "Update User",
      "request": {
        "method": "PUT",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"first_name\": \"Jane\",\n  \"last_name\": \"Doe\",\n  \"email\": \"jane.doe@example.com\",\n  \"status\": true\n}",
          "options": {
            "raw": { "language": "json" }
          }
        },
        "url": {
          "raw": "{{base_url}}/users/{{user_identifier}}",
          "host": ["{{base_url}}"],
          "path": ["users", "{{user_identifier}}"]
        },
        "description": "Update an existing user. Uses `sometimes` validation — only include fields to change."
      },
      "response": []
    },
    {
      "name": "Delete User",
      "request": {
        "method": "DELETE",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "url": {
          "raw": "{{base_url}}/users/{{user_identifier}}",
          "host": ["{{base_url}}"],
          "path": ["users", "{{user_identifier}}"]
        },
        "description": "Soft-delete a user. The record remains in the database and can be restored."
      },
      "response": []
    },
    {
      "name": "Restore User",
      "request": {
        "method": "POST",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "url": {
          "raw": "{{base_url}}/users/{{user_identifier}}/restore",
          "host": ["{{base_url}}"],
          "path": ["users", "{{user_identifier}}", "restore"]
        },
        "description": "Restore a soft-deleted user."
      },
      "response": []
    },
    {
      "name": "Activate User",
      "request": {
        "method": "POST",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "url": {
          "raw": "{{base_url}}/users/{{user_identifier}}/activate",
          "host": ["{{base_url}}"],
          "path": ["users", "{{user_identifier}}", "activate"]
        },
        "description": "Set a user's status to active."
      },
      "response": []
    },
    {
      "name": "Deactivate User",
      "request": {
        "method": "POST",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "url": {
          "raw": "{{base_url}}/users/{{user_identifier}}/deactivate",
          "host": ["{{base_url}}"],
          "path": ["users", "{{user_identifier}}", "deactivate"]
        },
        "description": "Set a user's status to inactive."
      },
      "response": []
    },
    {
      "name": "Verify User Email",
      "request": {
        "method": "POST",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "url": {
          "raw": "{{base_url}}/users/{{user_identifier}}/verify-email",
          "host": ["{{base_url}}"],
          "path": ["users", "{{user_identifier}}", "verify-email"]
        },
        "description": "Manually verify a user's email address."
      },
      "response": []
    }
  ]
}
```

---

## Minimal Resource Folder (CRUD + restore only)

For simple resources like `Continents`, `Countries`, `Departments` etc.:

```json
{
  "name": "Continents",
  "description": "Manage continent reference data.",
  "item": [
    {
      "name": "List Continents",
      "request": {
        "method": "GET",
        "header": [
          { "key": "Accept", "value": "application/json" }
        ],
        "url": {
          "raw": "{{base_url}}/continents?page=1&per_page=15&search=&sort_by=name&sort_order=asc",
          "host": ["{{base_url}}"],
          "path": ["continents"],
          "query": [
            { "key": "page",       "value": "1" },
            { "key": "per_page",   "value": "15" },
            { "key": "search",     "value": "" },
            { "key": "sort_by",    "value": "name" },
            { "key": "sort_order", "value": "asc" }
          ]
        }
      },
      "response": []
    },
    {
      "name": "Create Continent",
      "request": {
        "method": "POST",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"name\": \"Africa\",\n  \"status\": true\n}",
          "options": { "raw": { "language": "json" } }
        },
        "url": {
          "raw": "{{base_url}}/continents",
          "host": ["{{base_url}}"],
          "path": ["continents"]
        }
      },
      "response": []
    },
    {
      "name": "Get Continent",
      "request": {
        "method": "GET",
        "header": [{ "key": "Accept", "value": "application/json" }],
        "url": {
          "raw": "{{base_url}}/continents/{{continent_identifier}}",
          "host": ["{{base_url}}"],
          "path": ["continents", "{{continent_identifier}}"]
        }
      },
      "response": []
    },
    {
      "name": "Update Continent",
      "request": {
        "method": "PUT",
        "header": [
          { "key": "Accept",       "value": "application/json" },
          { "key": "Content-Type", "value": "application/json" }
        ],
        "body": {
          "mode": "raw",
          "raw": "{\n  \"name\": \"Africa (Updated)\",\n  \"status\": true\n}",
          "options": { "raw": { "language": "json" } }
        },
        "url": {
          "raw": "{{base_url}}/continents/{{continent_identifier}}",
          "host": ["{{base_url}}"],
          "path": ["continents", "{{continent_identifier}}"]
        }
      },
      "response": []
    },
    {
      "name": "Delete Continent",
      "request": {
        "method": "DELETE",
        "header": [{ "key": "Accept", "value": "application/json" }],
        "url": {
          "raw": "{{base_url}}/continents/{{continent_identifier}}",
          "host": ["{{base_url}}"],
          "path": ["continents", "{{continent_identifier}}"]
        }
      },
      "response": []
    },
    {
      "name": "Restore Continent",
      "request": {
        "method": "POST",
        "header": [{ "key": "Accept", "value": "application/json" }],
        "url": {
          "raw": "{{base_url}}/continents/{{continent_identifier}}/restore",
          "host": ["{{base_url}}"],
          "path": ["continents", "{{continent_identifier}}", "restore"]
        }
      },
      "response": []
    }
  ]
}
```

---

## Notes

- The `response` array is always `[]` — saved example responses are added manually in Postman after testing.
- `"disabled": true` on a query param renders it greyed-out in Postman (optional param shown for reference).
- All `host` values use `["{{base_url}}"]` as a single element — Postman resolves the variable at runtime.
- Route variables follow `{{resource_name_identifier}}` pattern matching the model's `identifier` UUID column.
