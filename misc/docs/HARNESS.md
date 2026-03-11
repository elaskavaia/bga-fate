## Overview

The local harness lets you develop and validate game UI without a real BGA server. You set up a specific game state, run one command, and get a static HTML snapshot showing how the game looks — tokens, buttons, tooltips, and game log — all rendered by the real client code.

**Goal**: catch UI bugs (wrong tokens, missing buttons, broken tooltips, bad log text) locally before deploying to BGA.

## Problem

The game is client-server. Server logic (PHP) and client logic (TypeScript) are tightly coupled: the server drives the game state and emits notifications; the client renders them. The real BGA server is not accessible locally, so there is no way to test the two together without deploying.

## Design

The key insight is that the client-server contract is narrow: two JSON payloads.

- **Game state** (`gamedatas`) — a full snapshot returned on page load; client calls `game.setup(gamedatas)` to build the DOM
- **Notifications** — a sequence of events emitted during an action; client calls `notif_xxx(args)` for each one in order

We can produce both payloads locally using the existing PHP test infrastructure (same in-memory stubs as unit tests), then feed them to the real client code running in a simulated browser (JSDOM).

Two parts run in sequence:

1. **PHP runner** — sets up game state via a scenario or debug function, captures the resulting game state and notifications as JSON
2. **JS renderer** — loads that JSON into a simulated browser, runs the client code (`setup` + notification replay), and writes a static HTML snapshot

The snapshot has the game CSS inlined and extra inspection sections appended: a click-handler registry, a tooltip registry, and a game log — so it can be read as a file without running a browser.

## What we had to stub

The BGA framework does a lot of work that normally only runs on their servers or in their browser environment. To run locally we had to replicate or stub everything the game code depends on.

**Server side (PHP):**
- **Database** — all token and machine state normally lives in MySQL; replaced with in-memory implementations (`TokensInMem`, `MachineInMem`) that mirror the real DB API
- **Framework base class** (`Table`) — the BGA `Table` class wires up DB connections, player data, notifications, game state transitions; replaced with stubs in `bga-sharedcode` that provide the same interface without a real server
- **Notify** — `$this->notify->all(...)` normally pushes to BGA's real-time channel; replaced with `RecordingNotify` that captures all calls as a plain array
- **Game state machine** — `gamestate->jumpToState()`, `changeActivePlayer()`, etc. are normally server-side BGA infra; stubbed to track current state in memory
- **Harness extension** (`GameHarness`) — adds `getAllDatas()` with a `gamestate` field (real BGA appends this automatically on reload, our stub does not)

**Client side (JS):**
- **HTML template** — the real BGA page is served by their platform; we provide a minimal `template.html` with the same element IDs the client expects (`#game_play_area`, `#generalactions`, `#pagemaintitletext`, `#player_boards`, `#logs`, etc.)
- **Minimal CSS** — BGA's own layout CSS is not available; `common.css` provides just enough structural rules (flex layout, action bar, player panels, log) for the snapshot to be readable
- **Framework globals** — `$`, `_`, `gameui`, `ebg` and other globals the client code calls; stubbed with enough behaviour to let `game.setup()` and notification handlers run without errors
- **`format_string_recursive`** — BGA's log formatting function; fully reimplemented including i18n, nested log objects, separator joining, and `bgaFormatText` hook for token/place name resolution
- **Tooltip capture** — `addTooltipHtml` normally registers tooltips with a real dijit widget; intercepted to collect them in a registry that is appended to the snapshot
- **Player panels** — normally injected into the page by BGA's server-side rendering; built from `gamedatas.players` in the renderer

## Saved state

To avoid re-running setup (which involves shuffling) on every run, the harness persists the full in-memory DB to a JSON file after each run and reloads it at the start of the next. This makes runs fast and reproducible.

## Scenarios and debug functions

Two ways to drive the server:

- **Scenario** — a script of sequential actions (same endpoints as the real client: `action_resolve`, `action_skip`, etc.), used to build up a saved state incrementally
- **Debug function** — a single PHP function that sets up a specific state in one shot, used for inspecting a particular operation or UI state without replaying history

---

## Usage

```
npm run play                                         # default 'setup' scenario
npm run play --scenario=hero_move                    # named scenario
npm run play --debug=debug_Op_move                   # debug function, persists to debug/ state
npm run play --debug=debug_Op_move --scenario=setup  # debug function against scenario state (read-only)
npm run play --reset                                 # ignore saved state, start fresh
HARNESS_VERBOSE=1 npm run play                       # show full server console output
```

Then read `staging/snapshot.html`.

## Snapshot inspection

| Section | What it shows |
|---|---|
| `#pagemaintitletext` | Prompt from `getPrompt()` for the current operation |
| `#generalactions` | Action buttons with `data-action` attributes |
| `#logs` | Formatted game log entries |
| `#harness-click-registry` | All clickable elements with id, class, and action payload |
| `#harness-tooltip-registry` | All registered tooltips |

## Adding debug functions

All `debug_*` functions go in `misc/harness/GameHarness.php` (not `Game.php`).

Typical pattern:
```php
public function debug_Op_move(): void {
    $this->debug_setupGame_h1(); // or assume --scenario=setup state
    $playerId = $this->getCurrentPlayerId();
    $this->machine->push("move", $playerId, []);
    $this->gamestate->jumpToState(StateConstants::STATE_PLAYER_TURN);
}
```

See [PROCEDURES.md](PROCEDURES.md#validating-operation-ui-in-harness) for the full before/after diff workflow.

---

## Implementation details

### npm script

```json
"play": "php8.4 misc/harness/play.php ${npm_config_debug:+-debug $npm_config_debug} ${npm_config_reset:+-reset} ${npm_config_scenario:-} && ts-node --project misc/harness/tsconfig.json misc/harness/render.ts ${npm_config_scenario:-}"
```

### PHP runner — `misc/harness/play.php`

Bootstraps the same autoloader as PHPUnit tests, instantiates `GameHarness`, then:

1. Parse CLI flags: `-debug <fn>`, `-reset`, and optional play name (default: `setup`; debug default: `debug`)
2. Auto-seed `staging/plays/<name>/script.json` from `misc/harness/plays/<name>.json` if the example is newer
3. Load `staging/plays/<name>/db.json` into `GameHarness` if present (tokens, machine, gamestate, players, curid)
4. `RecordingNotify` is already set up by `GameUT::__construct()` — no extra swap needed
5. For each step in `script.json`, dispatch to `action_resolve / action_skip / action_whatever / action_undo` or any `debug_*` method via reflection
6. After each step: run the dispatch loop (if in `GameDispatch` state), then emit a synthetic `gameStateChange` notification
7. In `--debug` mode: call the single named `debug_*` function, run dispatch loop, emit `gameStateChange`
8. Write `staging/gamedatas.json`, `staging/notifications.json`, and `staging/plays/<name>/db.json`

Debug mode always writes to `staging/plays/debug/` to avoid corrupting source scenario state.

### JS renderer — `misc/harness/render.ts`

1. Read `staging/gamedatas.json` and `staging/notifications.json`
2. Load `misc/harness/template.html` as the JSDOM base document
3. Build player panels from `gamedatas.players` (framework normally generates these server-side)
4. Set up JSDOM globals and BGA framework stubs (`$`, `_`, `gameui`, `ebg`, etc.)
   - `gameui.addTooltipHtml(nodeId, html)` — captured in a tooltip registry
   - `gameui.format_string_recursive(str, args)` — full implementation: null handling, i18n, nested `{log,args}` recursion, separator joining, `${key}` substitution; calls `gameui.bgaFormatText` hook
   - `gameui.bgaFormatText` — wired to `game.bgaFormatText(str, args)` so token/place names resolve correctly in the log
5. Instantiate `Game`; wire `bgaFormatText`
6. Call `game.setup(gamedatas)` — builds initial DOM
7. Call `enterState(gamedatas.gamestate)` — triggers `onEnteringState` (sets title + buttons)
8. For each notification:
   - If `notif.log` is non-empty: format and append to `#logs`
   - If `type === "gameStateChange"`: re-enter state (clears + re-renders title/buttons)
   - Otherwise: call `await game.notif_<type>(notif.args)` if handler exists
9. Inline `common.css` + `fate.css` into `<head>`
10. Append `#harness-click-registry`, `#harness-tooltip-registry`, and a click-logging script
11. Write `staging/snapshot.html`

### RecordingNotify

Lives in `modules/php/Tests/GameUT.php`, used by both harness and unit tests.

- Implements `addDecorator(callable $fn)` so all notify decorators actually run
- `GameUT::__construct` installs it after `parent::__construct()`, then calls `registerNotifyDecorators()` to re-register decorators on the new instance
- Decorator chain: **Base** fills in `player_name`/`you`; **Game** fills in `reason = ""`

### Scenario format

`staging/plays/<name>/script.json`:

```json
{
  "current_player_id": 10,
  "steps": [
    { "endpoint": "action_resolve", "data": { "target": "move" } },
    { "endpoint": "action_resolve", "data": { "target": "hex_5_5" } }
  ]
}
```

Optional per-step: `"reload": true` — writes `gamedatas.json` after that step.

Available endpoints — **actions**: `action_resolve`, `action_skip`, `action_undo`, `action_whatever`; **debug**: any `debug_*` method on `GameHarness`, params matched by name via reflection.

### DB state format

```json
{
  "tokens":    [ { "key": "hero_1", "location": "hex_5_5", "state": 0 } ],
  "machine":   [ { "id": 1, "rank": 1, "type": "move", "owner": "6cd0f6", "pool": "main", "data": null } ],
  "gamestate": { "state_id": 10, "active_player": 10 },
  "players":   [ { "player_id": 10, "player_no": 1, "player_color": "6cd0f6", "player_name": "player1", "player_zombie": 0, "player_eliminated": 0 } ]
}
```

---

## Status

- [x] One-time setup steps (staging/, .gitignore, package.json play script)
- [x] Extract `GameUT` into `modules/php/Tests/GameUT.php` (no PHPUnit dependency)
- [x] Write `play.php` with scenario + debug mode
- [x] Add `getAllDatas()` to `GameUT`; `GameHarness` extends it with `gamestate` field
- [x] Write `misc/harness/plays/setup.json`
- [x] Write `misc/harness/template.html` (BGA HTML skeleton)
- [x] Write `misc/harness/tsconfig.json`
- [x] Write `misc/harness/render.ts` with notification replay, game log, click/tooltip registries
- [x] `RecordingNotify` with decorator support (in `GameUT.php`, used by both harness and unit tests)
- [x] `format_string_recursive` with `bgaFormatText` delegation for token/place name resolution
- [x] Test Flow 1: initial setup snapshot (`npm run play` → staging/snapshot.html ✓)
- [x] Test Flow 2: action + notification replay (PlayerTurn state + buttons render ✓)
- [ ] (Optional) Add Playwright screenshot step
