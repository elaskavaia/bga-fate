# Code Style

How code is written in this project. The shared `game-review-diff` skill reads this file and flags
changed code that breaks it; the "Review: do not flag" section at the end lists patterns a review
must accept. Framework-wide rules (operations, material, method placement) live in that skill and
in [PROCEDURES.md](PROCEDURES.md); this file holds only what is a convention of this repo.

## Naming (all languages)

- Getters start with `get` (`getCardCost`, `getGoldOn`), in PHP and TypeScript alike. This covers
  any side-effect-free method that returns a value, not just field accessors: a `calcScore()` is a
  getter and is named `getScore()`.
- Other sanctioned prefixes: `is`, `has`, `can` for boolean predicates (`isEndOfGame`), and
  `count` for methods that double as barewords in the expression engine (`countCardsInHand`,
  descriptive even then, never `countHand`).
- No bare-noun getters (`cardCost()`), and no new player concepts: use the framework's own
  (`getActivePlayerId`, `getActivePlayerList`, `getMostlyActivePlayerId`).
- Never shorten identifiers (`button`, not `btn`). Concise, not cryptic.

## PHP

- No fully-qualified class names inline (`\Bga\GameFramework\NotificationMessage`): add a `use`
  import and reference the short name.
- Shared operation helpers go on the game's abstract op parent, not on `Game.php`; see
  [DESIGN.md](DESIGN.md).
- System invariants (always true unless there is a bug: hero is on the map, a required data field
  exists) use `systemAssert("ERR:<op>:<what>:$context", $cond)` - message first, condition second.
  Game-state preconditions (no mana, card already used) return an `ERR_*` code instead.

## TypeScript

- No new bare `any`: use a real type or add an explicit
  `// eslint-disable-next-line @typescript-eslint/no-explicit-any` to mark it deliberate.
- Unused-but-required params (overrides, callbacks) are prefixed with `_`.

## Notifications

- Present tense: "${player_name} draws a card", never "drew".
- A private move still informs other players: a `_private`-only notification needs a masked public
  counterpart so opponents see counts change.

## Formatting

- Prettier with the PHP plugin (see `package.json`): print width 140, brace style 1tbs.
- ASCII only in code and docs: no em-dashes, no fancy quotes, no unicode arrows. Exceptions: the
  copyright symbol in a file header, glyphs the UI needs, and characters that are the data being
  matched.
- In committed files, write user-home paths as `~/...`, never `/home/<user>/...`.

## Comments

- Few and short, explaining why, not what. Avoid comments in general.
- Remove WHAT-comments on BGA boilerplate (`// send notification`, `// move token`); keep
  WHY-comments.
- Update or remove comments that reference renamed or removed operations, cards, or hex IDs.
- Never remove commented-out code; the author keeps it deliberately.
- Do not duplicate facts that live in another document.

## Tests

- Tests mirror source: `tests/Operations/Op_xxxTest.php` alongside any new `Op_xxx.php`.
- Match the assertion style of neighboring test files.
- Call `createTokens()` in `setUp` when a test depends on token locations.
- Unit tests use the `PCOLOR` / `BCOLOR` constants (`6cd0f6`, `982fff`) instead of hardcoded
  player colors; campaign tests hardcode no colors at all.

## Review: do not flag

Patterns raised as review findings before that are intentional here. Framework-wide exceptions are
in the `game-review-diff` skill; add only repo-specific ones below.

- Nothing yet.
