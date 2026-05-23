# AIAS — Gemini AI Agent Guide

## Laravel Boost Guidelines

=== foundation rules ===

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.16
- laravel/framework (LARAVEL) - v12
- laravel/passport (PASSPORT) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- tailwindcss (TAILWINDCSS) - v4

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## DRY Principle (Don't Repeat Yourself) - MANDATORY

**CRITICAL**: All code MUST strictly follow the DRY principle for maintainability.

### Core DRY Rules

1. **NEVER duplicate logic** across repositories, services, or controllers
2. **Always reuse existing repository methods** instead of creating duplicate functionality
3. **Use dependency injection** to access existing repositories and their methods
4. **Before writing new code**, search for existing implementations that can be reused
5. **Refactor immediately** if you find yourself copying code from another file
6. **Benefits**: Easier maintenance, consistent behavior, single source of truth, reduced bugs

### DRY Implementation Patterns

**CORRECT - Repository Dependency Injection**:

```php
class AuditEngagementRepository extends BaseRepository
{
    public function __construct(
        protected FindingRepository $findingRepository
    ) {}

    public function createEngagementWithFindings(array $data): AuditEngagement
    {
        // Reuse FindingRepository instead of duplicating logic
        $engagement = $this->insert($data);
        $this->findingRepository->createFinding([
            'engagement_id' => $engagement->id,
            ...
        ]);
        return $engagement;
    }
}
```

**INCORRECT - Logic Duplication**:

```php
class AuditEngagementRepository extends BaseRepository
{
    public function createEngagementWithFindings(array $data): AuditEngagement
    {
        // DON'T DO THIS! Duplicates BaseRepository::insert()
        $engagement = AuditEngagement::create($data);

        // DON'T DO THIS! Duplicates FindingRepository logic
        Finding::create([...]);

        return $engagement;
    }
}
```

### DRY Enforcement Checklist

Before writing any new functionality:

- [ ] Search codebase for existing similar implementations
- [ ] Identify repositories/services that can be reused
- [ ] Use dependency injection to access existing logic
- [ ] Extend BaseRepository methods rather than recreating CRUD
- [ ] Extract common patterns into shared traits or services
- [ ] Refactor duplicate code immediately when found

## Feature Development Workflow

When adding new features or updating existing ones, follow this comprehensive workflow:

### Required Steps

1. **Follow Existing Architectural Patterns** - Use established patterns as templates
2. **Create OpenAPI Documentation** - `storage/api-docs/{feature-name}.openapi.yaml` with comprehensive API specs
3. **Create Feature Documentation** - `docs/features/{feature-name}.md` with overview, API, permissions
4. **Update Features Index** - Add to `docs/features/README.md` with proper categorization
5. **Update Permissions** - Add to `config/role-permission-map.php` if applicable
6. **Write Comprehensive Tests** - Unit, feature, edge cases, integration tests
7. **Update Postman Collection** - Add endpoints to Postman collection
8. **Document Breaking Changes** - API changes, permissions, database, config

### Update Postman Collection

Update API documentation and testing using the comprehensive Postman collection:

- **Collection**: Add new endpoints following existing folder structure patterns
  - Include all CRUD operations (List, Create, Show, Update, Delete)
  - Add comprehensive test scripts for status codes, JSON validation, and business logic
  - Set up proper authentication inheritance from collection level
  - Include detailed request descriptions with usage examples

- **Environment Configuration**: Update environment variables
  - Add resource ID variables for auto-population
  - Include any new configuration or debug variables

- **Test Script Standards**: Follow the established testing patterns:

  ```javascript
  pm.test("Status code is 200/201", function () {
      pm.expect(pm.response.code).to.be.oneOf([200, 201]);
  });

  if (pm.response.code === 201) {
      pm.environment.set("created_resource_id", pm.response.json().data.id);
  }

  pm.test("Response has standard API structure", function () {
      const jsonData = pm.response.json();
      pm.expect(jsonData).to.have.property('status');
      pm.expect(jsonData).to.have.property('message');
      pm.expect(jsonData).to.have.property('data');
  });
  ```

### Completion Checklist

- [ ] All architectural patterns followed
- [ ] OpenAPI YAML documentation created in `storage/api-docs/`
- [ ] Feature documentation created/updated in `docs/features/`
- [ ] Features README.md updated with proper categorization
- [ ] Permissions added/updated in config
- [ ] Comprehensive tests written and passing
- [ ] Postman collection updated with new endpoints
- [ ] Postman environment variables updated
- [ ] Breaking changes documented
- [ ] Code formatted with Laravel Pint: `vendor/bin/pint --dirty`
- [ ] All tests passing: `./test.sh`

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure — don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies

- Be concise in your explanations — focus on what's important rather than explaining obvious details.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs

- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start.
- Do not add package names to queries — package information is already shared.

### Available Search Syntax

1. Simple Word Searches with auto-stemming — query=authentication
2. Multiple Words (AND Logic) — query=rate limit
3. Quoted Phrases (Exact Position) — query="infinite scroll"
4. Mixed Queries — query=middleware "rate limit"
5. Multiple Queries — queries=["authentication", "middleware"]

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

## Comments

- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something very complex going on.

## Enums

- Keys in an Enum should be TitleCase. For example: `AuditStatus`, `RiskLevel`, `FindingSeverity`.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files.
- Pass `--no-interaction` to all Artisan commands.

### Database

- Always use proper Eloquent relationship methods with return type hints.
- Avoid `DB::`; prefer `Model::query()`.
- Prevent N+1 query problems by using eager loading.

### Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers.
- Check sibling Form Requests for array or string based validation rules convention.

### Testing

- Use factories for model creation in tests.
- Use `php artisan make:test [options] {name}` for feature tests, `--unit` for unit tests.

=== laravel/v12 rules ===

## Laravel 13

- Middleware configured in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/providers.php` contains service providers.
- Commands in `app/Console/Commands/` auto-register.
- Model casts: use `casts()` method, not `$casts` property.
- When modifying a column, migration must include all previously defined attributes.

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix formatting.

=== pest/core rules ===

## Pest Core

- App uses Pest v3 for testing. All tests must be written in Pest syntax.
- Use `php artisan make:test {name}` to create tests (Pest format).
- If you see a test using PHPUnit class syntax, convert it to Pest.
- Tests should cover happy paths, failure paths, and weird paths.
- **ALWAYS use `./test.sh`** to run tests.
- Run minimal tests using an appropriate filter before finalizing.

=== tailwindcss/v4 rules ===

## Tailwind CSS 4

- Always use Tailwind CSS v4; do not use deprecated utilities.
- Configuration is CSS-first using `@theme` directive.
- Import using `@import "tailwindcss"`, not `@tailwind` directives.
- Use gap utilities for spacing, not margins.
- Support dark mode using `dark:` if existing components do.

### Replaced Utilities

| Deprecated | Replacement |
|---|---|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
