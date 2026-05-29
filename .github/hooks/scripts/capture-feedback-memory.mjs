#!/usr/bin/env node

import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';

const OUTPUT_PATH = '.github/hooks/feedback/agent-feedback.md';

const PREFERENCE_PATTERNS = [
    /\balways\b/i,
    /\bnever\b/i,
    /\bprefer\b/i,
    /\bmust\b/i,
    /\bdo not\b/i,
    /\bdon't\b/i,
    /\bavoid\b/i,
    /\bonly\b/i,
    /\bdirect\b/i,
    /\bconcise\b/i,
    /\bshort\b/i,
    /\btools first\b/i,
    /\bno narration\b/i,
];

const CORRECTION_PATTERNS = [
    /\byou (should|must|need to|have to)\b/i,
    /\bnext time\b/i,
    /\bnot like this\b/i,
    /\binstead\b/i,
    /\bstop\b/i,
    /\bwrong\b/i,
    /\bfix\b/i,
];

function parseInput(raw) {
    try {
        return JSON.parse(raw);
    } catch {
        return {};
    }
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

function splitSentences(text) {
    return text
        .split(/[.!?\n;:]+/)
        .map((line) => line.trim())
        .filter((line) => line.length >= 8);
}

function collectText(input) {
    const collected = [];
    const stack = [input];
    const seen = new Set();

    while (stack.length > 0) {
        const current = stack.pop();

        if (!current) {
            continue;
        }

        if (typeof current === 'string') {
            const cleaned = normalizeText(current);
            if (cleaned.length > 0) {
                collected.push(cleaned);
            }
            continue;
        }

        if (Array.isArray(current)) {
            for (let i = current.length - 1; i >= 0; i -= 1) {
                stack.push(current[i]);
            }
            continue;
        }

        if (typeof current === 'object') {
            if (seen.has(current)) {
                continue;
            }
            seen.add(current);

            for (const [key, value] of Object.entries(current)) {
                if (
                    key === 'content' ||
                    key === 'message' ||
                    key === 'text' ||
                    key === 'prompt' ||
                    key === 'summary' ||
                    key === 'instruction'
                ) {
                    stack.push(value);
                }
            }
        }
    }

    return collected;
}

function pickLines(sentences, patterns, maxItems = 25) {
    const out = [];
    const seen = new Set();

    for (const sentence of sentences) {
        const candidate = sentence.trim();

        if (candidate.length < 8 || candidate.length > 220) {
            continue;
        }

        if (!patterns.some((regex) => regex.test(candidate))) {
            continue;
        }

        const key = candidate.toLowerCase();
        if (seen.has(key)) {
            continue;
        }

        seen.add(key);
        out.push(candidate);

        if (out.length >= maxItems) {
            break;
        }
    }

    return out;
}

function deriveNextTime(corrections, preferences) {
    const nextTime = [];

    const merged = [...corrections, ...preferences].join(' | ').toLowerCase();

    if (/(tools first|run tools first)/.test(merged)) {
        nextTime.push('Run tools before narrative output.');
    }

    if (/(direct answers|direct)/.test(merged)) {
        nextTime.push('Keep responses direct and outcome-first.');
    }

    if (/(short|concise|3-6 word|no filler)/.test(merged)) {
        nextTime.push('Keep phrasing compact with minimal filler.');
    }

    if (/(no narration|no explanation)/.test(merged)) {
        nextTime.push('Avoid explanatory narration unless requested.');
    }

    if (nextTime.length === 0) {
        nextTime.push('Re-check latest corrections before final response.');
        nextTime.push('Prioritize explicit user constraints over defaults.');
    }

    return nextTime;
}

function parseExistingSection(sectionHeader) {
    try {
        const existing = readFileSync(OUTPUT_PATH, 'utf8');

        const escapedHeader = sectionHeader.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const matcher = new RegExp(`## ${escapedHeader}\\n([\\s\\S]*?)(?:\\n## |$)`, 'm');
        const section = existing.match(matcher)?.[1] || '';

        const lines = section
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line.startsWith('- '))
            .map((line) => line.slice(2).trim())
            .filter(Boolean);

        return lines;
    } catch {
        return [];
    }
}

function mergeUnique(primary, secondary, maxItems = 40) {
    const out = [];
    const seen = new Set();

    for (const line of [...primary, ...secondary]) {
        const key = line.toLowerCase();
        if (seen.has(key)) {
            continue;
        }
        seen.add(key);
        out.push(line);
        if (out.length >= maxItems) {
            break;
        }
    }

    return out;
}

function toBullets(lines) {
    if (lines.length === 0) {
        return '- none captured';
    }

    return lines.map((line) => `- ${line}`).join('\n');
}

function buildMarkdown({ corrections, preferences, nextTime, eventName }) {
    const now = new Date().toISOString();

    return [
        '# Agent Feedback Memory',
        '',
        `Last updated: ${now}`,
        `Hook event: ${eventName || 'unknown'}`,
        '',
        '## Corrections User Made',
        toBullets(corrections),
        '',
        '## Preferences User Stated',
        toBullets(preferences),
        '',
        '## What To Do Differently Next Time',
        toBullets(nextTime),
        '',
        '## Usage',
        '- Load this file at session start and before final response.',
        '- Treat these rules as higher priority than default style.',
    ].join('\n');
}

const raw = readFileSync(0, 'utf8');
const payload = parseInput(raw);

const textBlocks = collectText(payload);
const sentences = textBlocks.flatMap(splitSentences);

const extractedCorrections = pickLines(sentences, CORRECTION_PATTERNS, 20);
const extractedPreferences = pickLines(sentences, PREFERENCE_PATTERNS, 25);
const nextTime = deriveNextTime(extractedCorrections, extractedPreferences);

const existingCorrections = parseExistingSection('Corrections User Made');
const existingPreferences = parseExistingSection('Preferences User Stated');

const mergedCorrections = mergeUnique(extractedCorrections, existingCorrections, 25);
const mergedPreferences = mergeUnique(extractedPreferences, existingPreferences, 30);

const markdown = buildMarkdown({
    corrections: mergedCorrections,
    preferences: mergedPreferences,
    nextTime,
    eventName: payload?.hook_event_name || payload?.hookEventName,
});

mkdirSync('.github/hooks/feedback', { recursive: true });
writeFileSync(OUTPUT_PATH, markdown, 'utf8');

process.stdout.write(
    `${JSON.stringify({
        continue: true,
        systemMessage: `Feedback memory saved to ${OUTPUT_PATH}`,
    })}\n`
);
