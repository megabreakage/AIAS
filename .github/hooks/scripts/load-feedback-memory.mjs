#!/usr/bin/env node

import { existsSync, readFileSync } from 'node:fs';

const FEEDBACK_PATH = '.github/hooks/feedback/agent-feedback.md';

function compactLines(text, limit = 40) {
    return text
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .slice(0, limit)
        .join('\n');
}

if (!existsSync(FEEDBACK_PATH)) {
    process.stdout.write(`${JSON.stringify({ continue: true })}\n`);
    process.exit(0);
}

const raw = readFileSync(FEEDBACK_PATH, 'utf8');
const summary = compactLines(raw, 60);

process.stdout.write(
    `${JSON.stringify({
        continue: true,
        systemMessage: `Reloaded feedback memory:\n${summary}`,
    })}\n`
);
