## Architecture

### Operation-Based State Machine

The game logic is built around an operation-based state machine:

- **OpMachine** (`modules/php/OpCommon/OpMachine.php`) — Core state machine that manages operation queue and execution
- **Operation** (`modules/php/OpCommon/Operation.php`) — Abstract base class for all game operations
- **Operation implementations** (`modules/php/Operations/Op_*.php`) — Concrete operations
- **ComplexOperation** — Operations that can contain sub-operations (delegates)
- **CountableOperation** — Operations that can be repeated a specific number of times

Operations are queued in the database (DbMachine) and executed sequentially, enabling complex game flows with undo/redo support.

### Token Management

- **DbTokens** (`modules/php/Db/DbTokens.php`) — Token storage, notifications, counters, and material-based creation
- Tokens represent all physical game pieces (cards, dice, workers, resources, etc.)

### Hex Map

- **HexMap** (`modules/php/Common/HexMap.php`) — All hex grid logic: adjacency, distance, terrain queries, pathfinding, and monster movement helpers
- Accessed via `$this->game->hexMap->` from operations and `$game->hexMap->` from tests
- Key functions: `getAdjacentHexes`, `getReachableHexes`, `getDistanceMapToGrimheim`, `getMonsterNextHex`, `getMonstersOnMap`, `isHeroAdjacentTo`, `getHexesInLocation`, `isOccupied`, `isInGrimheim`

### Game States

State classes in `modules/php/States/` handle different game phases:

- GameDispatch — Main game flow dispatcher
- PlayerTurn — Individual player turn handling
- MultiPlayerMaster/MultiPlayerTurnPrivate/MultiPlayerWaitPrivate — Multiplayer coordination
- PlayerTurnConfirm — Turn confirmation state
- MachineHalted — Error/debug state

### Client-Side Code

TypeScript files in `src/` compile to a single `Game.js`:

- **Game.ts** — Main game class (extends GameMachine), entry point for client logic
- **Game0Basics.ts** — First file in compilation order, basic definitions
- **Game1Tokens.ts** — Token rendering and management
- **GameMachine.ts** — Client-side state machine handling
- **LaAnimations.ts** — Animation utilities

SCSS files in `src/css/` compile to `fate.css` with `GameXBody.scss` as the entry point.

---

## Token Naming Convention

Following the project's token naming pattern (`key = supertype_type_instance`):

- `hero_<hero>` — Hero miniature (e.g. `hero_1` = Bjorn)
- `house_<n>` — Town piece (e.g. `house_0` = well)
- `monster_<type>_<n>` — Monster tile (e.g. `monster_goblin_5`, `monster_troll_2`)
- `card_monster_<n>` — Monster card (e.g. `card_monster_12`); type includes `ctype_yellow` or `ctype_red`
- `card_hero_<hero>_<n>` — Hero card (e.g. `card_hero_1_1` = Level I starting, `card_hero_1_2` = Level II upgraded). Odd `<n>` = front (Level I), even `<n>` = back (Level II). Two-sided card.
- `card_ability_<hero>_<n>` — Ability card (e.g. `card_ability_1_3`). Odd `<n>` = Level I, even `<n>` = Level II (flipped side). These are two-sided cards.
- `card_equip_<hero>_<n>` — Equipment card (e.g. `card_equip_1_7`)
- `card_event_<hero>_<n>_<i>` — Event card (e.g. `card_event_1_15_2`) - last <i> some even cards are duplicated, so it tell them apart
- `marker_<color>_<n>` — Player marker (e.g. `marker_ff0000_1`)
- `monster_legend_<n>_<level>` — Legend monster tile. 6 legends numbered 1–6 (1=Queen, 2=Seer, 3=Grendel, 4=Surt, 5=Hrungbald, 6=Nidhuggr). `_1` = yellow (Level I), `_2` = red (Level II) — same two-sided pattern as ability cards. `_1` starts in `supply_monster`, `_2` starts in `limbo`. When a red legend card is drawn, swap `_1` out and place `_2` on the map. Stats in `monster_material.csv`. Legends destroy 3 town pieces on entering Grimheim and can swap places with blocking monsters during movement.
- `crystal_green_<n>` — Mana crystal (individual tokens on cards). Also used as stun marker on monsters (Suppressive Fire): a green crystal on a monster means it skips its next movement and the crystal is removed.
- `crystal_yellow_<n>` — Gold/XP crystal (individual tokens on cards)
- `crystal_red_<n>` — Damage crystal (individual tokens on cards and things)
- `rune_stone` — Time track marker (singleton)
### Dice
The "state" of the die token is the dice value (1-6). These side values will map to sides of real die.
- `die_attack_<n>` — Attack die (1..20).
- `die_monster_<n>` — Monster die


Shortening of words:
- Equipment -> equip

**Location naming:**

- `hex_<x>_<y>` — Board hex area (e.g. `hex_9_9` = Grimheim)
- `supply_monster` — Monster supply
- `hand_<color>` — Player hand (e.g. `hand_ff0000`)
- `tableau_<color>` — Player area (e.g. `tableau_ff0000`)
- `pboard_<color>` — Player board
- `cards_<color>` — Active cards area
- `deck_ability_<color>` — Ability pile
- `deck_equip_<color>` — Equipment pile
- `deck_event_<color>` — Event deck
- `discard_<color>` — Discard pile 
- `deck_monster_yellow` / `deck_monster_red` — Monster card decks
- `timetrack_<n>` — Time track step slot; actual step tracked as `token_state` on `rune_stone`

---



## Model Classes (`modules/php/Model/`)

Domain model objects for game characters. Created via factory methods on `Game`:
- `$game->getHero($owner)` — Hero by player color
- `$game->getHeroById($heroId)` — Hero by token id (e.g. `"hero_1"`)
- `$game->getMonster($monsterId)` — Monster by token id
- `$game->getCharacter($id)` — Returns Hero or Monster based on id prefix

### Character (base)
- `getId()`, `getHex()`, `getAttackRange()` (default 1), `moveCrystals()`, `getRulesFor()`
- `getArmor()` — armor value from material (default 0, Draugr=1)
- `hasCover()` — true if on a forest hex (blocks "hitcov" results)
- `beginDefense()` — call before rolling dice against this character. Resets per-attack state (armor remaining). **Important**: Character instances carry attack state (`armorRemaining`), so the same instance must be used for `beginDefense()` + all `applyDamage()` calls within one attack.
- `applyDamage($rule, $attackerId)` — process a single die result ("hit", "hitcov", "miss", "rune"). Handles cover, Dead faction rune-as-hit, and armor absorption. Places a red crystal if hit lands. Returns 1 (hit) or 0 (miss/absorbed).
- `moveTo($location, $message, $args)` — move character token + update hex map cache

### Hero extends Character
- `getOwner()` — player color
- `getAttackStrength()` — sum of hero card + equipment + ability strength on tableau
- `getMaxHealth()` — from hero card on tableau
- `getAttackRange()` — max attack_range from equipment cards (default 1)
- `gainXp($amount)` — move yellow crystals from supply to tableau
- `applyDamageEffects($amount)` — apply damage; if knocked out: reset to 5 damage, move to Grimheim, destroy 2 houses

### Monster extends Character
- `getFaction()`, `getHealth()`, `getXpReward()`
- `getAttackRange()` — firehorde=2, others=1
- `applyDamageEffects($amount, $attackerId)` — check if killed: remove crystals, move to supply, log kill with attacker name. Caller handles XP award separately.

**Faction effects** (handled in `Character::applyDamage`):
- Dead: rune die results count as hits when Dead monsters attack
- Draugr: armor=1, absorbs 1 hit per attack (via `beginDefense` + `armorRemaining` state)

### Card (`modules/php/Model/Card.php`)

Base class for card instances created on the fly during trigger dispatch. Each card on a player's tableau or hand is instantiated as a `Card` (or subclass) by `Op_trigger::resolve()` via `Game::instantiateCard($card, $op)`.

- Constructor: `(Game $game, string|array $cardOrId, Operation $op)` — accepts token ID or full token row (with `key`, `location`, `state`). Owner comes from `$op->getOwner()`.
- `getId()`, `getOwner()`, `getRulesFor($field)`, `getDamage()`, `getMana()`, `getGold()`
- `queue($type, $owner, $data)` — delegates to the parent operation's `queue()` with this card preset in `data["card"]`
- `onTrigger($triggerName)` — routes to `on<TriggerName>()` if the subclass defines it, else falls back to `onTriggerDefault()`
- `canTrigger($triggerName)` — returns true if the card can react to this trigger type (base: checks if `on<TriggerName>` method exists)
- `canBePlayed($triggerName, &$errorRes)` — returns true if the card is actually playable. Base returns true; `CardGeneric` overrides with full checks.
- `getUseCardOperationType()` — maps card supertype to action op: equip → `useEquipment`, ability/hero → `useAbility`, event → `playEvent`

**CardGeneric** (`modules/php/Model/CardGeneric.php`) — default class when no bespoke subclass exists. Implements the standard voluntary trigger flow:
- `canTrigger()` — also returns true when the Material `on` field matches the trigger type
- `canBePlayed()` — checks trigger match, once-per-turn state, non-empty `r` field, and whether the `r` expression's op has valid targets
- `onTriggerDefault()` — if `canBePlayed()`, queues the corresponding `useEquipment`/`useAbility`/`playEvent` with `prompt=true` so the player can confirm or skip

**Bespoke card classes** (`modules/php/Cards/CardEquip_<Name>.php`, etc.) — per-card classes that override `on<TriggerName>()` hooks for cards needing custom behavior (passive effects, complex logic, multiple triggers). Class name derived from Material `name` field: `"Home Sewn Cape"` → `CardEquip_HomeSewnCape`. Created via `Game::instantiateCard()` which tries the bespoke class first, then falls back to `CardGeneric`.

---

## Operation Catalog

Operations are the building blocks of game flow. They are queued in the state machine and dispatched in order.

Kinds: `auto` = server-resolves without player input; `player` = waits for player choice; `main` = counts as 1 of 2 actions per turn; `free` = doesn't consume a main action slot.

### Common Machine Operations 

- `nop` — No-op placeholder
- `savepoint` — Marks an undo savepoint
- `or` — Player picks one branch from multiple choices. Notation: `a/b`
- `order` — Player picks execution order for a set of ops. Notation: `a+b`
- `seq` — Runs sub-operations in sequence. Notation: `a,b` or `a;b` (; is lower op priority)
- `paygain` — Runs sub-operations in sequence. Notation: `cost:effect`
- `counter` - Modify next operation on stack with count from resolving its expression


### Main Game Operations

- `turn` (player) — Main player turn: pick 2 actions + free actions 
- `turnconf` (player) — Confirm end of turn (undo checkpoint) 
- `turnEnd` (auto) — Reset turn state, queue next player or monsterTurn 
- `turnMonster` (auto) — Advance time track; check win/loss; queue next round 
- `actionMove` (main) — Hero moves up to 3 hexes 
- `actionAttack` (main) — Hero attacks monster within range 
- `actionPrepare` (main) — Draw 1 event card 
- `actionFocus` (main) — Add 1 mana to a card 
- `actionMend` (main) — Remove 2 damage from hero (5 in Grimheim, may target equipment cards too) 
- `actionPractice` (main) — Gain 1 XP (yellow crystal) 
- `useEquipment` (free) — Activate an equipment card 
- `useAbility` (free) — Activate an ability card (costs mana)
- `playEvent` (free) — Play an event card from hand 
- `shareGold` (free) — Give gold to another hero — *notimpl*


### Card Effect Operations 

These are **generic parameterized operations** used as building blocks to implement card effects.
They are queued by `playEvent`/`useEquipment`/`useAbility` after the card is played.
Most are Countable (X = count) and take a target that is either pre-seeded or player-chosen.

See other operations in misc/op_material.csv

**Targeting**: target can be pre-set when queuing (e.g. "self") or left for the player to pick
from a filtered set. 

Common target filters (passed as "parameter" of operation):
- `self` — the acting hero
- `adj` — adjacent character (monster or hero depending on action content, including self)
- `range` — monster within hero's attack range
- `range2`, `range3` — monster within fixed range N (not hero's attack range)
- `any` — any card on hero's tableau
- `equip` — equipment card on hero's tableau

**Filter conditions** (if uses non ident characters quotes needed):
This is additional expression that is evaluated on the target using the MathExpression
engine (`Base::evaluateExpression`). Terms are resolved via `Game::evaluateTerm`:
- numeric rules fields (`rank`, `health`, `strength`, `xp`, ...) resolve via `getRulesFor`
- bareword predicates: `legend`, `not_legend`, `trollkin`, `firehorde`, `dead`, `adj`,
  `closerToGrimheim`, `healthRem`
- `count*` terms dispatch to methods on `Game` (e.g. `countRunes`)

Examples: `'rank<=2'`, `'hp<=2'`, `'rank3+legend'`, `trollkin` (bareword, no quotes needed).

**Not separate operations** (handled as modifiers/hooks on existing operations):
- "Prevent monster from moving" — green crystal placed on monster by `c_supfire`, checked by `monsterMoveAll` (crystal stays until next trigger)


### Trigger System

Cards (events, abilities, equipment) can have an **`on`** field in Material specifying when they can be activated.
At key points during gameplay, an `Op_trigger` is queued which walks every card on the active player's tableau and hand, instantiates a `Card` object for each, and calls `onTrigger($triggerType)`.

**`on` column** — timing trigger for when the card can be played:
- (empty) — play anytime during your turn (once per turn; tracked via token state=1, reset in turnEnd)
- `actionAttack` — play during/after an attack action
- `roll` — play after a dice roll
- `damage` — play when receiving damage
- `monsterMove` — play *before* the Monsters Move step (fires per player, allows Suppressive Fire)
- `monsterAttack` — play after a monster attacks you
- `custom` — card has a bespoke class under `modules/php/Cards/` that handles its own trigger logic

**How triggers fire:**

1. An operation calls `$this->queueTrigger("triggerType")` (or `queueTrigger()` which defaults to the operation's own type).
2. This queues `Op_trigger(triggerType)` for the current player.
3. `Op_trigger::resolve()` walks every card on the player's tableau and hand, instantiates each via `Game::instantiateCard($card, $this)`, and calls `$card->onTrigger($triggerType)`.
4. For each card, `Card::onTrigger()` routes to `on<TriggerName>()` if the subclass defines it, else to `onTriggerDefault()`.
5. **Bespoke cards** (with a class under `Cards/`) handle their own logic in the hook method — they may queue ops, read game state, or do nothing.
6. **Generic cards** (`CardGeneric`) check `canBePlayed($triggerType)` — verifying the `on` field matches, the card hasn't been used this turn, the `r` field is non-empty, and the effect has valid targets. If playable, queues `useEquipment`/`useAbility`/`playEvent` with the card preset and `prompt=true`, so the player can confirm or skip each card individually.

Unlike the old design (single "pick one card" prompt), the new flow offers each matching card as a separate confirm-or-skip prompt. This correctly allows the player to react with multiple cards per trigger.

**Where triggers are queued (operation → trigger → description):**

```
Op_turnStart
  → trigger(turnStart)       — at start of player turn (passive start-of-turn effects)
Op_roll
  → trigger(roll)            — after a hero rolls attack dice
  → trigger(actionAttack)    — after a roll initiated by Op_actionAttack
Op_actionMove
  → trigger(actionMove)      — after hero movement resolves
Op_resolveHits
  → trigger(resolveHits)     — before damage is applied to a hero (for damage prevention)
Op_dealDamage
  → trigger(monsterKilled)   — when a monster is killed
Op_turnEnd
  → trigger(turnEnd)         — at end of player turn
```

**Attack action trigger sequence (example):**

```
actionAttack → player picks target → roll dice
  → trigger(roll)          — each card reacts: bespoke cards auto-fire, generic cards prompt individually
  → trigger(actionAttack)  — same: each matching card is offered as a separate confirm-or-skip
  → resolveHits            — converts dice to damage
    → trigger(resolveHits) — damage prevention cards offered individually
  → dealDamage             — applies damage to monster
    → trigger(monsterKilled) — if monster died, each matching card reacts
```

Triggered card effects (like `2addDamage` from Master Shot) are queued between the trigger and subsequent operations, so they modify the ongoing action (e.g., adding damage dice before `resolveHits` counts them).

### Hero Attribute Trackers

Hero attributes (strength, range, move, health) are stored as tracker tokens in the DB: `tracker_{attr}_{color}`.

**Token IDs:** `tracker_strength_{color}`, `tracker_range_{color}`, `tracker_move_{color}`, `tracker_health_{color}`

**How it works:**
- `Hero::recalcTrackers()` — recomputes all trackers from base card values (tableau cards). Called at setup and end of turn.
- `Hero::incTrackerValue($type, $delta)` — bumps a tracker mid-turn (e.g. Flexibility: `$hero->incTrackerValue("move", 1)`)
- Public getters (`getAttackStrength()`, `getAttackRange()`, `getMaxHealth()`, `getNumberOfMoves()`) read from trackers
- Base computation methods (`calcBaseStrength()`, `calcBaseRange()`, `calcBaseMove()`, `calcBaseHealth()`) derive values from tableau cards

**Lifecycle:** trackers are created per hero in `setupGameTables`, set to base values via `recalcTrackers()`, may be bumped mid-turn by card effects, and reset at end of turn in `Op_turnEnd`.

---

## Client-Side DOM Structure

The client renders tokens by placing them inside DOM elements whose `id` matches the token's `location` field. Knowing the location → DOM mapping is essential when adding new UI areas.

### Hex Board

- `map_area` — Container div for the entire hex grid
- `hex_{q}_{r}` — Individual hex cell (e.g. `hex_9_9` = Grimheim)

Hexes use pointy-top axial coordinates, center at (9,9), radius 8. Tokens placed at a hex location become children of that hex's div. Hexes get class `.active_slot` when they are valid move targets. First row is 1.

### Player Areas

- `player_areas` — Wrapper for all player zones
- `tableau_{playerColor}` — Individual player zone (tableau)
- `hand_{color}` — Player's private hand of event cards
- `discard_{color}` — Player's discard pile? TODO check?
- `deck_event_{color}` — Player's event draw pile on tableau
- `deck_ability_{color}` — Player's ability pile on tableau
- `deck_equip_{color}` — Player's equipment pile on tableau

Player board tokens (crystals, cards, markers) should live in `tableau_{color}`. Player-colored tokens use `tableau_{color}` as their location on the server side; the client must map this to the correct DOM element.


### Time Track

The time track is not yet wired to a DOM element. Planned:

- `timetrack_1` — Container for the time track strip
- `timetrack_1_{n}` — Individual step slot (1–10 for short track) - mapped on client side from state of `rune_stone`

The `rune_stone` token lives at location `timetrack_1` on the server. The client needs a matching div per step so the token can be parented there.

### Action Slot Markers

When a player picks a main action, a marker token moves to `aslot_{color}_{actionType}`. These slots need matching DOM elements on the player board.

- `aslot_{color}_{actionType}` — Slot showing which action the player chose (e.g. `aslot_ff0000_actionPractice`)

### Supply / Off-Board Locations

These locations hold tokens that are not on the map. They should have a hidden or off-screen DOM element so token parenting doesn't break.


- `supply_die_attack` — Attack and damage dice pool
- `supply_die_monster` — Monster die pool
- `supply_crystal_green` / `supply_crystal_red` / `supply_crystal_yellow` — Crystal supply pools
- `supply_monster` — Undeployed monster tiles

- `deck_monster_yellow` / `deck_monster_red` — Monster card draw piles
- `display_monsterturn` — Drawn monster cards during reinforcement (cleared at start of next monster turn); state 0 = placed, state 1 = skipped (grayed out)

- `oversurface` — Transparent overlay for phantom token animations (pointer-events: none)
- `limbo` — Off-screen sink for tokens not yet placed

---

## Game Log Style

Log messages use the hero name (e.g. `${token_name}`) instead of `${player_name}`. This matches the cooperative/thematic feel — the game narrates what characters do, not what players do.

Pattern: **"Character" does "stuff"**
- `${token_name} moves to ${place_name}`
- `${token_name} attacks ${token_name2}`
- `${token_name} gains ${count} gold`

Use `char_name` (character name) instead of `token_name` when the subject is the hero but the token being moved is something else (e.g. crystals): `${char_name} gains ${count} gold`.

Hero names are colored via `tc` field in material (e.g. Bjorn = green, Alva = blue). The client wraps them in a `<span>` with the hero's color using `getTokenPresentaton()`.

---

## Key Technical Decisions

1. **Cooperative game**: All players win or lose together. No hidden scoring. The `is_coop: 1` flag is already set in gameinfos.
2. **Player order**: Fixed order, chosen at start. All players act each round, then monsters act.
3. **Hex board representation**: Store adjacency as a PHP array (or JSON data file). Do not try to compute hex math — the board is irregular with named locations.
4. **Monster movement**: Pre-compute paths from each board edge to Grimheim (following arrows + roads). Store as lookup table.
5. **Crystals as counters**: Gold/experience and mana are tracked as individual crystal tokens.
6. **Damage tracking**: Damage on heroes/monsters tracked using red crystals (`crystal_red`), same as the physical game's "damage dice" which are just counters. No separate damage tokens needed.
7. **Card effects**: Implement as operations. Each unique card effect gets an operation class or a parameterized generic operation.
8. **Undo support**: Use existing DbMultiUndo infrastructure. Allow undo within a turn (before confirming end of turn).
9. **Monster AI**: Fully deterministic (no choices for monsters), so monster turn can be auto-resolved on server. Client just animates notifications.
10. ~~**Event deck exhaustion**~~: See Assumptions section.


## Overview of card effects

In Fate: Defenders of Grimheim, card effects are tightly integrated into the hero's development and the "tower defense" rhythm of the game. They are primarily categorized by the type of card (Ability, Equipment, or Event) and how they interact with the game's action economy. [1, 2] 
1. Activated / Free Action Effects (Abilities & Equipment)
Most character-specific benefits fall into this category. They are generally treated as Free Actions that can be used at any time during your turn, but not during a main action unless specified. [3] 

* Once-Per-Turn Limit: Standard Equipment and Ability cards can only be used once per turn.
* Mana-Activated: Some cards require spending Mana to trigger their effect (e.g., "Spend 2 Mana to add 2 damage").
* Action Modifiers: These are used within the context of another action, such as adding damage to an Attack Action after the dice are rolled. [3, 4, 5, 6] 

2. Triggered / Reactive Effects
These effects occur automatically or can be played in response to specific game states, particularly during the Monster Turn. [7, 8] 

* Damage Prevention: A major exception to the "once per turn" rule. Cards that prevent damage can be used once each time you receive damage, allowing multiple uses per round if attacked multiple times.
* Reaction Events: Certain Event cards are specifically designated as Reaction cards, which can be played during the monster turn to deal damage or defend. [5, 7, 9] 

3. Static / Persistent Effects
These are "always-on" rules or modifications that don't require activation but change your hero's base stats or rules. [10] 

* Rule Changes: For example, a card might state your hero moves 4 spaces instead of the standard 3, or provide a permanent Strength Bonus to every attack action you take.
* Mana Regeneration: Static effects at the end of the turn that automatically add mana to specific cards. [3, 8, 10] 

4. Instant / One-Time Effects (Events)
These are found on Event Cards, which are drawn into your hand rather than placed in your tableau. [2] 

* Immediate Resolution: You play them, resolve the text (e.g., "Move 2 steps" or "Deal damage"), and then discard them.
* Flexibility: You can play any number of event cards during your turn as long as they don't interrupt a main action. [3, 7, 11] 
* Conditional: Some of them can only be played during some other events which is similar to Triggered ability 

5. Replacement Effects
Less common but present in specialized hero builds, these change one outcome for another. [10] 

* Example: Syndra's "Circle of Life" allows damage that would be dealt to her to be placed on the card instead, where it can later be repaired. [4] 



## Assumptions (to verify with game designer)

1. **Event discard pile is face up (public)**: The rules don't specify whether the event discard pile is face up or face down. We assume face up, as is standard for card game discard piles. All players can see discarded event cards.
2. **Event deck is not reshuffled when exhausted**: The rules don't mention reshuffling the discard pile when the event deck runs out. We assume the deck simply stays empty — no auto-reshuffle.
3. **"Move X" is mandatory; "Move up to X" is optional**: "Move 2 areas" means the hero must move exactly 2 steps if possible (falling back to max reachable if blocked). "Move up to 1 area" allows the player to choose not to move. Implemented via CountableOperation: `2moveHero` (count=2, mcount=2) vs `[0,1]moveHero` (count=1, mcount=0).
4. **moveMonster cannot push into Grimheim**: When a player moves a monster via card effects (Kick, Swift Kick, etc.), we assume the player will not select a Grimheim hex as the destination. No `monsterEntersGrimheim` logic is implemented for player-initiated monster movement.
5. **Stitching can heal adjacent heroes but only repair own equipment**: The card says "Remove damage from any hero or equipment within range 1." We assume "within range 1" applies to heroes (can heal any adjacent hero including self), but equipment repair is limited to the acting player's own tableau.
