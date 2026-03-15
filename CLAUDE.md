# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Board Game Arena (BGA) implementation of the game "Fate: Defenders of Grimheim" (also will be refers in Fate). It uses TypeScript, Scss for client-side code and PHP for server-side game logic.

## Development Commands

### Build 

- `npm run build` - Full build (TypeScript, SCSS, and material generation)
- `npm run build:ts` - Compile TypeScript to .js
- `npm run build:scss` - Compile SCSS to fate.css
- `npm run genmat` - Generate Material.php from CSV files in misc/
- `npm run lint:php`- Check for php syntax errors
- `npm run predeploy` - Generate and test everything (build + PHP lint + PHP tests + JS tests)


### Testing

- `npm run tests` - Run all PHPUnit tests
- `npm run test -- tests/<TestFile>.php` - Run a single test file
- `npm run test -- --filter testMethodName tests/<TestFile>.php` - Run a single test method
- Note: Tests require APP_GAMEMODULE_PATH environment variable pointing to bga-sharedcode repository (but it automatically set if you run via npm)
- `npm run tests:cov` - Run PHPUnit tests with code coverage report (requires Xdebug)
- `npm run jstests` - Run TypeScript unit tests (mocha + chai, test files in src/tests/*.spec.ts)

### Local UI Harness

The harness runs PHP server logic locally and renders a client snapshot for visual inspection — no real BGA server needed.


- `HARNESS_VERBOSE=1 ...` - Show full game console output

**Key files:**
- `tests/Harness/play.php` — PHP runner (scenarios + debug functions)
- `tests/Harness/render.ts` — Node.js renderer (JSDOM, BGA stubs, notification replay)
- `tests/Harness/GameWrapper.php` — Extends `Game` with in-memory stubs and debug setup functions
- `tests/Harness/plays/<name>.json` — Source-controlled scenario scripts

**Typical workflow:**
1. Add a `debug_*` function in `Game.php` that sets up the state to test
2. Run `php8.4 tests/Harness/play.php --debug debug_<name> --scenario tests/Harness/plays/setup.json`
3. Run `ts-node --project tests/Harness/tsconfig.json tests/Harness/render.ts` to generate `staging/snapshot.html`
4. Read `staging/snapshot.html` to inspect layout, tokens, and action buttons
5. Action buttons have `data-action` attributes showing the `action_resolve` payload

### Code Formatting

- Prettier is configured with PHP plugin (see package.json)
- Print width: 140 characters
- Brace style: 1tbs

## Architecture

### Operation-Based State Machine

The game logic is built around an operation-based state machine pattern:

- **OpMachine** ([modules/php/OpCommon/OpMachine.php](modules/php/OpCommon/OpMachine.php)) - Core state machine that manages operation queue and execution
- **Operation** ([modules/php/OpCommon/Operation.php](modules/php/OpCommon/Operation.php)) - Abstract base class for all game operations
- **Operation implementations** (modules/php/Operations/Op\_\*.php) - Concrete operations like Op_cardDraw, Op_placeDie, Op_gain, etc.
- **ComplexOperation** - Operations that can contain sub-operations (delegates)
- **CountableOperation** - Operations that can be repeated a specific number of times

Operations are queued in the database (DbMachine) and executed sequentially, enabling complex game flows with undo/redo support.

### Material System

Game elements are defined in CSV files and auto-generated into PHP code:

- CSV files in misc/ directory define tokens, cards, locations, operations, etc.
- [misc/other/genmat.php](misc/other/genmat.php) - Script that parses CSV files and generates Material.php
- Generated sections in Material.php are marked with `--- gen php begin <name> ---` and `--- gen php end <name> ---`
- Material.php is partially auto-generated - manual sections exist for constants and error codes

**Important**: When adding new game elements, update the corresponding CSV file and run `npm run genmat` rather than editing Material.php directly.

### Token Management

- **DbTokens** ([modules/php/Db/DbTokens.php](modules/php/Db/DbTokens.php)) - Token storage, notifications, counters, and material-based creation
- Tokens represent all physical game pieces (cards, dice, workers, resources, etc.)

### Hex Map

- **HexMap** ([modules/php/Common/HexMap.php](modules/php/Common/HexMap.php)) - All hex grid logic: adjacency, distance, terrain queries, pathfinding, and monster movement helpers
- Accessed via `$this->game->hexMap->` from operations and `$game->hexMap->` from tests
- Key functions: `getAdjacentHexes`, `getReachableHexes`, `getDistanceMapToGrimheim`, `getMonsterNextHex`, `getMonstersOnMap`, `isHeroAdjacentTo`, `getHexesInLocation`, `isOccupied`, `isInGrimheim`


### Game States

State classes in modules/php/States/ handle different game phases:

- GameDispatch - Main game flow dispatcher
- PlayerTurn - Individual player turn handling
- MultiPlayerMaster/MultiPlayerTurnPrivate/MultiPlayerWaitPrivate - Multiplayer coordination
- PlayerTurnConfirm - Turn confirmation state
- MachineHalted - Error/debug state

### Client-Side Structure

TypeScript files in src/ compile to a single Game.js:

- **Game.ts** - Main game class (extends GameMachine), entry point for client logic
- **Game0Basics.ts** - First file in compilation order, basic definitions
- **Game1Tokens.ts** - Token rendering and management
- **GameMachine.ts** - Client-side state machine handling
- **LaAnimations.ts** - Animation utilities


SCSS files in src/css/ compile to fate.css with GameXBody.scss as the entry point.

### File Naming Conventions

- PHP Operation classes: `Op_<operationName>.php` (e.g., Op_cardDraw.php)
- Material CSV files: `<category>_material.csv` (e.g., token_material.csv, card_material.csv)
- PHP namespaces: `Bga\Games\Fate\<Subdirectory>`

## After completing a task

- Check [misc/docs/PLAN.md](misc/docs/PLAN.md) and mark completed items with `[x]`

## Prepare the game for BGA deployment:

#. Run the full build process: `npm run build`
#. Check for any build errors in TypeScript, SCSS, or material generation
#. Run tests: `npm run predeploy`
#. Check and fix failed tests
#. Show git status to see which files have changed
#. Check for spelling mistakes and issues in changed code
#. Check to see if new php tests should be added

## Common Development Patterns

Detailed step-by-step checklists are in separate files — read them when performing these tasks:

- **Adding a new operation** — see [misc/docs/PROCEDURES.md](misc/docs/PROCEDURES.md#adding-a-new-operation). Use the **Operation Template** (`Op_xxx`) in the same file as the starting point for new operation files.
- **Adding a new game element** (token, location, card) — see [misc/docs/PROCEDURES.md](misc/docs/PROCEDURES.md#adding-new-game-element)
- **Adding a new material CSV file** — see [misc/docs/PROCEDURES.md](misc/docs/PROCEDURES.md#adding-new-game-material-element)
- **Validating operation UI in harness** — see [misc/docs/PROCEDURES.md](misc/docs/PROCEDURES.md#validating-operation-ui-in-harness)
- **Graphics assets** (PDF sources, sprite conversion) — see [misc/docs/GRAPHICS.md](misc/docs/GRAPHICS.md)

### Working with Tests

- Tests use an in-memory implementation (MachineInMem, TokensInMem) for fast execution
- Test base classes provide game setup utilities
- APP_GAMEMODULE_PATH must point to bga-sharedcode for BGA framework dependencies
- BGA framework stub files are in `/home/elaskavaia/git/bga-sharedcode/misc/module/table/table.game.php` — tests use these stubs instead of the real framework (which we have no access to). Look here when fixing framework class issues (e.g. `UserException`, `Table`, `Notify`)
- use `npm run tests` to run tests


## BGA-Specific Considerations

- This is not for beginners - assumes familiarity with BGA development
- Deployment uses SFTP (see BGA documentation for VSCode setup)
- \_ide_helper.php provides IDE autocomplete for BGA framework
- Follow BGA framework conventions for notifications, database queries, and state transitions
- Game uses modern BGA framework with namespace support: `Bga\Games\Fate`
