#!/usr/bin/env node

import { readFileSync } from 'node:fs';

const STOP_WORDS = new Set(['the', 'is', 'am', 'are']);
const FILLER_WORDS = new Set(['just', 'really', 'very', 'actually', 'basically', 'literally']);

function parseInput(raw) {
    try {
        return JSON.parse(raw);
    } catch {
        return {};
    }
}

function stripFormatting(text) {
    return text
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
        .map((chunk) => chunk.trim())
        .filter(Boolean);
}

function pruneWords(sentence) {
    const words = sentence
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, ' ')
        .split(/\s+/)
        .filter(Boolean)
        .filter((word) => !STOP_WORDS.has(word))
        .filter((word) => !FILLER_WORDS.has(word));

    if (words.length < 3) {
        return '';
    }

    const capped = words.slice(0, 6);

    if (capped.length < 3) {
        return '';
    }

    return capped.join(' ');
}

function collectText(input) {
    const collected = [];
    const stack = [input];
    const seen = new Set();

    while (stack.length > 0) {
        const current = stack.pop();

        if (!current || seen.has(current)) {
            continue;
        }

        if (typeof current === 'string') {
            const cleaned = stripFormatting(current);
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
            seen.add(current);
            for (const [key, value] of Object.entries(current)) {
                if (key === 'content' || key === 'message' || key === 'text' || key === 'prompt' || key === 'summary') {
                    stack.push(value);
                }
            }
        }
    }

    return collected;
}

function buildBullets(textBlocks) {
    const phrases = [];

    for (const block of textBlocks) {
        const sentences = splitSentences(block);

        for (const sentence of sentences) {
            const phrase = pruneWords(sentence);
            if (phrase.length > 0) {
                phrases.push(phrase);
            }
        }
    }

    const unique = [];
    const seen = new Set();

    for (const phrase of phrases) {
        if (seen.has(phrase)) {
            continue;
        }
        seen.add(phrase);
        unique.push(phrase);
        if (unique.length >= 12) {
            break;
        }
    }

    return unique;
}

function buildOutput(bullets) {
    const finalBullets = bullets.length > 0
        ? bullets
        : ['feature planning complete', 'feature updates complete', 'next actions ready'];

    return {
        continue: true,
        systemMessage: ['Compact context', ...finalBullets.map((line) => `- ${line}`)].join('\n'),
    };
}

const raw = readFileSync(0, 'utf8');
const payload = parseInput(raw);
const textBlocks = collectText(payload);
const bullets = buildBullets(textBlocks);
const output = buildOutput(bullets);

process.stdout.write(`${JSON.stringify(output)}\n`);
