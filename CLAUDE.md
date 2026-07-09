# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Board Game Arena (BGA) implementation of the game "Fate: Defenders of Grimheim" (also referred to as Fate). It uses TypeScript, Scss for client-side code and PHP for server-side game logic.

## Architecture

See [misc/docs/DESIGN.md](misc/docs/DESIGN.md) for architecture overview, token/location naming, and game design details.
**Important** if you are exploration/reaseach agent please read of search this document first.


## Planning

- When planning individual features or fixes, create the plan in `misc/docs/` instead of a temp location.
- After the plan is completed, update relevant documents in `misc/docs/` and delete the plan file.

## Development Commands

### Build 

- `npm run build` - Full build (TypeScript, SCSS, and material generation)
- `npm run build:ts` - Compile TypeScript to .js
- `npm run build:scss` - Compile SCSS to fate.css
- `npm run genmat` - Generate Material.php from CSV files in misc/
- `npm run lint:php`- Check for php syntax errors

- `npm run predeploy` - Generate and test everything (build + PHP lint + PHP tests + JS tests). Slow (several minutes). **Only run it when explicitly asked or right before `git commit`.** Do NOT run as mid-task verification — use targeted commands instead (`npm run build:ts`, `npm run jstests`, single PHP test file). Always redirect output to `/tmp/predeploy_output.txt` so you can re-read it instead of re-running.


### Testing

- `npm run tests` - Run all PHPUnit tests (full suite)
- **Important**: When running full test suite, redirect output to a temp file (`npm run tests > /tmp/test_output.txt 2>&1`) and read from it instead of re-running multiple times — tests are slow!

- `npm run test -- tests/<TestFile>.php` - Run a single test file
- `npm run test -- --filter testMethodName tests/<TestFile>.php` - Run a single test method
- Note: Tests require APP_GAMEMODULE_PATH environment variable pointing to bga-sharedcode repository (but it automatically set if you run via npm)
- `npm run tests:cov` - Run PHPUnit tests with code coverage report (requires Xdebug)
- `npm run jstests` - Run TypeScript unit tests (mocha + chai, test files in src/tests/*.spec.ts)
- `npm run play` - Run simulated game to generate web page (html), see [misc/docs/HARNESS.md](misc/docs/HARNESS.md) for usage details.


### Debugging PHP tests (phpdbg)

Requires `php8.4-phpdbg` (`sudo apt install php8.4-phpdbg`).

```bash
APP_GAMEMODULE_PATH=~/git/bga-sharedcode/misc/ phpdbg8.4 -e -qrr ~/php-composer/vendor/bin/phpunit --bootstrap ./tests/_autoload.php --filter testMethodName tests/TestFile.php
```

Key commands: `b file.php:line` (breakpoint), `r` (run), `c` (continue), `s` (step into), `n` (step over), `p $var` (print), `ev expr` (evaluate), `bt` (backtrace), `q` (quit).

### Local UI Harness

The harness runs PHP server logic locally and renders a client snapshot (`staging/snapshot.html`) for visual inspection — no real BGA server needed. See [misc/docs/HARNESS.md](misc/docs/HARNESS.md) for usage details.

### Debugging on real BGA Studio (chrome-devtools-mcp)

When the chrome-devtools-mcp plugin is available, you can drive a real browser against a live BGA Studio game (screenshots, DOM snapshots, console logs, network, clicks):

- Open a table: `https://studio.boardgamearena.com/tableview?table=<TABLE_ID>` (ask the user for the current table id)
- Act as a specific test player: append `&as=<PLAYER_ID>` (e.g. `...&as=2300663`)
- Login session persists in the MCP browser profile (`~/.cache/chrome-devtools-mcp/`); if redirected to the login page, ask the user to log in in the MCP-controlled Chrome window, then retry
- **The game runs inside `iframe#gameIframe`** on the tableview page. `evaluate_script` executes against the top document, so `document.getElementById(...)` will NOT find game DOM. Reach it via `document.getElementById('gameIframe').contentDocument` (and `.contentWindow` for `getComputedStyle` / `localStorage`). The iframe is same-origin, so this works.

### Code Formatting

- Prettier is configured with PHP plugin (see package.json)
- Print width: 140 characters
- Brace style: 1tbs


### Material System

Game elements (tokens, cards, operations) are defined in CSV files in `misc/` and auto-generated into `Material.php`. Generated sections are marked with `--- gen php begin <name> ---` / `--- gen php end <name> ---`. When adding new game elements, update the CSV and run `npm run genmat` — do not edit generated sections directly.

When editing a CSV file prefer the `awk` tool — otherwise it's easy to misplace columns.

## After completing a task

- Check [misc/docs/PLAN.md](misc/docs/PLAN.md) and mark completed items with `[x]`

## Common Development Patterns

Detailed step-by-step checklists are in separate files — read them when performing these tasks:

- **Adding a new operation** — see [misc/docs/PROCEDURES.md](misc/docs/PROCEDURES.md#adding-a-new-operation). Use the **Operation Template** (`Op_xxx`) in the same file as the starting point for new operation files.
- **Adding a new game element** (token, location, card) — see [misc/docs/PROCEDURES.md](misc/docs/PROCEDURES.md#adding-new-game-element)
- **Adding a new material CSV file** — see [misc/docs/PROCEDURES.md](misc/docs/PROCEDURES.md#adding-new-game-material-element)
- **Validating operation UI in harness** — see [misc/docs/PROCEDURES.md](misc/docs/PROCEDURES.md#validating-operation-ui-in-harness)
- **Graphics assets** (PDF sources, sprite conversion) — see [misc/docs/GRAPHICS.md](misc/docs/GRAPHICS.md)

### Working with Tests

- Tests are organized in subdirectories mirroring source: `tests/Operations/`, `tests/OpCommon/`, `tests/Model/`, `tests/Common/`, `tests/Game/`, `tests/Campaign/`
- Tests use an in-memory implementation (MachineInMem, TokensInMem) for fast execution
- BGA framework stub files are in `/home/elaskavaia/git/bga-sharedcode/misc/module/table/table.game.php` — tests use these stubs instead of the real framework (which we have no access to). Look here when fixing framework class issues (e.g. `UserException`, `Table`, `Notify`)

## BGA-Specific Considerations

- `_ide_helper.php` provides IDE autocomplete for BGA PHP framework
- `src/types.d.ts` and `src/types/bga-animations.d.ts` provide BGA TypeScript type stubs
- Follow BGA framework conventions for notifications, database queries, and state transitions
- Game uses modern BGA framework with namespace support: `Bga\Games\Fate`


## Other games for reference if needed 

- `../bga-wayfares/`
- `../bga-skarabrae/`
- `../bga-mars/`
- `~/git/bga-sharedcode/misc/` - test stubs
