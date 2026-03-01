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
- `npm run predeploy` - Generate and test everything


### Testing

- `npm run tests` - Run all PHPUnit tests
- `npm run test -- modules/php/Tests/<TestFile>.php` - Run a single test file
- `npm run test -- --filter testMethodName modules/php/Tests/<TestFile>.php` - Run a single test method
- Note: Tests require APP_GAMEMODULE_PATH environment variable pointing to bga-sharedcode repository (but it automatically set if you run via npm)

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

- **DbTokens** ([modules/php/Db/DbTokens.php](modules/php/Db/DbTokens.php)) - Database layer for token storage
- **PGameTokens** ([modules/php/Common/PGameTokens.php](modules/php/Common/PGameTokens.php)) - Game-specific token logic wrapper
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

## Prepare the game for BGA deployment:

#. Run the full build process: `npm run build`
#. Check for any build errors in TypeScript, SCSS, or material generation
#. Run tests: `npm run predeploy`
#. Check and fix failed tests
#. Show git status to see which files have changed
#. Check for spelling mistakes and issues in changed code
#. Check to see if new php tests should be added

## Common Development Patterns

### Adding a New Operation

Prompt: add <name> operation. Read CLAUDE.md for instructions

#. Create `modules/php/Operations/Op_<name>.php` with a **minimal empty template**
   - Extend `Operation` (default choice)
   - Only add `resolve()` as an empty stub
   - Add a comment block at the top with relevant rules from RULES.md
#. Add operation definition to `misc/op_material.csv`
#. Run `npm run genmat` to update Material.php
#. Run `npm run tests` — **verify everything passes before continuing**
#. **Present design to user for approval** — before implementing logic, outline:
   - Which rules apply (quote from RULES.md)
   - Operation type (automated / user-facing / countable)
   - What `getPossibleMoves()` will return (valid targets, error cases)
   - What `resolve()` will do (token moves, state changes, sub-operations)
   - Any new game elements or helpers needed
#. Determine operation type:
   - **Automated** — no user choice (e.g. reinforcement, turn end)
   - **User-facing** — player selects a target (e.g. move, attack)
   - **Countable** — repeatable N times (e.g. gain X gold) → switch base class to `CountableOperation`
#. For **user-facing** operations, implement:
   - `getPrompt()` — prompt text shown to the player (use `clienttranslate()`)
   - `getPossibleMoves()` — valid targets as assoc array
     - Keys are token IDs so the client can highlight them for clicking
     - Valid: `["q" => Material::RET_OK]`, invalid: `["q" => ERR_CODE]`
   - `canSkip()` — return `true` if action is optional
#. Ask user to code review before moving to next step
#. Implement `resolve()` — executes the game logic
   - Use `$this->getCheckedArg()` to get the validated user selection
   - Use `$this->dbSetTokenLocation()` to move tokens with notifications
   - Use `$this->queue()` to chain sub-operations
   - For multi-step operations, the operation may re-queue itself with extra data
   - For **automated** operations, `resolve()` may be the only required method
#. Ask user what do for ui choices
   - `getUiArgs()` — optional UI hints (e.g. `["buttons" => false]` for map-only selection)
   - `getExtraArgs()` — optional extra data for client (e.g. `["token_div" => $id]`)
#. Ask user to code review before moving to next step
#. Add tests in `modules/php/Tests/`
   - Instantiate via `$this->game->machine->instanciateOperation($type, $owner, $data)`
   - Test `getPossibleMoves()` returns expected valid/invalid targets
   - Test `resolve()` produces correct side effects (token moves, state changes)
#. If new game elements are introduced, follow the "Adding New Game Element" checklist below

### Adding New Game Element

Prompt: add <name> location. Read CLAUDE.md for instructions.
Prompt: add <name> game element. Read CLAUDE.md for instructions.

Every physical game piece leaves footprints in multiple places: database, material, CSS, and client-side code. Follow this checklist when adding a new element.

**1. Design (DESIGN.md)**
   - Check if DESIGN.md already describes this element; if not, add an entry documenting:
     - Token key pattern using reverse DNS notation: `supertype_type_instance` (e.g. `monster_goblin_5`, `card_hero_1`)
     - Whether it is player-specific (keyed by color) or global
     - Possible locations of these tokens (e.g. `supply`, `hex_X_Y`, `hand_<color>`, `tableau_<color>`)
     - Possible states and what they mean
     - Whether it exists in the DB tokens table as a key, only as a location

**2. Material (CSV + genmat)**
   - Add or update the element type/supertype in the appropriate CSV file in misc/ (e.g. `token_material.csv`, `card_material.csv`)
   - You can define types and supertypes and not individual instances in material (in this case instances are created during setup, for example if we need 50 crystals - they all the same, we only need to define crystal as supertype)
   - Include translatable fields (name, tooltip) where needed
   - If a game element is location it usually goes to `location_material.csv`
   - Run `npm run genmat` to regenerate Material.php

**3. Setup (Game.php)**
   - Tokens are auto-created by `PGameTokens::createTokens()` based on the `create` field in the CSV:
     - `0` = do not create, `1` = single token with id as-is, `2` = indexed (`{id}_{INDEX}`), `3` = per-player indexed (`{id}_{COLOR}_{INDEX}`), `4` = per-player single (`{id}_{COLOR}`), `5` = indexed per-player (`{id}_{INDEX}_{COLOR}`)
   - The `location` column sets the initial location; `{COLOR}` placeholders are expanded per player
   - Only add manual setup code in `setupNewGame()` if auto-creation is insufficient (e.g. conditional placement, shuffling into decks)

**4. Graphics assets**
   - Check if sprite images exist in img/ for this element
   - If not, ask the user to provide graphics assets before proceeding with CSS

**5. CSS/SCSS (src/css/)**
   - SCSS files are organized by element category: `Cards.scss` for cards, `Tokens.scss` for tokens/meeples/crystals, `Minis.scss` for hero miniatures, `Map.scss` for map/hex styles. Entry point is `GameXBody.scss` which imports all others via `@use`
   - Supertype class sets shared properties (background-image, dimensions): `.meeple { background-image: url(img/tokens.png); width: 25px; height: 25px; }`
   - Type class sets sprite position: `.meeple_ff0000 { background-position: 14% 0%; }`
   - Run `npm run build:scss` to verify

**6. Client-side (src/Game.ts and related)**
   - DOM elements use id matching the token key and classes matching supertype/type: `<div id="meeple_ff0000_7" class="meeple meeple_ff0000"></div>`
   - Override `getPlaceRedirect(tokenInfo, args)` in Game.ts to handle:
     - Location redirects: map server location to a client container by setting `result.location` (e.g. server `tableau_ff0000` → client `breakroom_ff0000`)
     - Click handlers: set `result.onClick = (x) => this.onToken(x)` for interactive elements
     - Use `result.nop = true` to suppress animation for non-visual tokens
   - Override `updateTokenDisplayInfo(tokenDisplayInfo)` in Game.ts to:
     - Build dynamic tooltips per token type (switch on `tokenInfo.mainType`)
     - Set `tokenInfo.showtooltip = false` to hide tooltips for layout-only elements
     - Enrich `tokenInfo.imageTypes` with extra CSS classes
   - Check all locations token can be in. If a new location container is needed, create it in `setup(gamedatas: CustomGamedatas)()` . Dynamic containers can also be created on-demand in `getPlaceRedirect` using `placeHtml()`

**7. Ask user to validate in browser**
    - **Important**: Always do this step proactively — create the debug function and tell the user to run it, don't ask whether they want it.
    - Create or update php function with debug_ prefix to create some of the elements (or move around)
    - Ask user to validate how it looks and check tooltips


### Graphics Assets

Original game assets (PDFs) are in `~/Develop/bga/bga-assets/fate/Cards/`:
- Per-hero PDFs: `EN_<Hero>_Fronts.pdf`, `EN_<Hero>_Backs.pdf` (40 pages each)
- Shared equipment: `EN_Equipment_Fronts.pdf` (promo pack, 7 pages including cover)
- Monster cards: `EN_Threats_Fronts.pdf`, `EN_Threats_Backs.pdf`
- Text extractions: `.txt` files alongside each PDF

Converted JPG sprites are in `~/Develop/bga/bga-assets/fate/Cards/JPG/`.
The `monim.sh` script in that directory converts PDFs to 6-column sprite sheets using ImageMagick montage.

**Hero card sprite conversion**: Each hero has a `reorder_<hero>.sh` script that:
1. Extracts individual cards from the Fronts, Backs, and Equipment sprites
2. Reorders them by `num` field from `card_material.csv` (1-34)
3. Adds 2 generic backs (equipment + event) as positions 35-36
4. Assembles into a 6x6 sprite: `<hero>_hero_cards.jpg` (288x393 per card)

The mapping in each script maps PDF sprite positions to CSV `num` values:
- Fronts sprite: hero Level I, starting ability/equip, then remaining abilities, equipment, events (with duplicates for count>1)
- Backs sprite: hero Level II + ability Level IIs (first 8 positions), then generic equip/event backs
- Equipment sprite: promo pack cards (position varies per hero)

Output sprites are copied to `img/` in this repo for use by CSS.

### Adding New Game Material Element

Prompt: add new material file <name>. Read CLAUDE.md for instructions.

To add new file:
1. Add file <name>_material.csv in misc/ with pipe (|) separated header
2. Add comments in Material.php  `--- gen php begin <name>_material ---` and `--- gen php end <name>_material ---`  before `/* --- GEN PLACEHOLDR --- */`
3. Run `npm run genmat` to regenerate Material.php

To update:
1. Update the appropriate CSV file in misc/ (tokens_material.csv, card_material.csv, etc.)
2. Run `npm run genmat` to regenerate Material.php
3. Material generation uses pipe (|) as field separator
4. Special directives in CSV: `#set _tr=field` (mark field as translatable), `#set _noquotes=field` (no quotes in output)

### Working with Tests

- Tests use an in-memory implementation (MachineInMem, TokensInMem) for fast execution
- Test base classes provide game setup utilities
- APP_GAMEMODULE_PATH must point to bga-sharedcode for BGA framework dependencies
- use `npm run tests` to run tests


## BGA-Specific Considerations

- This is not for beginners - assumes familiarity with BGA development
- Deployment uses SFTP (see BGA documentation for VSCode setup)
- \_ide_helper.php provides IDE autocomplete for BGA framework
- Follow BGA framework conventions for notifications, database queries, and state transitions
- Game uses modern BGA framework with namespace support: `Bga\Games\Fate`
