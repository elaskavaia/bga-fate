# Development Procedures

Step-by-step checklists for common development tasks. Referenced from CLAUDE.md.

## Adding a New Operation

Prompt: add <name> operation. Read PROCEDURES.md for instructions

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
#. Add a `debug_Op_<name>` function in `GameHarness.php` (not `Game.php`) that sets up the game state and pushes the operation to the machine, then run the harness to validate the UI:
   ```bash
   npm run play --debug=debug_Op_<name> --scenario=setup
   # Then read staging/snapshot.html to verify layout, buttons, and tooltips
   ```
#. If new game elements are introduced, follow the "Adding New Game Element" checklist below

## Adding New Game Element

Prompt: add <name> location. Read PROCEDURES.md for instructions.
Prompt: add <name> game element. Read PROCEDURES.md for instructions.

Every physical game piece leaves footprints in multiple places: database, material, CSS, and client-side code. Follow this checklist when adding a new element.

**1. Design (DESIGN.md)**
   - Check if DESIGN.md already describes this element; if not, add an entry documenting:
     - Token key pattern using reverse DNS notation: `supertype_type_instance` (e.g. `monster_goblin_5`, `card_hero_1`)
     - Whether it is player-specific (keyed by color) or global
     - Possible locations of these tokens (e.g. `supply`, `hex_X_Y`, `hand_<color>`, `tableau_<color>`)
     - Possible states and what they mean
     - Whether it exists in the DB tokens table as a key, or only as a location

**2. Material (CSV + genmat)**
   - Add or update the element type/supertype in the appropriate CSV file in misc/ (e.g. `token_material.csv`, `card_material.csv`)
   - You can define types and supertypes and not individual instances in material (in this case instances are created during setup, for example if we need 50 crystals - they all the same, we only need to define crystal as supertype)
   - Include translatable fields (name, tooltip) where needed
   - If a game element is location it usually goes to `location_material.csv`
   - Run `npm run genmat` to regenerate Material.php

**3. Setup (Game.php)**
   - Tokens are auto-created by `DbTokens::createAllTokens()` based on the `create` field in the CSV:
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

**7. Validate in harness**
    - **Important**: Always do this step proactively — create the debug function and run the harness, don't ask whether the user wants it.
    - Create or update a `debug_` function in `GameHarness.php` (not `Game.php`) to place/move the relevant elements into a testable state
    - Run the harness and read `staging/snapshot.html` to inspect layout, token placement, and CSS:
      ```bash
      npm run play --debug=debug_<name> --scenario=setup
      # Then read staging/snapshot.html
      ```
    - Check: tokens appear in correct locations, action buttons render with correct labels
    - Check tooltips: `snapshot.html` includes a `#harness-tooltip-registry` section at the bottom listing all registered tooltips. Read `staging/snapshot.html` and search for the token ID or tooltip text to verify the expected content is present. Each entry shows the node ID and the rendered tooltip HTML.
    - If the operation requires player input, the buttons in `#generalactions` should appear with `data-action` attributes showing the correct `action_resolve` payload

## Validating Operation UI in Harness

After implementing an operation, run the harness and inspect `staging/snapshot.html` to confirm the client-side UI is correct. This replaces manual browser testing.

### Setup

1. Add a `debug_Op_<name>` function in `GameHarness.php` that:
   - Calls `debug_setupGame_h1()` (or assumes `setup` scenario state)
   - Pushes the operation onto the machine for the active player
   - Example:
     ```php
     public function debug_Op_move(): void {
         $playerId = 10;
         $this->machine->instanciateOperation("move", $playerId, []);
     }
     ```
2. Run the harness:
   ```bash
   npm run play --debug=debug_Op_<name> --scenario=setup
   ```
3. Read `staging/snapshot.html`

### What to check

**Action buttons (`#generalactions`)**
- Buttons should appear for each valid target returned by `getPossibleMoves()`
- Each button must have a `data-action` attribute — search for `data-action` in the HTML
- The payload should be `{"endpoint":"action_resolve","data":{"target":"<targetId>"}}`
- If no buttons appear, check `getPrompt()` returns non-empty and `getPossibleMoves()` returns valid targets

**Highlighted / clickable tokens**
- The `#harness-click-registry` section lists all elements with click handlers (`_lis` attribute) and all `data-action` elements
- Verify the expected tokens (e.g. hex tiles, cards) appear in this list
- The "action" column shows either `onToken` (generic handler) or the full `action_resolve` payload

**Tooltips**
- The `#harness-tooltip-registry` section lists all registered tooltips
- Search for the token ID or expected tooltip text to confirm it was registered
- Each card shows the node ID and the rendered tooltip HTML

**Title bar**
- The `#pagemaintitletext` element should contain the prompt from `getPrompt()`
- Search for the prompt text in the HTML to confirm it appears

### Common problems

| Symptom | Likely cause |
|---|---|
| No buttons in `#generalactions` | `getPossibleMoves()` returns empty or all errors, or `getPrompt()` returns empty |
| Button missing `data-action` | Button id doesn't start with `button_` — check `getUiArgs()` target key |
| Token not in click registry | `getPlaceRedirect()` not setting `result.onClick` for this token type/location |
| Tooltip missing | `updateTokenDisplayInfo()` not setting `tokenInfo.tooltip` or `tokenInfo.name` for this token |
| Wrong state shown | Machine halted — check `staging/plays/debug/notifications.json` for error messages |

## Adding New Game Material Element

Prompt: add new material file <name>. Read PROCEDURES.md for instructions.

To add new file:
1. Add file <name>_material.csv in misc/ with pipe (|) separated header
2. Add comments in Material.php  `--- gen php begin <name>_material ---` and `--- gen php end <name>_material ---`  before `/* --- GEN PLACEHOLDR --- */`
3. Run `npm run genmat` to regenerate Material.php

To update:
1. Update the appropriate CSV file in misc/ (tokens_material.csv, card_material.csv, etc.)
2. Run `npm run genmat` to regenerate Material.php
3. Material generation uses pipe (|) as field separator
4. Special directives in CSV: `#set _tr=field` (mark field as translatable), `#set _noquotes=field` (no quotes in output)
