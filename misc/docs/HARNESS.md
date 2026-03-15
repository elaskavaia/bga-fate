## Overview

The local harness lets you develop and validate game UI without a real BGA server. You set up a specific game state, run one command, and get a static HTML snapshot showing how the game looks — tokens, buttons, tooltips, and game log — all rendered by the real client code.

**Goal**: 
- catch UI bugs (wrong tokens, missing buttons, broken tooltips, bad log text) locally before deploying to BGA.
- ability to debug server code with local php debugger

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
- **Notify** — `$this->notify->all(...)` normally pushes to BGA's real-time channel; the `Notify` stub in `bga-sharedcode` records all calls to `$this->notify->log` and supports `addDecorator`, so no replacement is needed
- **Game state machine** — `gamestate->jumpToState()`, `changeActivePlayer()`, etc. are normally server-side BGA infra; stubbed to track current state in memory
- **Harness driver** (`GameDriver`) — appends the `gamestate` field to `getAllDatas()` (real BGA adds this automatically on reload, our stub does not); also handles state persistence via `toJson`/`fromJson` and endpoint dispatch via reflection

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
npm run play                                                              # default setup scenario + render
php8.4 tests/Harness/play.php --script tests/Harness/plays/op_roll.json    # named script
php8.4 tests/Harness/play.php --debug debug_Op_move                       # debug function
php8.4 tests/Harness/play.php --debug debug_Op_roll --db staging/db.json  # debug against saved state
php8.4 tests/Harness/play.php --output /tmp/out                           # custom output directory
```

`npm run play` runs the default setup scenario and the JS renderer. For other options, call `play.php` directly, then run the renderer separately if needed:
```
ts-node --project tests/Harness/tsconfig.json tests/Harness/render.ts
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

All `debug_*` functions go in `Game.php`. They assume the game state is already set up (via `--scenario`).

Typical pattern:
```php
public function debug_Op_move(): void {
    $playerId = $this->getCurrentPlayerId();
    $color = $this->getPlayerColorById((int) $playerId);
    $this->machine->push("move", $color, []);
    $this->gamestate->changeActivePlayer($playerId);
    $this->gamestate->jumpToState(StateConstants::STATE_PLAYER_TURN);
}
```

Run with:
```bash
php8.4 tests/Harness/play.php --debug debug_Op_move --scenario tests/Harness/plays/setup.json
ts-node --project tests/Harness/tsconfig.json tests/Harness/render.ts
```

See [PROCEDURES.md](PROCEDURES.md#validating-operation-ui-in-harness) for the full before/after diff workflow.

---

## Implementation details

### npm script

```json
"play": "php8.4 tests/Harness/play.php && ts-node --project tests/Harness/tsconfig.json tests/Harness/render.ts"
```

### PHP runner — `tests/Harness/play.php` + `GameDriver` + `GameWrapper`

**`play.php`** is a thin bootstrap: requires the autoloader, `GameWrapper`, and `GameDriver`, then calls `GameDriver::main(new GameWrapper(), $argv, ...)`.

**`GameDriver`** is fully generic (no game-specific imports). It orchestrates the harness run:

1. `main(game, argv, baseDir, stagingDir)` — static entry point: parses CLI args (`--debug`, `--scenario`, `--db`, `--output`), constructs the driver, runs steps/debug, saves output
2. Constructor takes a `Table` instance, output dir, and current player ID; gets game name from `$game->getGameName()`; builds a state name map by scanning `modules/php/States/` via the `Bga\Games\{name}\States\` namespace
3. `loadDbFromJson(dbPath)` — calls `$game->loadDbState($db)` + restores gamestate/players (framework-level)
4. `runSteps()` — for each step: dispatch the endpoint, run `onEnteringState()`, emit a synthetic `gameStateChange` notification
5. `runDebug()` — calls a single `debug_*` function, then dispatch + emit
6. `saveDbToJson()` — calls `$game->saveDbState()` + saves gamestate/players to `<output>/db.json`
7. `saveGamedatas()` / `saveNotifications()` — writes JSON for the JS renderer

**`GameWrapper`** is the game-specific part. It extends `Game` directly (not `GameUT`) and implements the harness contract. See "GameWrapper contract" section below.

### JS renderer — `tests/Harness/render.ts`

1. Read `staging/gamedatas.json` and `staging/notifications.json`
2. Load `tests/Harness/template.html` as the JSDOM base document
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
9. Inline `common.css` + `<game>.css` into `<head>`
10. Append `#harness-click-registry`, `#harness-tooltip-registry`, and a click-logging script
11. Write `staging/snapshot.html`


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

Available endpoints — **actions**: `action_*`; **debug**: any `debug_*` method on `Game` (or `GameWrapper`), params matched by name via reflection.

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

## GameWrapper contract

`GameDriver` is fully generic — it has no game-specific imports. It communicates with the game through the `GameWrapper` class, which must implement these methods:

### Required methods

| Method | Purpose |
|---|---|
| `getGameName(): string` | Namespace name (e.g. `"Fate"`). Used by GameDriver to discover state classes via `Bga\Games\{name}\States\*` |
| `saveDbState(): array` | Serialize all custom DB tables to an associative array. GameDriver persists this as part of `db.json` |
| `loadDbState(array $db): void` | Restore custom DB tables from the array produced by `saveDbState()` |
| `getAllDatas(): array` | Return game data for the client (must be `public` — BGA framework declares it `protected`) |

### Required from BGA framework (inherited from `Table`)

These are called by GameDriver but already provided by `BgaFrameworkStubs.php` — no implementation needed:

- `$game->gamestate` — state machine (`jumpToState()`, `changeActivePlayer()`, `getCurrentMainStateId()`)
- `$game->notify` — notifications (`all()`, `_getNotifications()`)
- `$game->getActivePlayerId()`, `$game->getCurrentPlayerId()`, `$game->loadPlayersBasicInfos()`
- `$game->_setCurrentPlayerId()`, `$game->_getCurrentPlayerId()`, `$game->_colors`

### Fate implementation (`tests/Harness/GameWrapper.php`)

```php
class GameWrapper extends Game {
    // Constructor: swap MySQL-backed tables with in-memory stubs
    function __construct() {
        parent::__construct();
        $this->machine = new OpMachine(new MachineInMem($this, $this->xtable));
        $this->tokens = new TokensInMem($this);
    }

    // Harness contract
    public function getGameName(): string { return "Fate"; }

    public function saveDbState(): array {
        return [
            "tokens" => $this->tokens->toJson(),
            "machine" => $this->machine->db->toJson(),
        ];
    }

    public function loadDbState(array $db): void {
        $this->tokens->fromJson($db["tokens"] ?? []);
        $this->machine->db->fromJson($db["machine"] ?? []);
    }

    // Widen visibility (if declared protected in game)
    public function getAllDatas(): array { return parent::getAllDatas(); }

    // Game-specific debug functions
    public function debug_setupGame_h1(): void { /* ... */ }
}
```

### Reusing for another game

To use the harness for a different BGA game:

1. **`GameWrapper`** — extend your `Game` class, swap in-memory DB stubs in constructor, implement the 4 contract methods
2. **`play.php`** — bootstrap: require autoloader + stubs + `GameWrapper` + `GameDriver`, call `GameDriver::main(new GameWrapper(), $argv, ...)`
3. **`GameDriver.php`** — use as-is (no edits needed)
4. **State classes** in `modules/php/States/` following `Bga\Games\{name}\States\` namespace
5. **`render.ts`** — adjust CSS filename for your game

### Not yet decided

- Whether to extract `GameDriver` + render into a shared package (e.g. `bga-harness/`) or keep it as copy-paste-and-adapt per game
- How to share `TokensInMem` / `MachineInMem` if multiple games use the same tokens+machine pattern

---

## Status

- [x] One-time setup steps (staging/, .gitignore, package.json play script)
- [x] Extract `GameUT` into `tests/Stubs/GameUT.php` (no PHPUnit dependency)
- [x] Write `play.php` with scenario + debug mode
- [x] Add `getAllDatas()` to `GameUT`; `GameDriver` appends `gamestate` field
- [x] Write `tests/Harness/plays/setup.json`
- [x] Write `tests/Harness/template.html` (BGA HTML skeleton)
- [x] Write `tests/Harness/tsconfig.json`
- [x] Write `tests/Harness/render.ts` with notification replay, game log, click/tooltip registries
- [x] `Notify` stub in `bga-sharedcode` supports recording + decorators — no custom subclass needed
- [x] `format_string_recursive` with `bgaFormatText` delegation for token/place name resolution
- [x] Test Flow 1: initial setup snapshot (`npm run play` → staging/snapshot.html ✓)
- [x] Test Flow 2: action + notification replay (PlayerTurn state + buttons render ✓)
- [ ] (Optional) Add Playwright screenshot step
