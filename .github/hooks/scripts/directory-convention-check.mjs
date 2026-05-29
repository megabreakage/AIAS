#!/usr/bin/env node

/**
 * AIAS Directory Convention Pre-Tool Hook
 *
 * Fires on PreToolUse for create_file. Checks that new files land in the
 * correct Central/ or Tenant/ subdirectory before allowing the write.
 *
 * Returns permissionDecision "ask" with an explanation when a violation is
 * detected. All other tools and compliant paths are silently allowed (exit 0).
 */

// Tool names to intercept (guard against naming variation across agents)
const WATCHED_TOOLS = new Set(['create_file', 'createFile', 'write_file', 'writeFile']);

/**
 * Directories where files must be placed inside a Central/ or Tenant/ scope.
 *
 * Each entry:
 *   base        — the directory prefix to match (trailing slash required)
 *   rootExempt  — filenames allowed directly at the base level (shared base classes etc.)
 *   extraScopes — additional top-level subdirs that are legitimate (e.g. Auth, Tenants)
 */
const SCOPED_DIRS = [
    {
        base: 'app/Models/',
        rootExempt: ['BaseModel.php', 'User.php'],
        extraScopes: [],
    },
    {
        base: 'app/Repositories/',
        rootExempt: ['BaseRepository.php', 'PreambleRepository.php'],
        extraScopes: [],
    },
    {
        base: 'app/Http/Controllers/Api/V1/',
        rootExempt: ['BaseApiController.php', 'HealthController.php', 'TenantController.php', 'PreambleController.php'],
        extraScopes: ['Auth'],
    },
    {
        base: 'app/Filters/',
        rootExempt: ['EloquentFilter.php'],
        extraScopes: [],
    },
    {
        base: 'app/Http/Resources/',
        rootExempt: ['BaseResource.php', 'BaseResourceCollection.php'],
        extraScopes: [],
    },
    {
        base: 'app/Http/Requests/',
        rootExempt: [],
        extraScopes: ['Auth', 'Tenants'],
    },
];

const ALLOWED_SCOPES = ['Central', 'Tenant'];

function normalize(p) {
    return p.replace(/\\/g, '/').replace(/^[./]+/, '');
}

/**
 * Returns a violation object { decision, reason } if the path breaks a
 * convention, or null when the path is acceptable.
 *
 * @param {string} rawPath
 * @returns {{ decision: string, reason: string } | null}
 */
function checkPath(rawPath) {
    const filePath = normalize(rawPath);

    // ── Scoped directory rules ──────────────────────────────────────────────
    for (const { base, rootExempt, extraScopes } of SCOPED_DIRS) {
        if (!filePath.startsWith(base)) {
            continue;
        }

        const remainder = filePath.slice(base.length); // e.g. "Foo.php" or "Central/Foo.php"
        const parts = remainder.split('/').filter(Boolean);

        if (parts.length === 0) {
            continue; // creating the dir itself — skip
        }

        // Allow known root-level exempt files (shared base classes)
        if (parts.length === 1 && rootExempt.includes(parts[0])) {
            continue;
        }

        // Allow if top-level subdir is an accepted scope
        const topDir = parts[0];
        const allScopes = [...ALLOWED_SCOPES, ...extraScopes];
        if (allScopes.includes(topDir)) {
            continue;
        }

        // Violation detected
        const expectedPaths = ALLOWED_SCOPES.map((s) => `${base}${s}/`);
        return {
            decision: 'ask',
            reason:
                `"${rawPath}" is not scoped to a Central/ or Tenant/ directory.\n` +
                `Expected one of:\n` +
                expectedPaths.map((p) => `  • ${p}`).join('\n') +
                `\nIs the placement intentional? If yes, confirm to proceed.`,
        };
    }

    // ── Migration directory rule ────────────────────────────────────────────
    // Warn if a migration in database/migrations/ (central) has "tenant" in its name.
    if (
        filePath.startsWith('database/migrations/') &&
        !filePath.startsWith('database/migrations/tenant/')
    ) {
        const filename = filePath.split('/').pop() ?? '';
        if (/tenant/i.test(filename)) {
            return {
                decision: 'ask',
                reason:
                    `Migration "${filename}" contains "tenant" in its name but is placed in ` +
                    `database/migrations/ (central).\n` +
                    `Tenant migrations belong in database/migrations/tenant/.\n` +
                    `Is the placement intentional?`,
            };
        }
    }

    return null;
}

// ── Main ────────────────────────────────────────────────────────────────────

process.stdin.resume();
const chunks = [];
process.stdin.on('data', (chunk) => chunks.push(chunk));
process.stdin.on('end', () => {
    let input = {};
    try {
        input = JSON.parse(Buffer.concat(chunks).toString());
    } catch {
        process.exit(0); // unparseable input — don't block
    }

    // Only intercept file-creation tools
    const toolName = String(input.tool_name ?? input.toolName ?? '');
    if (!WATCHED_TOOLS.has(toolName)) {
        process.exit(0);
    }

    // Extract the target file path (handle both camelCase and snake_case variants)
    const filePath =
        input.tool_input?.filePath ??
        input.tool_input?.file_path ??
        input.tool_input?.path ??
        '';

    if (!filePath) {
        process.exit(0); // no path to check — allow
    }

    const violation = checkPath(String(filePath));
    if (violation) {
        process.stdout.write(
            JSON.stringify({
                hookSpecificOutput: {
                    hookEventName: 'PreToolUse',
                    permissionDecision: violation.decision,
                    permissionDecisionReason: violation.reason,
                },
            })
        );
    }

    process.exit(0);
});
