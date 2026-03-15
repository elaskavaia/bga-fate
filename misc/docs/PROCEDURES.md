# Development Procedures

Step-by-step checklists for common development tasks. Referenced from CLAUDE.md.

## Adding a New Operation

Prompt: create <name> operation. Read PROCEDURES.md for instructions. Create plan first with all items from this list. Follow this plan exactly.

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
#. For **user-facing** operations, start from the Operation Template below (copy `Op_xxx` and rename). Implement:
   - `getPossibleMoves()` — valid targets as assoc array
     - Keys are token IDs so the client can highlight them for clicking
     - Valid: `["q" => Material::RET_OK]`, invalid: `["q" => ERR_CODE]`
   - `canSkip()` — return `true` if action is optional
   - `getPrompt()` — prompt text shown to the player (use `clienttranslate()`)
#. Implement `resolve()` — executes the game logic
   - Use `$this->getCheckedArg()` to get the validated user selection
   - Use `$this->dbSetTokenLocation()` to move tokens with notifications
   - Use `$this->queue()` to chain sub-operations
   - For multi-step operations, the operation may re-queue itself with extra data
   - For **automated** operations, `resolve()` may be the only required method
#. If the operation needs custom client-side behavior, override:
   - `getUiArgs()` — UI hints (e.g. `["buttons" => false]` when player clicks map/tokens instead of buttons)
   - `getExtraArgs()` — extra data sent to client (e.g. `["token_div" => $id]`)
#. Ask user to code review before moving to next step
#. Add tests in `tests/`
   - Instantiate via `$this->game->machine->instanciateOperation($type, $owner, $data)`
   - Test `getPossibleMoves()` returns expected valid/invalid targets
   - Test `resolve()` produces correct side effects (token moves, state changes)
#. Add a `debug_Op_<name>` function in `Game.php` that sets up the game state and pushes the operation to the machine, then run the harness to validate the UI:
   ```bash
   php8.4 tests/Harness/play.php --debug debug_Op_<name> --scenario tests/Harness/plays/setup.json
   # Then run the renderer and read staging/snapshot.html to verify layout, buttons, and tooltips
   ts-node --project tests/Harness/tsconfig.json tests/Harness/render.ts
   ```
#. If new game elements are introduced, follow the "Adding New Game Element" checklist below
#. Update DESIGN.md if needed
#. Update PLAN.md if needed
#. If you learned anything new update CLAUDE.md

## Operation Template

When creating a new user-facing operation, use this as the starting template for `modules/php/Operations/Op_<name>.php`:

```php
<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * One-line summary of what this operation does.
 *
 * Params:
 * - param(0): description of first param, if any (e.g. "max", "self", a location name)
 *
 * Data Fields:
 * - card - seeded value of context
 *
 * Behaviour:
 * - Normal case: describe what getPossibleMoves() offers and what resolve() does
 * - Edge case / precondition failure: describe error return and whether it auto-skips
 *
 * Used by: CardName (r-column-expression).
 */
class Op_xxx extends Operation {

    // Params are baked into the operation type string at queue time, e.g. "drawEvent(max)" or "heal(self)".
    // They are static for the lifetime of the operation.
    // private function getLocation(): string {
    //     return $this->getParam(0, "default"); // getParam(index, default)
    // }

    // Data fields are values seeded by the caller at queue time, e.g. queue("xxx", null, ["card" => $cardId]).
    // private function getCard(): ?string {
    //     return $this->getDataField("card");
    // }


    // If operation is fully automated prompt is not needed
    function getPrompt() {
        return clienttranslate("Prompt shown to the player");
    }

    function getPossibleMoves() {
        // Precondition failure — return error, op will auto-skip if canSkip()
        // return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("...")];

        // Token targets: keys are token IDs, client highlights them for clicking
        // return array_keys($data);

        // One explicit confirm target (no map/card selection needed)
        return parent::getPossibleMoves();

        // More elaborate reply when individual error codes
        // $targets = [];
        // foreach ($cards as $cardId => $card) {
        //     $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
        //     $targets[$cardId] = ["q" => $damage > 0 ? Material::RET_OK : Material::ERR_NOT_APPLICABLE];
        // }
        // return $targets;
    }

    function resolve(): void {
        $target = $this->getCheckedArg(); // validated selection from getPossibleMoves()
        $hero = $this->game->getHero($this->getOwner());

        // Move tokens
        // $this->game->tokens->dbSetTokenLocation($tokenId, $location, $state, clienttranslate('...'), [...]);

        // Queue follow-up operations
        // $this->queue("otherOp");
    }

    // function canSkip() {
    //     return true; // normally operation cannot be skipped
    // }

    // function getSkipName() {
    //     return clienttranslate("End Turn"); // only needed if button is not "Skip"
    // }

    // function getUiArgs() {
    //     return ["buttons" => false]; // suppress action buttons; player clicks map/tokens directly
    // }

    // function getExtraArgs() {
    //     return ["token_div" => $someId]; // extra data sent to client with getArgs()
    // }

    // function requireConfirmation() {
    //     return true; // if cannot be auto-skipped and auto-executed, for example dangerous operation or that cannot be undone
    // }


}
```

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
    - Create or update a `debug_` function in `Game.php` to place/move the relevant elements into a testable state
    - Run the harness and read `staging/snapshot.html` to inspect layout, token placement, and CSS:
      ```bash
      php8.4 tests/Harness/play.php --debug debug_<name> --scenario tests/Harness/plays/setup.json
      ts-node --project tests/Harness/tsconfig.json tests/Harness/render.ts
      # Then read staging/snapshot.html
      ```
    - Check: tokens appear in correct locations, action buttons render with correct labels
    - Check tooltips: `snapshot.html` includes a `#harness-tooltip-registry` section at the bottom listing all registered tooltips. Read `staging/snapshot.html` and search for the token ID or tooltip text to verify the expected content is present. Each entry shows the node ID and the rendered tooltip HTML.
    - If the operation requires player input, the buttons in `#generalactions` should appear with `data-action` attributes showing the correct `action_resolve` payload

## Validating Operation UI in Harness

After implementing an operation, run the harness and inspect `staging/snapshot.html` to confirm the client-side UI is correct. This replaces manual browser testing.

### Setup

1. Add a `debug_Op_<name>` function in `Game.php` that:
   - Assumes `setup` scenario state (passed via `--scenario`)
   - Pushes the operation onto the machine for the active player
   - Example:
     ```php
     public function debug_Op_move(): void {
         $playerId = $this->getCurrentPlayerId();
         $this->machine->push("move", $this->getPlayerColorById($playerId), []);
         $this->gamestate->changeActivePlayer($playerId);
         $this->gamestate->jumpToState(StateConstants::STATE_PLAYER_TURN);
     }
     ```
2. Run the harness in two steps so you can diff before/after:
   ```bash
   # Step 1: baseline — run setup scenario, save snapshot as before.html
   php8.4 tests/Harness/play.php --scenario tests/Harness/plays/setup.json
   ts-node --project tests/Harness/tsconfig.json tests/Harness/render.ts
   cp staging/snapshot.html staging/before.html

   # Step 2: apply the operation on top of the setup state
   php8.4 tests/Harness/play.php --debug debug_Op_<name> --scenario tests/Harness/plays/setup.json
   ts-node --project tests/Harness/tsconfig.json tests/Harness/render.ts
   # staging/snapshot.html now reflects the operation state
   ```
3. Compare `staging/before.html` and `staging/snapshot.html` to see what changed

### What to check

**For automated operations** (no user input), skip the buttons/clickable-tokens checks. Instead focus on:
- Token positions and states changed as expected (diff `before.html` vs `snapshot.html`, look for elements that moved or changed class/state)
- Game log entries in `#logs` (right column of snapshot) show the expected messages with correct token/place names substituted

**For user-facing operations**, check all of the following:

**Action buttons (`#generalactions`)**
- Buttons should appear for each valid target returned by `getPossibleMoves()` unless buttons=>false in getUiArgs
- Each button must have a `data-action` attribute — search for `data-action` in the HTML
- The payload should be `{"endpoint":"action_resolve","data":{"target":"<targetId>"}}`
- If no buttons appear, check `getPrompt()` returns non-empty and `getPossibleMoves()` returns valid targets
- If operation is skippable it should be Skip button or similar

**Highlighted / clickable tokens**
- The `#harness-click-registry` section lists all elements with click handlers (`_lis` attribute) and all `data-action` elements
- Verify the expected tokens (e.g. hex tiles, cards) appear in this list
- The "action" column shows either `onToken` (generic handler) or the full `action_resolve` payload
- Clickable elements also have the `active_slot` class

**Tooltips**
- The `#harness-tooltip-registry` section lists all registered tooltips
- Search for the token ID or expected tooltip text to confirm it was registered
- Each card shows the node ID and the rendered tooltip HTML

**Title bar**
- The `#pagemaintitletext` element should contain the prompt from `getPrompt()`
- Search for the prompt text in the HTML to confirm it appears

### Simulate user click (resolve step)

After verifying the prompt snapshot above, simulate the user clicking a target:

1. Save the prompt snapshot for comparison:
   ```bash
   cp staging/snapshot.html staging/prompt.html
   ```
2. Find the action payload: look in the snapshot for `data-action` on buttons, or for `active_slot` hexes/tokens in the `#harness-click-registry`. The payload for clicking an active slot is `{"target":"<tokenId>"}`.
3. Create a scenario `tests/Harness/plays/op_<name>.json` that calls the debug function then resolves:
   ```json
   {
     "current_player_id": 10,
     "reset": true,
     "steps": [
       { "endpoint": "debug_Op_<name>", "reload": true },
       { "endpoint": "action_resolve", "data": { "target": "<targetId>" }, "reload": true }
     ]
   }
   ```
4. Run the scenario:
   ```bash
   php8.4 tests/Harness/play.php --scenario tests/Harness/plays/op_<name>.json
   ts-node --project tests/Harness/tsconfig.json tests/Harness/render.ts
   ```
5. Compare `staging/prompt.html` and `staging/snapshot.html` to see what changed — verify tokens moved, dice appeared, game log updated, etc.


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
