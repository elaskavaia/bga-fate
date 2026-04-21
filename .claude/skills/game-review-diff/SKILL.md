---
name: game-review-diff
description: Review uncommitted changes in the Fate BGA project for bugs, one at a time. Runs the generic review-diff checks plus Fate-specific conventions (Material CSV, operations, tests, BGA patterns). Use when the user wants to review their current changes in this repo before committing.
---

# Fate BGA Diff Review

Run the generic review process from `~/.claude/skills/review-diff/SKILL.md` first — read and apply it. Then additionally check for Fate-specific issues below.

## Fate-specific checks

Each bullet is a **preferred pattern** — flag any code that doesn't follow it.

### Operations (`modules/php/Operations/Op_*.php`)

- Use `$this->getCheckedArg()` to read user-picked targets in `resolve()`, not raw `$args[...]`.
- Omit `token_name` / `place_name` from `$args` when calling `dbSetTokenLocation($tokenId, ...)` — they are auto-added.
- Use `systemAssert($msg, $cond)` with message `"ERR:<op>:<what>:$ctx"` for conditions that should never occur at runtime, not silent `return ["q" => ERR_PREREQ]`.
- Use `getPart($tokenId, $n)` instead of `explode("_", $tokenId)[$n]` — `getPart` has bounds checking.

### Player / hero mapping

- Use `$this->game->getHeroTokenId($owner)` to get a hero token, not `custom_getPlayerNoById()` (latter returns player_id like 10, 11… in tests).
- Guard `getHeroTokenId` calls when setup may be incomplete — it `systemAssert`s if no `card_hero_*` is on `tableau_$owner`.

### Tests

- Call `createTokens()` in `setUp` when a test depends on token locations.
- Add `tests/Operations/Op_xxxTest.php` alongside any new `Op_xxx.php`.
- Use `PCOLOR` / `BCOLOR` constants (`6cd0f6`, `982fff`) instead of hardcoded player colors.
- Match the assertion style of neighboring `tests/Operations/Op_*Test.php` files.

### Comments

- Keep only WHY-comments; remove WHAT-comments on BGA boilerplate (`// send notification`, `// move token`).
- Update or remove comments that reference renamed/removed operations, cards, or hex IDs.

### Client-side (`src/`)

- Run `npm run build` after editing `.ts` / `.scss` (refreshes generated `.js` and `fate.css`).
- Add a handler in `src/Game.ts` for every new notification sent from PHP.

### Docs

- Mark completed features with `[x]` in [PLAN.md](../../../misc/docs/PLAN.md).

## Not a bug — do NOT flag

These have been raised before as review findings but are intentional / correct:

- `getPossibleMoves()` returning `['a','b']` — this is the acceptable shape, not a smell.
- Operations with only a `resolve()` and no explicit `getPossibleMoves()` — some ops don't need targets.
- Generated sections in `modules/php/Material.php` containing lots of near-identical entries — that's the CSV; don't suggest deduplication.
- `systemAssert` messages that look duplicated across operations — the `"ERR:<op>:..."` prefix differs.

## Reporting

Same as generic skill: report one issue at a time with Yes/No prompt, auto-fix trivia silently, show file:line for each issue.
