#!/usr/bin/env node

/**
 * AIAS DRY Principles Pre-Execution Hook
 *
 * Fires on every UserPromptSubmit to inject the DRY checklist as system
 * context before the agent writes any code. Always allows (exit 0).
 */

const DRY_SYSTEM_MESSAGE = `
## AIAS DRY Principles — Mandatory Pre-Code Checklist

Before writing ANY code, verify ALL of the following:

### 1. Search Before Creating
- \`app/Repositories/\` — does an existing method already handle this operation?
- \`app/Filters/\` — reusable filter class already exists?
- \`app/Traits/\` — shared behavior available?
- \`BaseRepository\` — does \`insert/update/delete/read\` already cover this CRUD?

### 2. Inject Dependencies — Never Duplicate
\`\`\`php
// ❌ NEVER — direct model query or copied logic
AuditEngagement::create(['title' => $data['title']]);

// ✅ ALWAYS — inject and reuse the repository
public function __construct(protected AuditEngagementRepository $repo) {}
$this->repo->createEngagement($data);
\`\`\`

### 3. Tenant Filtering — Repository Methods Only
\`\`\`php
// ❌ NEVER — manual tenant filter in controller/service
Model::where('tenant_id', auth()->user()->tenant_id)->get();

// ✅ ALWAYS — repository browse* handles filtering
$this->repository->browseRecords($filters, $page, $perPage);
\`\`\`

### 4. Cross-Repository Dependency Injection
If repo A needs repo B logic, inject B into A's constructor. Never duplicate logic across repositories.

### 5. Directory Conventions
- Central models/repos/controllers → \`Central/\` subdirectories
- Tenant models/repos/controllers → \`Tenant/\` subdirectories
- Shared logic → \`app/Traits/\` or \`BaseRepository\`
- Tenant migrations → \`database/migrations/tenant/\`
- Validation → Form Request classes, never inline in controllers

### 6. Verification Checklist
- [ ] No logic duplicated from another file
- [ ] All DB operations through repositories
- [ ] Tenant filtering via \`browse*\` repository methods only
- [ ] \`BaseRepository\` base methods used where applicable
- [ ] Validation in Form Request classes
- [ ] Tests are PEST, reuse existing \`setUp\` and helper methods
`.trim();

process.stdin.resume();
const chunks = [];
process.stdin.on('data', (chunk) => chunks.push(chunk));
process.stdin.on('end', () => {
    // Always inject DRY context; never block the prompt
    process.stdout.write(JSON.stringify({ systemMessage: DRY_SYSTEM_MESSAGE }));
    process.exit(0);
});
