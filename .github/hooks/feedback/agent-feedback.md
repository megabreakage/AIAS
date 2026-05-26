# Agent Feedback Memory

Last updated: 2026-05-26T20:45:00.000Z
Hook event: manual-baseline

## Corrections User Made
- Run tools first.
- Show concrete result, then stop.
- Remove filler words from responses.
- Do not narrate obvious steps.
- Keep answers direct, not padded.

## Preferences User Stated
- No filler words: the, is, am, are.
- Direct answers only.
- Short 3-6 word sentences preferred.
- Run tools first, show result, stop.
- No narration or explanation unless asked.

## What To Do Differently Next Time
- Run tools before narrative output.
- Keep responses direct and outcome-first.
- Keep wording compact and strict.
- Re-check preference memory before final output.
- Prioritize explicit user style over defaults.

## Usage
- Load this file at session start and before final response.
- Treat these rules as higher priority than default style.