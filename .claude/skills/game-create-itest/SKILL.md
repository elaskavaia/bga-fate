---
name: game-create-itest
description: Create a campaign integration test for this Fate BGA project — a test that scripts a full turn through the harness GameDriver in tests/Campaign/. Use this skill when the user asks to add, write, or create an integration test, whether for a specific card ("integration test for card_equip_2_21") or a free-form scenario ("test that monsters entering Grimheim destroy a house"). Also used by game-verify-card when it needs to create missing tests.
argument-hint: card ID or free-form scenario description
---

# Campaign Integration Test Skill

Create one or more integration tests in `tests/Campaign/` that script a full turn through the harness `GameDriver`. These tests exercise real operation flows (useCard prompts, triggers, paygain, monster turns) against in-memory game state.

## Input

Auto-detect from the argument:

- **Card ID** (starts with `card_`) — e.g. `card_equip_2_21`. Parse `card_{type}_{heroNumber}_{cardNumber}`, look up the CSV row, generate tests for each effect branch and each trigger condition.
- **Free-form scenario** — e.g. `"monster enters Grimheim destroys house"`. Ask clarifying questions if the hero/scope is ambiguous, then write a single test.

## Target File

Card ID mode: append to `tests/Campaign/Campaign_<Hero><Category>Test.php` where:

- `<Hero>` is derived from `hno`: `1=Bjorn`, `2=Alva`, `3=Embla`, `4=Boldur`
- `<Category>` is derived from the card type prefix:
  - `card_ability_*` and `card_hero_*` → `Ability` (e.g. `Campaign_BjornAbilityTest.php`). Hero cards go here because there's only one per hero (double-sided = 2 rows) — not worth their own file.
  - `card_equip_*` → `Equip` (e.g. `Campaign_AlvaEquipTest.php`)
  - `card_event_*` → `Event` (e.g. `Campaign_BjornEventTest.php`)

**Legacy exception**: `Campaign_<Hero>SoloTest.php` files (e.g. `Campaign_BjornSoloTest.php`, `Campaign_AlvaSoloTest.php`) exist from early iterations and contain a mix of categories. Before creating a new file, `Glob` for both the category-specific file and the legacy Solo file:

1. If `Campaign_<Hero><Category>Test.php` exists → append there.
2. Else if `Campaign_<Hero>SoloTest.php` exists and already has tests for this category → append there to keep related tests together.
3. Else → scaffold `Campaign_<Hero><Category>Test.php` from the template below.

Do NOT migrate existing tests between files unless the user asks.

Free-form scenario mode: ask the user which file to use, or create a new file (e.g. `Campaign_<Topic>Test.php`).

## When to create multiple tests

Write ONE test unless the card has **multiple effect choices** or **multiple trigger conditions**:

- Split the `r` field on `/` (top-level alternation) — each branch gets its own test (e.g. Flexibility I: `(spendUse:1spendMana:gainAtt_move)/(spendUse:2spendMana:gainAtt_range)/(on(TActionAttack):2spendMana:2addDamage)` → 3 tests).
- If `on` field has multiple triggers (usually when `custom` is used), cover each. Most cards have one trigger.
- **Do NOT** write separate tests for "offered when conditions met" vs "not offered when missing X" — those are unit tests for `Op_spendMana`, `Op_spendDurab`, etc. A campaign test asserts the _effect_, not the gating.

## Test scaffold template

If the target file doesn't exist, create it with this skeleton (replace `<Hero>` and `<Category>` with the resolved names):

```php
<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for <Hero>'s <category> cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_<Hero><Category>Test extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([<heroNum>]);
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }
}
```

**Do not seed decks in `setUp()`.** Seed per-test only when the specific test needs `drawEvent` or reinforcement. Reason: shared deck seeding couples tests and masks flaky ones.

When seeding is needed in a test:

```php
$this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8"]);
$this->seedDeck("deck_event_" . $this->getActivePlayerColor(), ["card_event_1_27_1"]); // Rest
```

## Writing the test body

Standard shape:

```php
public function test<CardName><Effect>(): void {
    $color = $this->getActivePlayerColor();
    // 1. Place the card (if not starting tableau)
    $this->game->tokens->moveToken("<card_id>", "tableau_$color");
    // 2. Seed resources the card needs (mana, XP, damage on hero)
    $this->game->effect_moveCrystals($this->heroId, "green", 3, "<card_id>", ["message" => ""]);
    // 3. Place monsters / move hero to the right hex
    $this->game->tokens->moveToken($this->heroId, "hex_7_9");
    $this->game->getMonster("monster_goblin_20")->moveTo("hex_7_8", "");
    // 4. Seed dice if the effect involves rolls (5=hit, 1=miss)
    $this->seedRand([5, 5, 5]);
    // 5. Drive the flow with respond()
    $this->assertValidTarget("<card_id>"); // sanity-check the card is offered before picking it
    $this->respond("<card_id>");
    $this->confirmCardEffect(); // test helper: respond("1") to accept the paygain confirm prompt
    // 6. Assert the effect
    $this->assertEquals(1, $this->countDamage("monster_goblin_20"));
}
```

For trigger-activated cards (on != empty): trigger the source action (`respond("actionAttack")`, `respond("<hex>")` for move, etc.) and then drive the useCard prompt the trigger queues.

### Event card token ids have an auto-suffix

The CSV key (e.g. `card_event_4_32`) is the card *type*, not a token id. Each copy (per `count`) is created as a separate token with an `_<i>` suffix: `card_event_4_32_1`, `_2`, `_3`. When you `seedHand($cardId, $color)` or `seedDeck($deck, [$cardIds])` for event cards, use the suffixed token id — typically `_1` — not the bare CSV key. Symptom if you forget: token-move call fails with "token not found", or the card never shows up in the `useCard` target list.

Ability and equip cards have `count=1` and no suffix; their CSV key is the token id directly.

## Hex cheat-sheet (Iteration 0+ map)

Use these hexes to avoid spelunking through `misc/map_material.csv`:

- **Grimheim (7 hexes — heroes can't attack from inside, monsters entering destroy houses)**: `hex_8_9`, `hex_9_9`, `hex_10_9`, `hex_9_8`, `hex_10_8`, `hex_9_10`, `hex_10_10`
- **Good "park hero here" plains hexes outside Grimheim**: `hex_7_9`, `hex_5_9` (distance 2 apart → range-2 attack pair), `hex_6_9`
- **Forest hexes (Alva Hero I/II trigger — ending move in forest)**: `hex_5_8`, `hex_7_8`
- **Adjacency pair for "hero adjacent to monster" setups**: hero at `hex_7_9`, monster at `hex_7_8` (or `hex_8_9` if hero is in Grimheim)
- **Range-2 setup**: hero at `hex_7_9`, target at `hex_5_9`
- **"Two adjacent monsters" setup (Nailed Together, Elven Blade)**: hero at `hex_7_9`, monster A at `hex_7_8`, monster B at `hex_6_9`

When unsure, read `misc/map_material.csv` or use `$this->game->hexMap->getAdjacentHexes($hex)` in an ad-hoc debug print.

## Flow patterns to expect (and the pitfalls)

### Pattern: Manual-activation card, single branch (e.g. Belt of Youth)

```php
$this->assertValidTarget("<card_id>");
$this->respond("<card_id>");
$this->confirmCardEffect(); // test helper — respond("1") to the paygain confirm prompt
```

### Pattern: Manual-activation card, multi-branch (e.g. Flexibility I)

```php
$this->assertValidTarget("<card_id>");
$this->respond("<card_id>");
$this->respond("choice_<N>"); // 0-indexed branch pick (also doubles as the confirm)
// sub-ops resolve...
```

### Pattern: Trigger-activated card (e.g. Elven Blade on=TAfterActionAttack)

```php
$this->respond("actionAttack");
$this->respond("<hex>"); // attack target
// Trigger queues a useCard prompt with confirm=true — MUST respond explicitly
$this->assertOperation("useCard");
$this->assertValidTarget("<card_id>");
$this->respond("<card_id>");
// Sub-op (dealDamage, heal, etc.) may ALSO prompt even with a single target
$this->respond("<hex_or_choice>");
```

### Pitfall: Hierarchical triggers share one `useCard` prompt

`TActionAttack` chains through `TRoll` hierarchically. Cards with `on=TRoll` (e.g. Bjorn Hero I) and `on=TActionAttack` (e.g. Quiver, Trollbane) **don't produce separate prompts** — they appear together in a single `useCard` prompt's `target` list. Consequences:

- Don't `skip()` to dismiss a phantom "earlier" trigger — that call consumes the real prompt you wanted.
- Just `assertValidTarget($cardId)` and `respond($cardId)` directly; unrelated options coexist in the same list and don't need dismissing first.
- If you need to confirm a specific card ISN'T offered (e.g. Trollbane vs non-trollkin), check the current op's `target` array, not the next prompt.

### Pitfall: `useCard` with `confirm=true`

When a trigger queues `useCard`, the prompt has `confirm=true`. **Even if there's a single eligible card, the player must click it.** Do not expect auto-resolve. Pattern:

```php
$this->assertOperation("useCard");
$this->respond("<card_id>");
```

### Pitfall: Paygain sub-ops may still prompt

After picking the card in `useCard`, child ops (`dealDamage`, `heal`, `spendMana`) can still prompt for their target even when there's only one valid choice. Don't assume auto-resolve — if the flow stalls, **dumpState to see what op is waiting**.

If the child op is a *target-picking* op (`dealDamage`, `heal`, `moveHero`) with multiple valid targets, **don't call `confirmCardEffect()`** — picking the target IS the confirmation. `confirmCardEffect` only applies when the child op has no further target choices (e.g. `2addDamage`, `2heal(self)`). Symptom if you call it incorrectly: `checkUserTargetSelection:01` error, because `"1"` isn't a valid hex/target.

### Pitfall: Hero starts in Grimheim

Default hero start is `hex_8_9` (or similar Grimheim hex). Heroes **cannot attack from inside Grimheim**. For attack tests, always `moveToken($this->heroId, "hex_7_9")` first.

### Pitfall: Seed the dice before any attack

Any attack — hero or monster — rolls dice via `bgaRand()` and is non-deterministic unless seeded. If the test expects a hit (or a miss), call `seedRand([...])` **before** the op that triggers the roll. One value per die: `5` = hit, `3` = rune, `1` = miss. For monster attacks, seed before `skipOp("turn")`; for hero attacks, seed before `respond("actionAttack")`.

```php
$this->seedRand([5]); // goblin str=1, one hit
$this->skipOp("turn"); // end turn → monster turn attacks
```

Symptom when missing: flow sails past the expected `useCard`/`dealDamage` window because the roll whiffed, and the test ends up in the next turn's `turn` op.

### Pitfall: When testing damage make sure monster survived the attack

If total damage >= monster health, the token is removed from the map and `countDamage($monster)` reads **0** — the crystals don't hang around on the corpse. Either pick a beefier monster or seed fewer hits so the damage tokens stick. Quick health reference:

- sprite: 1, goblin: 2
- brute: 3, skeleton: 3
- troll: 6

If you actually want to assert the monster died, check `tokenLocation($monster) === "supply_monster"` (or similar) instead of reading damage.

### Pitfall: Manually placing a stat-bearing card on tableau requires `recalcTrackers()`

Hero attribute trackers (`strength`, `range`, `move`, `health`, `hand`) are recomputed only at setup and end-of-turn (`Hero::recalcTrackers`). When a test does `moveToken($cardId, "tableau_$color")` for a card with a `strength` (or other stat) field, the tracker stays stale — the new dice/range/etc. won't show up. Pattern:

```php
$this->game->tokens->moveToken($cardId, "tableau_$color");
$this->game->getHero($color)->recalcTrackers();
```

Symptom: damage assertions short by exactly the card's stat bonus; range/move asserts off by the bonus. Applies to any tableau card with `strength`, `attack_range`, `move`, `health`, or `hand` — not just ability cards. Real game flow goes through `Op_upgrade`, which already recalcs, so production code doesn't need this; it's purely a test-setup shortcut.

## Debug workflow

When the test fails or stalls:

1. Add `$this->dumpState("label")` right after the last successful `respond()`.
2. Run the test: `npm run test -- --filter '<methodName>' tests/Campaign/Campaign_<Hero>*.php`
3. Read the dumped `type` and `target` fields to see what op is waiting and what it expects.
4. Add the missing `respond()` call.
5. Repeat until green.
6. **Remove all `dumpState()` calls before finalizing.** They're for debugging, not for the committed test.

`skipIfOp("drawEvent")` is also useful — `turnEnd` often queues a drawEvent that isn't part of what the test is exercising.

## Iteration loop

After writing the test:

1. Run the targeted test: `npm run test -- --filter '<methodName>' tests/Campaign/Campaign_<Hero>*.php`
2. If it fails, use the debug workflow above to fix the flow. Do NOT give up and weaken assertions — find the right `respond()` sequence.
3. Once green, check whether the test modified `setUp()` or shared helpers (`CampaignBase.php`):
   - If yes: run full suite `npm run tests > /tmp/test_output.txt 2>&1; tail -10 /tmp/test_output.txt`
   - If no: skip the full suite.

## When done

1. **Card-ID invocation only**: flip the card's line in `misc/docs/PLAN.md` from `[ ]` to `[x]` and append `has tests` to the description.
2. Report:
   - Test method name(s) as clickable markdown links: `[testCardName](tests/Campaign/Campaign_<Hero>SoloTest.php#L<line>)`
   - Branches/triggers detected from the card's `r` and `on` fields (so the user can spot misreads)
3. **Reflection** (see below).

## Reflection

**DO NOT SKIP THIS STEP**

After the test passes, pause and ask: _did I learn something non-obvious while writing this test that isn't already in this skill?_

Signals that something is worth capturing:

- The flow stalled and `dumpState` revealed an unexpected op/prompt
- A helper or setup trick wasn't obvious from existing tests
- A trigger fired (or didn't fire) in a surprising way
- A rule-expression pattern behaves differently than the DSL reads
- An assertion mode that is more reliable than the first thing you tried

If yes, **prompt the user** with a short summary:

> **Pitfall candidate**: [one-line description]. Should I add this to the skill's "Flow patterns" or "Pitfalls" section?

Keep the prompt tight — one candidate pitfall per message. If the user agrees, add it to the SKILL.md under the most relevant existing heading (or create a new one if needed). If the user declines or it's something they already know, skip and finish.

**Do NOT** add entries for:

- Things that are already in the skill (re-check before prompting)
- One-off card quirks that won't generalize to other cards
- General PHP/PHPUnit facts

The goal is to grow the skill from real friction, not to inflate it.

## Output Format

```
## Integration Test Created: <card_name or scenario>

### Detected
- **Branches in `r`**: <list of top-level `/` splits, or "single branch">
- **Triggers (`on`)**: <list, or "none (manual)">

### Tests
- [testMethod1](tests/Campaign/Campaign_<Hero>SoloTest.php#L<line>)
- [testMethod2](tests/Campaign/Campaign_<Hero>SoloTest.php#L<line>)  <!-- if multi-branch -->

### Result
- <N> tests, <M> assertions, passed in <time>s
- PLAN.md updated: <line>  <!-- only for card-ID mode -->
```

## Important

- Never write offering/gating negative tests (`testCardNotOfferedWhenNoMana`, etc.) — those duplicate Op_spendMana / Op_spendDurab / Op_spendUse unit tests. Only write them if the gating logic is card-specific (e.g. Long Shot I's range ≥ 2 filter is unique to the card).
- Don't ask the user about flow specifics before writing — it's faster to write a best guess, run it, use dumpState to diagnose, and fix. Reserve questions for genuinely ambiguous scenarios (multi-hero, non-obvious target monster).
- Keep test bodies tight. One blank-line section per step (place → seed → drive → assert).
