---
name: game-bug-fix
description: Fix one or more bugs in a BGA game project end to end - reproduce with a test, implement the fix, blind-review it, triage the review findings, update the tracking doc, and commit. Source of the bug can be an entry in the project's bug-tracking doc, a line in TODO.md, or a description typed directly as skill params. Use when the user wants to fix, implement, or resolve a bug (or a batch of bugs), e.g. "fix BGA #12345", "fix the <card/feature> bug", "work through those bugs".
argument-hint: bug id / description / doc reference, or nothing to be asked which
---

# BGA Bug Fix

Take a bug from described to committed. This skill is about **fixing only** - it does not decide a bug's state or touch the bug tracker; it acts on a bug you already know needs fixing. One bug at a time, each through the same loop: reproduce -> fix -> blind review -> triage findings -> update doc -> commit -> next.

## 0. Figure out which bug(s), and in what order

The bug can come from three places - accept any:

- **A description in the skill params** - free-form text or a `BGA #<id>`. If it's an id, grep the project docs for it first; a matching entry may already spell out the root cause and fix.
- **TODO.md** (in the project docs dir) - bug lines, often carrying a root-cause note and a test name.
- **The project's bug-tracking doc** (a `BUG_TRIAGE.md` or similar - search if unsure) - richer entries with root cause + proposed fix + the name of a pinning test.

If the request is a batch ("these three", "the ones in TODO"), collect the entries and **do them sequentially, not in parallel** - each bug gets its own review and its own commit so a bad fix is isolated.

Do not silently pick a different bug than asked. If scope is ambiguous, ask in plain text (one question, numbered options) before touching code.

## Per-bug loop

Run steps 1-7 for each bug before moving to the next.

### 1. Understand the root cause

If the entry documents a file:line cause and a proposed fix, trust it but verify the cited lines still match - code moves. If there is no documented cause, trace it yourself and write down what you found before editing.

### 2. Get a reproducing test FIRST (before any production edit)

Do not touch production code until a test reproduces the bug. This proves you understand the defect and gives the fix something to satisfy.

**Check first - the test may already be written.** A **pinning test** often already exists (from a prior triage pass): a green test that asserts the current *buggy* behavior, with a comment like "flip once fixed". Grep for the test name in the entry, and for the `BGA #<id>`, before writing anything. If it exists, run it and confirm it passes against the buggy code - that green test *is* your reproducer; you will flip it in step 4.

**If none exists, write one, by layer** (the test framework is shared across BGA game projects - same layout and scripts everywhere):

- **PHP (server logic):** a scenario test in `tests/Campaign/` that drives a full turn through the harness, or a targeted unit test under `tests/` mirroring source layout when the bug is isolated to one unit. Run one file with `npm run test -- tests/<File>.php`.
- **JS (client logic):** a unit test in `src/tests/*.spec.ts` (mocha+chai, `npm run jstests`). Only for logic you can exercise without a live DOM/BGA framework.
- **CSS / pure display:** there is **no test framework** for this. Skip the test, verify by eye (local harness snapshot or Studio), and carry that "not test-covered" caveat into step 7.

**Run it and confirm it reproduces.** A freshly written reproducer should assert the *correct* expected behavior and therefore **fail now** in the way the bug describes - watch it fail. If it passes, you have not reproduced the bug: stop and re-investigate rather than "fixing" the wrong thing. (An existing pinning test is the inverse - it asserts buggy behavior and passes now; either way you must see the current behavior with your own eyes before editing.)

### 3. Implement the fix

Simplest thing that works. Reuse existing helpers - grep for one before writing a new one. Touch only what the bug needs; do not drive-by-refactor. Follow the entry's proposed fix unless you find it wrong - if you do, say so, don't silently diverge. Follow the project's own conventions and procedures (e.g. for adding a new game element or operation).

- Edited a material/data CSV? Regenerate the generated code (`npm run genmat`) rather than hand-editing generated sections.
- Edited client `.ts`/`.scss`? Rebuild (`npm run build`, or `build:ts`) and commit the generated output alongside the source.

### 4. Land the tests green

The reproducer from step 2 must now pass, and the suite must stay green.

- **The reproducer flips.** If you wrote a fresh reproducer (asserting correct behavior, red until now), it should go green with the fix - confirm it. If you reused an existing **pinning test** (green, asserting buggy behavior), flip its assertion to the correct behavior and update its docstring from "reproduces/pins bug" to "verifies the fix". If the entry named a method that doesn't match the actual one, rename to what it says (they drift).
- **Relax over-tight assertions the bug locked in.** An exact-equality assertion can be what froze the bug - loosen it to assert the property that matters.
- **Add missing coverage.** If the fix has behavior the reproducer doesn't reach (e.g. it only checked that an action is offered, not that it resolves correctly), add a test that drives it end to end. Something that becomes reachable but then errors on execution is a worse bug than the original.
- Server-side: targeted run first (`npm run test -- tests/<File>.php`). Then, before commit, the full suite (`npm run tests > /tmp/test_output.txt 2>&1`, read the file) to catch regressions. Also `npm run lint:php`.
- Client-side: `npm run jstests`. CSS/pure-display fixes have no test - carry the "not test-covered" caveat to step 7.

### 5. Blind review (fresh subagent, non-interactive)

Spawn a **general-purpose subagent** to run the project's diff-review skill on the uncommitted diff. "Blind" = it sees only the diff, not your reasoning, so it reviews independently. In its prompt state explicitly:

- This is a **non-interactive** run: do **not** stop to ask questions or wait - review everything and **print all findings at once** in one final report (severity, file:line, problem, concrete fix).
- **Report only, modify nothing.**
- Give it the scope: the `BGA #<id>`, the symptom, the files touched, and what to scrutinize (correctness vs the expected behavior, convention match with siblings, edge cases). Mention if the suite already passes.

Run it synchronously (you need the findings before committing).

### 6. Triage the review findings - address or justify each

You decide what is real; the reviewer is advice, not orders.

- **Valid MEDIUM+ (duplication, correctness, missing edge case):** fix it now, re-run the affected test.
- **LOW / convention / informational:** address if cheap and right; otherwise accept and move on. Do not gold-plate (a scenario test that fully covers behavior can stand in for a missing unit test).
- **Out-of-scope / pre-existing:** leave it, don't expand the diff.
- **Never argue a fix into the tree that the review shows is wrong.** If a finding reveals the approach is flawed, redo the fix.

### 7. Update the tracking doc, then commit

- If the bug came from a tracking doc (TODO / bug-tracking doc), flip its entry to `[x]` and rewrite it as **FIXED**: one or two lines on what the fix was and where, plus the test name(s). Drop the now-stale root-cause prose or past-tense it.
- **Anything needing the user's eyes goes in the entry as a `NOTE`** - do not stop and ask AFK. Specifically flag:
  - **Client-only fixes not verified in a real browser** - display bugs the harness/jstests can't confirm. Say "FIXED (client-side, NOT browser-verified - please confirm on Studio)" and keep any reporter/table id for a manual check.
  - **Modeling/design judgment calls** you made, so the user can veto.
- Pre-commit: lint + tests green (step 4). Then commit **only this bug's files** (not unrelated pre-existing `M` files in the tree). Write a **fresh** commit subject derived from the actual diff, present-tense, and reference `BGA #<id>` in the body.

Commit is part of this loop because invoking this skill is the request to fix-and-commit. Still: never `push`, and never fold in unrelated staged changes.

## After the batch

Report a short per-bug summary (commit hash, one line, any NOTE flagged for the user). Do **not** run `npm run predeploy` unless asked - it is slow; offer it. Update any plan/checklist doc if the bug was tracked there.
