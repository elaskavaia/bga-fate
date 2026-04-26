## Architecture

### Definitions

#### General Framework definitions

- **Operation (Simple)** — atomic game action with no sub-operations (e.g. move a token, draw a card)
- **Operation (Complex)** — operation that delegates to sub-operations like OR choice, sequence, etc
- **Effect** — what an ability/card *does* when triggered or played, expressed as operation DSL
- **Precondition** — predicate that must hold before an effect can trigger, expressed as operation DSL evaluated for success/failure
- **DSL (Operation DSL)** — mini-language used in CSVs (effect/precondition columns) that compiles into a queue of operations. Full grammar and examples in [EFFECTS_DSL.md](EFFECTS_DSL.md) (covers both the Op DSL and the Math DSL used in `pre` fields and predicate arguments).
- **Token** — every physical game piece (hero, monster, card, crystal, die, marker); has an id, location, and state
- **Location** — where a token lives (a hex, a deck, a hand, a tableau, supply, limbo); a string key, not a coordinate
- **Token State** — small integer attached to a token (e.g. die face value, timetrack step, card side)
- **Material** — static information about the game, the "rulebook data" vs. runtime token instances
- **Owner** — player color string (e.g. `"ff0000"`), not player id — used in this game to identify players

#### Game specific

- **Hero vs. Player** — Hero is the in-game character; player is the seat. 
- **Limbo** — special location for tokens that exist but aren't on the board yet, or are "out of the game" already
- **Supply** — pool of unowned tokens available to draw/spawn from
- **Tableau** — a player's personal area where their hero card, abilities, equipment, and earned crystals live
- **Grimheim** — the town the heroes defend; a 7-hex cluster, also a location key on town pieces

### Operation-Based State Machine

The game logic is built around an operation-based state machine:

- **OpMachine** (`modules/php/OpCommon/OpMachine.php`) — Core state machine that manages operation queue and execution
- **Operation** (`modules/php/OpCommon/Operation.php`) — Abstract base class for all game operations
- **Operation implementations** (`modules/php/Operations/Op_*.php`) — Concrete operations
- **ComplexOperation** — Operations that can contain sub-operations (delegates)
- **CountableOperation** — Operations that can be repeated a specific number of times

Operations are queued, pushed or inserted in the Machine and executed sequentially based on rank, enabling complex game flows.

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
- PlayerTurnConfirm — Turn confirmation state (Non-Machine state)
- MachineHalted — Error/debug state

### Client-Side Code

TypeScript files in `src/` compile to a single `Game.js`:

- **Game.ts** — Main game class (extends GameMachine), entry point for client logic
- **Game0Basics.ts** — First file in compilation order, basic definitions
- **Game1Tokens.ts** — Token rendering and management
- **GameMachine.ts** — Client-side state machine handling
- **LaAnimations.ts** — Animation utilities
- **LaZoom.ts** — Zoom

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
- `card_event_<hero>_<n>_<i>` — Event card (e.g. `card_event_1_15_2`) — trailing `<i>` distinguishes duplicate copies of the same card
- `marker_<color>_<n>` — Player marker (e.g. `marker_ff0000_1`)
- `monster_legend_<n>_<level>` — Legend monster tile. 6 legends numbered 1–6 (1=Queen, 2=Seer, 3=Grendel, 4=Surt, 5=Hrungbald, 6=Nidhuggr). `_1` = yellow (Level I), `_2` = red (Level II) — same two-sided pattern as ability cards. `_1` starts in `supply_monster`, `_2` starts in `limbo`. When a red legend card is drawn, swap `_1` out and place `_2` on the map. Stats in `monster_material.csv`. Legends destroy 3 town pieces on entering Grimheim and can swap places with blocking monsters during movement.
- `crystal_green_<n>` — Mana crystal (individual tokens on cards). Also used as stun marker on monsters (Suppressive Fire): a green crystal on a monster means it skips its next movement and the crystal is removed.
- `crystal_yellow_<n>` — Gold/XP crystal (individual tokens on cards)
- `crystal_red_<n>` — Damage crystal (individual tokens on cards and things)
- `rune_stone` — Time track marker (singleton)

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


### Dice

The "state" of the die token is the dice value (1-6). These side values will map to sides of real die.
- `die_attack_<n>` — Attack die (1..20).
- `die_monster_<n>` — Monster die


---



## Model Classes (`modules/php/Model/`)

Domain model objects wrapping game tokens. Obtain instances via factory methods on `Game`: `getHero($owner)`, `getHeroById($heroId)`, `getMonster($monsterId)`, `getCharacter($id)`.

- **Character** — base class for anything that occupies a hex (heroes, monsters). Provides position, crystals, armor, cover, and damage-resolution helpers shared between heroes and monsters.
- **Hero** — extends Character. Adds owner (player color), tableau/hand access, attribute trackers (strength/range/move/health/hand), action markers, XP gain, and knock-out handling (move to Grimheim + destroy 2 houses).
- **Monster** — extends Character. Adds faction, health, XP reward, and kill handling (remove from map, award XP to attacker).

Faction effects are handled inside `Character` damage resolution: 
Dead faction's rune die results count as hits; Draugr have armor=1.

### Card (`modules/php/Model/Card.php`)

Wrapper around a card token, instantiated on the fly during trigger dispatch. `Op_trigger::resolve()` calls `Game::instantiateCard($card, $op)`, which picks a bespoke subclass when available and falls back to `CardGeneric` otherwise.

- **Card** — base class. Provides id/owner/state accessors, crystal counters (damage/mana/gold), trigger routing (walks the trigger chain most-specific → least-specific and calls the first matching `on<TriggerName>` hook), playability checks, and `useCard` (notify + queue the `r` expression + discard if event).
- **CardGeneric** (`modules/php/Model/CardGeneric.php`) — default class for cards driven entirely by Material data. Implements the standard voluntary trigger flow: matches the Material `on` field, validates the `r` expression has targets, and queues a deduplicated `useCard` op that offers all matching cards in one prompt.
- **Bespoke card classes** (`modules/php/Cards/Card<Type>_<Name>.php`, etc.) — per-card subclasses for cards needing custom behavior (passive effects, complex logic, multiple triggers). Class name is derived from the Material `name` field, e.g. `"Home Sewn Cape"` → `CardEquip_HomeSewnCape`. Override `on<TriggerName>()` hooks; can still delegate to the CSV `r` field via `queue($r)`.

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
- `useCard` (free) — Unified card activation: offers all playable cards (abilities, equipment, events) from tableau and hand matching the current trigger.
- `shareGold` (free) — Give gold to another hero — *notimpl*


### Card Effect Operations 

These are **generic parameterized operations** used as building blocks to implement card effects.
They are queued by `useCard` after the card is played.
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

### Cost and Predicate Operations

**Predicates** are stateless guard ops — void the chain when their condition fails:

- `on(EventXxx)` — runtime event gate (matches the trigger that fired)
- `in(Location)` — hero is on a hex matching `loc` or terrain (e.g. `Grimheim`, `forest`)
- `adj(Location)` — hero is adjacent to such a hex

**Costs** start with `spend` (e.g. `spendUse`, `spendMana`, `spendXp`). They also void on failure (e.g. `spendUse` voids if the card was already used this turn), so they double as guards.

**Placement rule**: predicates and costs must be the **leftmost** element of a paygain chain (predicates fist). `Op_paygain` pre-flights for void state, but any sub that runs before a void one has already applied its side-effects. Left-anchor to fail fast.

Examples:

- `spendUse:spendMana:gainAtt_move` — once per turn, pay 1 mana to gain +1 move
- `on(TActionAttack):2spendMana:2addDamage` — during attack, pay 2 mana for +2 damage
- `in(Grimheim):2spendXp:upgrade` — in Grimheim, pay 2 XP to upgrade


### Trigger System

Cards declare when they can be activated via the **`on`** field in Material. At key gameplay moments, an operation calls `$this->queueTrigger(Trigger::Xxx)`, which queues `Op_trigger`. The op walks every card on the active player's tableau and hand, instantiates each via `Game::instantiateCard()`, and calls `$card->onTrigger($trigger)`.

If `on` is empty, the card is a free-action play (no "once per turn" cap unless `r` includes `spendUse`). If it's `custom`, the card has a bespoke class under [modules/php/Cards/](../../modules/php/Cards/) handling its own logic. Otherwise it's a `Trigger` enum case from the table below.

**Trigger chain.** Triggers form a hierarchy: `ActionAttack` is a more specific `Roll`; `ActionMove` is a more specific `Move`. Each `Trigger` case has an optional `parent()`, and `chain()` returns `[self, parent, …]`. The chain is matched at two points:

- `CardGeneric::canTriggerEffectOn` — matches the card's `on` field against any trigger in the dispatched chain (so `on=TRoll` fires during a dispatched `ActionAttack`).
- `Op_on` — the `on(EventXxx)` predicate in `r` expressions matches the same way against the `event` data field seeded by `Card::useCard()`.

For bespoke cards with per-event hooks, `Card::onTrigger` walks most-specific → least-specific and calls the first matching `on<TriggerName>()`.

**Dispatch.** `CardGeneric::onTrigger` checks `canBePlayed`, then `promptUseCard` queues (or extends) a single dedup'd `useCard` op per trigger. All cards matching anywhere in the chain share one prompt — e.g. during an attack roll, `on=TRoll` and `on=TActionAttack` cards appear together.

**Where triggers are queued (operation → Trigger → description):**

```
Op_turnStart
  → Trigger::TurnStart        — at start of player turn (passive start-of-turn effects)
Op_roll
  → Trigger::ActionAttack     — rolls initiated by Op_actionAttack (chains through Roll)
  → Trigger::Roll             — all other hero rolls
Op_move
  → Trigger::ActionMove       — moves queued by Op_actionMove (chains through Move)
  → Trigger::Move             — all other movements (card-driven, forced moves, etc.)
Op_resolveHits
  → Trigger::ResolveHits      — before damage is applied to a hero (for damage prevention)
Op_dealDamage
  → Trigger::MonsterKilled    — when a monster is killed
Op_turnEnd
  → Trigger::TurnEnd          — at end of player turn
Op_turnMonster
  → Trigger::MonsterMove      — before the Monsters Move step
Op_gainEquip
  → Trigger::CardEnter        — when a card enters play (direct onTrigger call, not via Op_trigger)
```

**Attack action trigger sequence (example):**

```
actionAttack → player picks target → roll dice
  → Trigger::ActionAttack  — single dispatch; chains through Roll.
                             One useCard prompt offers every card whose `on`
                             is anywhere in {ActionAttack, Roll} — e.g. Bjorn
                             Hero I (on=Roll) and Trollbane (on=ActionAttack)
                             in one list.
  → resolveHits          — converts dice to damage
    → Trigger::ResolveHits — damage prevention cards offered
  → dealDamage           — applies damage to monster
    → Trigger::MonsterKilled — if monster died, each matching card reacts
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

### Card effect text

Card `effect` text in CSVs is the canonical, designer-facing description of what the card does. For cards whose **top-level rule is `Op_or`**, the `effect` column contains an HTML `<ul><li>…</li></ul>` list, with one `<li>` per OR branch. `genmat` consumes the list and emits per-choice translatable strings into `Material.php`, used by `Op_or` to label choice buttons.

**In scope**: cards whose top-level rule operator is `/` (Op_or). Examples: Flexibility I/II, Treetreader I/II, Bloodline Crystal, Home Sewn Cape.

**Out of scope (for now)**: cards with nested OR (e.g. Stitching `spendUse:(heal/repair)`, Ring of Au `spendDurab:(heal/dealDamage)`). They keep auto-derived button names until we extend markup to inner-OR cases.

#### Markup syntax

- Wrap each choice in a `<li>...</li>` tag inside the `effect` field, in OR-branch order. The surrounding `<ul>...</ul>` is recommended for client rendering but not required by the generator.
- `<li>` tags are flat — no nesting, no attributes.
- `<li>` content may include existing inline icon codes (`[MANA]`, `[DAMAGE]`, etc.) and other inline HTML.
- Text outside the `<li>` items (preamble, trailing passive sentences, connectives) is allowed and is treated as non-choice text.

The same rule applies generically to any translatable field that contains `<li>` markup: `genmat` extracts each `<li>` content as a numbered sub-field. For card effects this is OR choices; the mechanism is field-agnostic.

#### Translation

Two translatable artifacts per OR-card:

- **The full `effect` string**, including the `<ul><li>` tags. Translators translate text and keep tags intact (same convention as keeping `[MANA]` icon codes today). Used by the client to render the card description.
- **Each `<li>` content** as a separate translatable string. Used by `Op_or` for choice button labels.

Cards without a `<ul>` block (single-effect cards, non-OR rules) translate the `effect` string as one unit — no per-choice strings emitted.

#### Semantics — what goes where

- **Inside `<li>`**: short, self-contained description of one choice. Should make sense as a button label. Typically cost + outcome (e.g. `2[MANA]: Move 1 area`).
- **Outside `<ul>`**: passive text (effects that always apply, not tied to a choice), shared preamble. Translated as part of the full `effect` string; never used by the server.
- Inline-OR prose ("Move into a forest area, or move out of a forest area.") must be rewritten as a list to be in scope. We control the text.
- **Flavor text** stays in the `flavour` column. Never inside `effect`.

Trailing periods inside `<li>` are not needed in the source — the list visually delimits items. Authoring convention: omit trailing punctuation inside `<li>`. Passive sentences outside `<ul>` keep their punctuation.

#### Worked examples

Flexibility I — three choices, no non-choice text:
```
<ul><li>1[MANA]: Move +1</li><li>2[MANA]: Attack range +1 this turn</li><li>2[MANA]: Add 2 damage to your attack action</li></ul>
```

Treetreader I — rewritten as list:
```
<ul><li>Move into an adjacent forest area</li><li>Move out of a forest area</li></ul>
```

Treetreader II — list plus a trailing passive sentence:
```
<ul><li>Move into an adjacent forest area</li><li>Move out of a forest area</li></ul>Each time you move into a forest area, heal 1 damage.
```

Home Sewn Cape — passive preamble, then list:
```
Add 1 [MANA] here every time you roll a [RUNE].<ul><li>2[MANA]: Move 1 area</li><li>3[MANA]: Prevent 2 damage</li></ul>
```

#### Generated artifacts

For an OR-card with a `<ul>` block, `genmat` emits in the card's Material entry:
- `"effect" => clienttranslate("…full string with tags…"),` — full text preserved verbatim, used by the client to render the card description as a list.
- `"effect_1" => clienttranslate("1[MANA]: Move +1"),` — first choice text.
- `"effect_2" => clienttranslate("2[MANA]: Attack range +1 this turn"),`
- … one per `<li>`, indexed from 1.

Indices are **1-based** to match natural human counting ("choice 1", "choice 2"). Internal `Op_or` branch index `i` maps to `effect_{i+1}`.

For a card without a `<ul>` block:
- `"effect" => clienttranslate("…"),`
- No `effect_N` emitted.

#### Validation (genmat)

If a translatable field contains `<li`, `genmat` must find at least one closed `<li>...</li>` pair, otherwise it fails the build with a clear error.

Genmat does NOT validate that the `<li>` count matches the OR branch count in the `r` expression — that's a runtime concern (`Op_or` falls back to auto-name if `effect_$i` is missing).

## Overview of card effects

Card effects are categorized by card type (Ability, Equipment, Event) and how they interact with the action economy.

**1. Activated / Free Action Effects (Abilities & Equipment)** — used as free actions any time during your turn (outside a main action, unless the card says otherwise).

* *Once-per-turn limit*: enforced explicitly via `spendUse` in the card's `r`, not implicit. Cards without `spendUse` (damage-prevention equipment, bespoke `r=custom` classes, "any number of times" cards) are unlimited.
* *Mana-activated*: pay mana to trigger, e.g. `spendMana:addDamage`.
* *Action modifiers*: used inside another action — e.g. add damage after the attack roll.

**2. Triggered / Reactive Effects** — fire automatically or play in response to a game event, especially during the Monster Turn.

* *Damage prevention*: triggers on `TResolveHits`. These cards typically omit `spendUse`, so they fire each time you take damage.
* *Reaction events*: event cards with an `on=` trigger (e.g. `TMonsterMove`) — playable from hand during that window only.

**3. Static / Persistent Effects** — always-on modifications to base stats or rules.

* *Rule changes*: e.g. Embla moves 4 instead of 3; permanent strength bonuses from equipment on the tableau. Computed by `Hero::calcBaseStrength/Range/Move/Health` and stored in attribute trackers.
* *Mana regeneration*: end-of-turn refill, applied during `Op_turnEnd`.

**4. Instant / One-Time Effects (Events)** — drawn into hand, played from hand, discarded after resolving.

* *Immediate resolution*: play, resolve `r`, discard.
* *Flexibility*: any number per turn, outside a main action.
* *Conditional*: some events have an `on=` trigger and only play during that window — same mechanism as triggered abilities.

**5. Replacement Effects** — replace one outcome with another; rare, used for specialized hero builds.



## Assumptions (to verify with game designer)

1. **Event discard pile is face up (public)**: The rules don't specify whether the event discard pile is face up or face down. We assume face up, as is standard for card game discard piles. All players can see discarded event cards.
2. **Event deck is not reshuffled when exhausted**: The rules don't mention reshuffling the discard pile when the event deck runs out. We assume the deck simply stays empty — no auto-reshuffle.
3. **"Move X" is mandatory; "Move up to X" is optional**: "Move 2 areas" means the hero must move exactly 2 steps if possible (falling back to max reachable if blocked). "Move up to 1 area" allows the player to choose not to move. Implemented via CountableOperation: `2move` (count=2, mcount=2) vs `[0,1]move` (count=1, mcount=0).
4. **moveMonster cannot push into Grimheim**: When a player moves a monster via card effects (Kick, Swift Kick, etc.), we assume the player will not select a Grimheim hex as the destination. No `monsterEntersGrimheim` logic is implemented for player-initiated monster movement.
5. **Stitching can heal adjacent heroes but only repair own equipment**: The card says "Remove damage from any hero or equipment within range 1." We assume "within range 1" applies to heroes (can heal any adjacent hero including self), but equipment repair is limited to the acting player's own tableau.
6. **Windbite does not trigger on its own added dice**: The card says "Whenever you roll [RUNE], add another [DIE_ATTACK] to your roll for each [RUNE]." Read literally this could chain (added dice may roll runes, triggering more added dice). We assume one-shot: count runes on the initial roll, add that many dice, done. Enforced by `Op_addRoll::shouldEmitTrigger()` returning false — added dice don't re-emit `TRoll`/`TActionAttack`.

## Rule clarifications (resolved with designer)

1. **Queen of the Hill I/II — the movement is the tactic, damage is thematic**: "Deal X damage to an adjacent monster and switch places with it." Clarified by the designer: the movement is a maneuver — Embla appears behind the monster and gently pushes them down the hill, which is what deals the damage thematically. Consequences:
   - The hero **always moves** into the target hex, even if the damage kills the monster (no "stay put on kill" fallback).
   - The target hex must be one the hero could legally enter — mountain-adjacent monsters are not valid targets (unless Fleetfoot II is in play, where the hero can enter mountain areas). Enforced by `Op_c_queen::getPossibleMoves()` filtering on `HexMap::isImpassable($hex, "hero")` with `ERR_PREREQ` / "You cannot enter that terrain".

2. **Orebiter — you attack the mountain, not a monster, and it consumes your attack action**: "You may attack adjacent mountain areas. For each damage dealt, gain 1 gold [XP]." Clarified by the designer:
   - Orebiter is activated by spending the **attack action on the player board** — same slot the regular attack action uses. The target is an **adjacent mountain hex**, not a monster. You're rolling dice to hit the gold vein; every point of damage dealt becomes 1 gold [XP].
   - Because the attack action slot is consumed, you **cannot attack monsters with your attack action on the same turn**. Swift Strike (and any other free-action / triggered attack source) is still available — the restriction is on the main attack action only.
