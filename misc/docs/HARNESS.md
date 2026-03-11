## Premise

Client-Server feedback loop: the game is client-server. We have PHP server logic and TypeScript client code. The real BGA server cannot be accessed locally (authorization/cookie issues). Goal: run server logic locally, produce realistic game state, render it in a simulated browser environment so Claude can inspect the result.

## Constraints

- No access to real BGA server or its framework source (only stubs)
- Cannot capture/replay real browser sessions (cookies fail)
- PHP test infrastructure (`GameUT`, `TokensInMem`, `MachineInMem`) already exists and works
- JS test infrastructure (JSDOM + stubs in `src/tests/setup.ts`) already exists and works
- `parent::getAllDatas()` is **not** in the BGA stub yet — add it to `GameUT` or the stub so it assembles gamedatas from test infra

## Client-Server Interaction Points

There are exactly two directions and three message types:

### Client → Server
1. **Action** — player does something (e.g. move hero). Client calls `bga.actions.performAction("action_resolve", args)`. Server runs the operation, mutates state, sends notifications back.

### Server → Client
1. **Reload (getAllDatas)** — triggered on page load or F5. Server returns full game state as `gamedatas`. Client calls `game.setup(gamedatas)` which builds the entire DOM from scratch.
2. **Notifications** — sent by server during action processing (token moves, counter updates, messages). Client receives them sequentially and calls the matching `notif_xxx(args)` method (e.g. `notif_tokenMoved`, `notif_counter`).

Action reply itself is just success/error — the actual state change is always communicated via notifications.

## Flows to Support in Local Test Harness

### Flow 1: Reload
```
PHP: setupGameTables() + optional actions → assemble gamedatas → gamedatas.json
JS:  game.setup(gamedatas) → snapshot.html
```
This is the "page load" view. Shows initial board or any saved state.

### Flow 2: Action + Notifications
```
PHP: game is in some state → call action (e.g. action_resolve with target)
     → capture notifications emitted during that call → notifications.json
JS:  (after setup) call game.notif_tokenMoved(args), game.notif_counter(args), etc.
     in sequence → snapshot.html
```
This lets us test: "after player moves hero, does the token appear on the right hex?"

### Flow 3: Full Round-Trip
```
PHP: setup → action → capture notifications + new gamedatas
JS:  setup → replay notifications → snapshot
```
Simulates a complete player turn cycle end-to-end.

## Architecture

```
┌──────────────────────────────────┐
│  PHP CLI script                  │
│  misc/harness/play.php│
│  Uses GameUT + test infra        │
│  Runs a scenario (setup + moves) │
│  Captures notifications via      │
│  RecordingNotify wrapper         │
│  Outputs:                        │
│    gamedatas.json                │
│    notifications.json            │
└──────────────┬───────────────────┘
               │ JSON files
┌──────────────▼───────────────────┐
│  Node.js CLI script              │
│  misc/harness/render.ts          │
│  JSDOM + setup.ts stubs          │
│  game.setup(gamedatas)           │
│  await game.notif_xxx(args) ...  │  ← replay notifications in order
│  Inlines fate.css into HTML      │
│  Writes: snapshot.html           │
└──────────────────────────────────┘
```

Run both with one npm script:
```
npm run play            # runs default 'setup' play
npm run play -- hero_move  # runs a specific play
```
Then open `staging/snapshot.html` in browser (has compiled CSS inlined), or Claude reads the raw HTML structure.

## npm script

In `package.json`:
```json
"play": "php8.4 misc/harness/play.php $npm_config_scenario && ts-node --project misc/harness/tsconfig.json misc/harness/render.ts"
```

## Part 1: PHP dumper — `misc/harness/play.php`

Bootstraps same autoloader as PHPUnit tests (`modules/php/Tests/_autoload.php`), then:

1. Accept scenario name as CLI arg (e.g. `php play.php hero_move`); defaults to `setup` if omitted
2. Read `staging/plays/<name>/script.js` to get `current_player_id` and `steps`
3. Load `staging/plays/<name>/db.json` into `GameUT` if present (`tokens->keyindex`, `machine->xtable`, `gamestate`, `players`, `curid`)
4. Replace `$game->notify` with a `RecordingNotify` that stores all calls
5. Run `reload` (calls `$game->getAllDatas()`) — implemented in `GameUT` ([modules/php/Tests/GameUT.php](modules/php/Tests/GameUT.php))
6. For each step, dispatch to the matching PHP method via reflection, passing `data` as named params; collect notifications
7. Write `staging/gamedatas.json` and `staging/notifications.json`
8. Write final db state to `staging/plays/<name>/db.json`

Example scripts to copy into `staging/plays/<name>/` are in `misc/harness/plays/` (source-controlled).

## Part 2: Node.js renderer — `misc/harness/render.ts`

Needs `misc/harness/tsconfig.json` extending the test tsconfig:
```json
{
  "extends": "../../src/tests/tsconfig.json",
  "include": ["./**/*.ts", "../../src/**/*.ts", "../../src/types/**/*.d.ts"]
}
```

1. Read `staging/gamedatas.json` and `staging/notifications.json`
2. Load `misc/harness/template.html` as the JSDOM base document — this is the BGA-provided HTML skeleton that exists before `setup()` runs (editable, committed to source control)
3. Set up JSDOM globals and BGA framework stubs (`$`, `_`, `gameui`, `ebg`, etc.) — extract shared stubs from `src/tests/setup.ts` into `src/tests/shared-stubs.ts` so both `setup.ts` and `render.ts` can import them
4. Instantiate `Game` with mock `Bga` (same as `Game.spec.ts`)
5. Call `game.setup(gamedatas)` — builds initial DOM (Flow 1)
6. For each notification in order, call `await (game as any)['notif_' + n.name](n.args)` — replays state changes (Flow 2)
7. Read `fate.css`, inject as `<style>` into `<head>`
8. Write `document.documentElement.outerHTML` → `staging/snapshot.html`

## DB state format

The harness needs reproducible initial state. Rather than re-running setup (which involves random shuffling), state is serialized to `staging/plays/<name>/db.json` and loaded before each run.

State captures everything in the in-memory "db":

```json
{
  "tokens": [
    { "key": "hero_1", "location": "hex_5_5", "state": 0 },
    { "key": "card_hero_1_1", "location": "tableau_6cd0f6", "state": 0 }
  ],
  "machine": [
    { "id": 1, "rank": 1, "type": "actionMove", "owner": "6cd0f6", "pool": "main", "data": null }
  ],
  "gamestate": {
    "state_id": 10,
    "active_player": 10
  },
  "players": [
    { "player_id": 10, "player_no": 1, "player_color": "6cd0f6", "player_name": "player1", "player_zombie": 0, "player_eliminated": 0 },
    { "player_id": 11, "player_no": 2, "player_color": "982fff", "player_name": "player2", "player_zombie": 0, "player_eliminated": 0 }
  ]
}
```

`db.json` is optional — if absent, the harness starts with a fresh `GameUT` (empty state). If present, it is loaded as the starting point. It is always written at the end of a run to `staging/plays/<name>/db.json` (gitignored), so it can be used as the starting point for subsequent runs or copied to seed another play.

To generate an initial `db.json`: run the `setup` play (which calls `debug_setupGameTables`). The output lands in `staging/plays/setup/db.json`. To start a new play from that state, copy it to `staging/plays/<new_play>/db.json`.

## Scenario format

A scenario is a JS file (`staging/plays/<name>/script.js`) — JSON with a `.js` extension for editor syntax highlighting. It contains metadata plus a sequence of requests mirroring real client calls. The harness loads `db.json` from the same directory if present, then runs `reload`, then executes the steps in order:

```json
{
  "current_player_id": 10,
  "steps": [
    { "endpoint": "action_resolve", "data": {"target": "actionMove"} },
    { "endpoint": "action_resolve", "data": {"target": "hex_5_5"} },
    { "endpoint": "debug_reinforcement", "data": {"cardId": "card_monster_2"} }
  ]
}
```

`current_player_id` identifies who is making the requests (the "logged in" player for this run).

`endpoint` is required. All other fields (`data`, `noerrortracking`, etc.) are optional GET/POST parameters passed as-is to the PHP handler.

### Available endpoints

**Actions** (state-dependent, mirror real player interactions):
- `action_resolve` — submit chosen target (data: `{"target": "..."}`)
- `action_skip` — skip current operation
- `action_undo` — undo last move (data: `{"move_id": N}`)
- `action_whatever` — execute random valid action (for testing/zombie mode)

**Debug** (available in BGA studio mode):
- `debug_xxx` — any `debug_*` method on `Game.php` or its parent; `data` keys map to PHP function parameter names via reflection


## Scenario variations

All play files live in `staging/plays/<name>/` (gitignored):
- `script.js` — the scenario to run (copy from `misc/harness/plays/` examples)
- `db.json` — saved game state (written after each run, optional to seed from)

Example scripts in `misc/harness/plays/` (source-controlled, copy to staging to use):
- `setup.js` — calls `debug_setupGameTables`; no db needed; produces `staging/plays/setup/db.json`
- For `hero_move`: copy `staging/plays/setup/db.json` → `staging/plays/hero_move/db.json`, write `script.js` with `action_resolve` steps

## Future: Screenshots via Playwright

If HTML inspection is not enough, add Playwright to take PNG screenshots:
- Install: `npm install --save-dev playwright`
- After writing `snapshot.html`, load it in headless Chromium
- Take screenshot → `staging/snapshot.png`
- Claude can read PNG via the `Read` tool (multimodal)

This is optional — the HTML snapshot covers most structural debugging needs.

## Setup Steps (manual)

Before implementing, these one-time setup steps are needed:

1. Create directory `staging/`
2. Add generated output files to `.gitignore`:
   ```
   staging/
   ```
3. Add `play` script to `package.json`

## Status / TODO

- [x] One-time setup steps (staging/, .gitignore, package.json play script)
- [x] Extract `GameUT` into `modules/php/Tests/GameUT.php` (no PHPUnit dependency)
- [x] Write `play.php` with `RecordingNotify`
- [x] Add `getAllDatas()` to `GameUT`
- [x] Write `misc/harness/plays/setup/script.json`
- [x] Write `misc/harness/template.html` (BGA HTML skeleton)
- [x] Write `misc/harness/tsconfig.json`
- [x] Write `misc/harness/render.ts` with notification replay
- [x] Test Flow 1: initial setup snapshot (`npm run play` → staging/snapshot.html ✓)
- [x] Test Flow 2: action + notification replay (PlayerTurn state + buttons render ✓)
- [ ] (Optional) Add Playwright screenshot step
