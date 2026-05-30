#!/usr/bin/env node

/**
 * AIAS Auto-Commit Stop Hook
 *
 * Fires at the end of every agent chat session (Stop event).
 * If there are uncommitted file changes, it:
 *   1. Analyses changed paths to infer the conventional commit type
 *   2. Extracts a description from the conversation transcript
 *   3. Stages all changes with `git add -A`
 *   4. Commits with a formatted message: `{emoji} {type}: {Description}`
 *
 * Commit types  →  emoji
 *   feat         →  ✨
 *   fix          →  🐛
 *   refactor     →  ♻️
 *   test         →  🧪
 *   docs         →  📚
 *   chore        →  🔧
 *   ci           →  🔄
 *   style        →  🎨
 *   perf         →  ⚡
 */

import { execSync, spawnSync } from 'node:child_process';

// ── stdin reader ──────────────────────────────────────────────────────────────

async function readStdin() {
    return new Promise((resolve) => {
        let data = '';
        const timeout = setTimeout(() => resolve(data), 3000);
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => {
            data += chunk;
        });
        process.stdin.on('end', () => {
            clearTimeout(timeout);
            resolve(data);
        });
        process.stdin.on('error', () => {
            clearTimeout(timeout);
            resolve('');
        });
    });
}

// ── git helpers ───────────────────────────────────────────────────────────────

function isGitRepo() {
    const result = spawnSync('git', ['rev-parse', '--git-dir'], {
        encoding: 'utf8',
        cwd: process.cwd(),
    });

    return result.status === 0;
}

function git(...args) {
    try {
        return execSync(`git ${args.join(' ')}`, {
            encoding: 'utf8',
            cwd: process.cwd(),
            stdio: ['pipe', 'pipe', 'pipe'],
        }).trim();
    } catch {
        return '';
    }
}

// ── type detection ────────────────────────────────────────────────────────────

/** Ordered by specificity — first match wins per file. */
const TYPE_MAP = [
    {
        type: 'ci',
        emoji: '🔄',
        patterns: [
            '.github/workflows',
            'Dockerfile',
            'docker-compose',
            '.dockerignore',
        ],
    },
    {
        type: 'chore',
        emoji: '🔧',
        patterns: [
            '.github/hooks',
            '.github/instructions',
            '.github/prompts',
            '.github/agents',
            'composer.json',
            'package.json',
            'pint.json',
            'phpunit.xml',
            'config/',
            '.env',
            'boost.json',
        ],
    },
    {
        type: 'test',
        emoji: '🧪',
        patterns: ['tests/'],
    },
    {
        type: 'docs',
        emoji: '📚',
        patterns: [
            'docs/',
            'storage/api-docs',
            'README.md',
            'AGENTS.md',
            'CLAUDE.md',
            'WARP.md',
        ],
    },
    {
        type: 'style',
        emoji: '🎨',
        patterns: ['resources/css', 'resources/js', 'vite.config'],
    },
    {
        type: 'feat',
        emoji: '✨',
        patterns: [
            'app/',
            'database/migrations',
            'database/seeders',
            'database/factories',
            'routes/',
            'resources/',
        ],
    },
];

function detectType(changedFiles) {
    const scores = Object.fromEntries(TYPE_MAP.map(({ type }) => [type, 0]));

    for (const file of changedFiles) {
        for (const { type, patterns } of TYPE_MAP) {
            if (patterns.some((p) => file.includes(p))) {
                scores[type]++;
                break;
            }
        }
    }

    const winner = Object.entries(scores).sort((a, b) => b[1] - a[1])[0];

    if (!winner || winner[1] === 0) {
        const hasAppCode = changedFiles.some(
            (f) => f.startsWith('app/') || f.startsWith('database/'),
        );

        return hasAppCode ? 'feat' : 'chore';
    }

    return winner[0];
}

function getEmoji(type) {
    return TYPE_MAP.find((t) => t.type === type)?.emoji ?? '🔧';
}

// ── message extraction from conversation ──────────────────────────────────────

function extractText(content) {
    if (typeof content === 'string') {
        return content;
    }

    if (Array.isArray(content)) {
        return content
            .map((c) =>
                typeof c === 'string'
                    ? c
                    : (c?.text ?? c?.content ?? ''),
            )
            .join(' ');
    }

    if (content && typeof content === 'object') {
        return content.text ?? content.content ?? '';
    }

    return '';
}

function normalizeText(text) {
    return String(text)
        .replace(/```[\s\S]*?```/g, ' ')
        .replace(/`[^`]*`/g, ' ')
        .replace(/\[[^\]]*\]\([^)]*\)/g, ' ')
        .replace(/[>#*_~|]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Matches present-tense action verbs that describe what was implemented,
 * capped at 120 chars to keep commit messages readable.
 */
const ACTION_RE =
    /\b(Adds?|Creates?|Implements?|Updates?|Fixes?|Refactors?|Removes?|Renames?|Introduces?|Migrates?|Extends?|Replaces?|Extracts?|Moves?|Converts?|Enables?|Disables?|Configures?|Registers?|Builds?|Scaffolds?|Wires?|Exposes?|Generates?)\b([^.!?\n]{10,110})/i;

function extractSummaryFromConversation(input) {
    if (!input || typeof input !== 'object') {
        return null;
    }

    const messages =
        input.messages ??
        input.transcript ??
        input.conversation ??
        [];

    if (!Array.isArray(messages) || messages.length === 0) {
        return null;
    }

    // Scan assistant messages in reverse — last response most relevant
    const assistantMessages = messages
        .filter((m) => m.role === 'assistant' || m.type === 'assistant')
        .reverse();

    for (const msg of assistantMessages.slice(0, 5)) {
        const raw = msg.content ?? msg.message ?? msg.text ?? '';
        const text = normalizeText(extractText(raw));
        const match = text.match(ACTION_RE);

        if (match) {
            return match[0].trim().slice(0, 120);
        }
    }

    // Fall back to the first substantial user prompt
    const userMessages = messages.filter(
        (m) => m.role === 'user' || m.type === 'human',
    );

    const firstUser = userMessages[0];

    if (firstUser) {
        const raw = firstUser.content ?? firstUser.message ?? firstUser.text ?? '';
        const text = normalizeText(extractText(raw));
        const firstLine = text.split(/[.\n]/)[0].trim();

        if (firstLine.length > 8 && firstLine.length < 120) {
            return firstLine;
        }
    }

    return null;
}

// ── file summary fallback ─────────────────────────────────────────────────────

function buildFileSummary(changedFiles) {
    const groups = {};

    for (const file of changedFiles) {
        const parts = file.split('/');
        const group =
            parts.length >= 2 ? `${parts[0]}/${parts[1]}` : parts[0];
        groups[group] = (groups[group] ?? 0) + 1;
    }

    const top = Object.entries(groups)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 3)
        .map(([dir]) => dir);

    if (top.length === 0) {
        return 'various files';
    }

    if (top.length === 1) {
        return top[0];
    }

    return `${top.slice(0, -1).join(', ')} and ${top[top.length - 1]}`;
}

// ── main ──────────────────────────────────────────────────────────────────────

const TYPE_VERB = {
    feat: 'Adds',
    fix: 'Fixes',
    refactor: 'Refactors',
    test: 'Adds tests for',
    docs: 'Updates docs in',
    chore: 'Updates',
    ci: 'Updates CI for',
    style: 'Styles',
    perf: 'Optimises',
};

async function main() {
    if (!isGitRepo()) {
        process.exit(0);
    }

    // Read conversation context from hook stdin
    const raw = await readStdin();
    let input = {};

    try {
        input = JSON.parse(raw);
    } catch {
        // No valid JSON — proceed without conversation context
    }

    // Check for uncommitted changes
    const statusOutput = git('status', '--porcelain');

    if (!statusOutput) {
        process.exit(0);
    }

    const changedFiles = statusOutput
        .split('\n')
        .filter(Boolean)
        .map((line) => line.slice(3).trim());

    if (changedFiles.length === 0) {
        process.exit(0);
    }

    // Determine conventional commit type and emoji
    const type = detectType(changedFiles);
    const emoji = getEmoji(type);

    // Build description
    const extracted = extractSummaryFromConversation(input);
    let description;

    if (extracted) {
        description =
            extracted.charAt(0).toUpperCase() + extracted.slice(1);
    } else {
        const verb = TYPE_VERB[type] ?? 'Updates';
        description = `${verb} ${buildFileSummary(changedFiles)}`;
    }

    const commitMessage = `${emoji} ${type}: ${description}`;

    // Stage everything
    try {
        execSync('git add -A', { stdio: 'inherit', cwd: process.cwd() });
    } catch (err) {
        console.error('[auto-commit] git add failed:', err.message);
        process.exit(1);
    }

    // Bail if nothing ended up staged (e.g. only ignored files changed)
    const staged = git('diff', '--cached', '--name-only');

    if (!staged) {
        console.log('[auto-commit] Nothing staged — skipping commit.');
        process.exit(0);
    }

    // Commit
    try {
        execSync(`git commit -m ${JSON.stringify(commitMessage)}`, {
            stdio: 'inherit',
            cwd: process.cwd(),
        });
        console.log(`[auto-commit] ✅ Committed: ${commitMessage}`);
    } catch (err) {
        console.error('[auto-commit] git commit failed:', err.message);
        process.exit(1);
    }
}

main().catch((err) => {
    // Non-blocking: a crash here must not disrupt the session-end flow
    console.error('[auto-commit] Unexpected error:', err.message);
    process.exit(0);
});
