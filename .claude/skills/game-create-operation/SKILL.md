---
name: game-create-operation
description: Create a new game operation (Op_<name>) in this BGA project. Use this skill whenever the user asks to create, add, or implement a new operation, such as "create heal operation", "add a new move operation", or "implement Op_xxx". Walks through the full operation creation checklist including stub file, material CSV entry, design review, implementation, tests, and harness validation.
argument-hint: <name>
---

# Create a New Operation

Step-by-step checklist for adding a new operation to the Fate BGA codebase. Follow this plan exactly — create a plan first with all items from this list.

Before starting read docs/DESIGN.md

## Steps

1. Create `modules/php/Operations/Op_<name>.php` with a **minimal empty template**
   - Extend `Operation` (default choice)
   - Only add `resolve()` as an empty stub
   - Add a comment block at the top with relevant rules from RULES.md
2. Add operation definition to `misc/op_material.csv`
3. Run `npm run genmat` to update Material.php
4. Run `npm run tests` — **verify everything passes before continuing**
5. **Present design to user for approval** — before implementing logic, outline:
   - Which rules apply (quote from RULES.md)
   - Operation type (automated / user-facing / countable)
   - What `getPossibleMoves()` will return (valid targets, error cases)
   - What `resolve()` will do (token moves, state changes, sub-operations)
   - Any new game elements or helpers needed
6. Determine operation type:
   - **Automated** — no user choice (e.g. reinforcement, turn end)
   - **User-facing** — player selects a target (e.g. move, attack)
   - **Countable** — repeatable or customizable X (e.g. gain X gold) → switch base class to `CountableOperation`
7. For **user-facing** operations, start from the Operation Template below (copy `Op_xxx` and rename). Implement:
   - `getPossibleMoves()` — valid targets as assoc array
     - Keys are token IDs so the client can highlight them for clicking
     - Valid: `["q" => Material::RET_OK]`, invalid: `["q" => ERR_CODE]`
   - `canSkip()` — return `true` if action is optional
   - `getPrompt()` — prompt text shown to the player (use `clienttranslate()`)
8. Implement `resolve()` — executes the game logic
   - Use `$this->getCheckedArg()` to get the validated user selection
   - Use `$this->dbSetTokenLocation()` to move tokens with notifications
   - Use `$this->queue()` to chain sub-operations
   - For multi-step operations, the operation may re-queue itself with extra data
   - For **automated** operations, `resolve()` may be the only required method
9. If the operation needs custom client-side behavior, override:
   - `getUiArgs()` — UI hints (e.g. `["buttons" => false]` when player clicks map/tokens instead of buttons)
   - `getExtraArgs()` — extra data sent to client (e.g. `["token_div" => $id]`)
10. Ask user to code review before moving to next step
11. Add tests in `tests/`
    - Instantiate via `$this->game->machine->instanciateOperation($type, $owner, $data)`
    - Test `getPossibleMoves()` returns expected valid/invalid targets
    - Test `resolve()` produces correct side effects (token moves, state changes)
12. Add a `debug_Op_<name>` function in `Game.php` that sets up the game state and pushes the operation to the machine, then run the harness to validate the UI:
    ```bash
    php8.4 tests/Harness/play.php --debug debug_Op_<name> --scenario tests/Harness/plays/setup.json
    # Then run the renderer and read staging/snapshot.html to verify layout, buttons, and tooltips
    npx ts-node --project tests/Harness/tsconfig.json tests/Harness/render.ts
    ```
13. If new game elements are introduced, follow the "Adding New Game Element" checklist in `misc/docs/PROCEDURES.md`
14. Update DESIGN.md if needed
15. Update PLAN.md if needed
16. If you learned anything new update CLAUDE.md

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
