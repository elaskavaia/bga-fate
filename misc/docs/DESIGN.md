
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

---

## Operation Catalog

Operations are the building blocks of game flow. They are queued in the state machine and dispatched in order.

Kinds: `auto` = server-resolves without player input; `player` = waits for player choice; `main` = counts as 1 of 2 actions per turn; `free` = doesn't consume a main action slot.

### Framework Operations (built-in)

- `nop` (auto) — No-op placeholder
- `savepoint` (auto) — Marks an undo savepoint
- `or` (player) — Player picks one branch from multiple choices
- `order` (player) — Player picks execution order for a set of ops
- `seq` (auto) — Runs sub-operations in sequence


### Game Operations

- `turn` (player) — Main player turn: pick 2 actions + free actions — *implemented*
- `turnconf` (player) — Confirm end of turn (undo checkpoint) — *stub*
- `turnEnd` (auto) — Reset turn state, queue next player or monsterTurn — *implemented*
- `turnMonster` (auto) — Advance time track; check win/loss; queue next round — *implemented*
- `actionMove` (main) — Hero moves up to 3 hexes — *implemented*
- `actionAttack` (main) — Hero attacks monster within range — *implemented*
- `actionPrepare` (main) — Draw 1 event card — *implemented*
- `actionFocus` (main) — Add 1 mana to a card — *implemented*
- `actionMend` (main) — Remove 2 damage from hero (5 in Grimheim, may target equipment cards too) — *implemented*
- `actionPractice` (main) — Gain 1 XP (yellow crystal) — *implemented*
- `useEquipment` (free) — Activate an equipment card — *notimpl*
- `useAbility` (free) — Activate an ability card (costs mana) — *notimpl*
- `playEvent` (free) — Play an event card from hand — *stub: logs effect, no execution*
- `shareGold` (free) — Give gold to another hero — *notimpl*


### Card Effect Operations (building blocks for card effects)

These are **generic parameterized operations** used as building blocks to implement card effects.
They are queued by `playEvent`/`useEquipment`/`useAbility` after the card is played.
Most are Countable (X = count) and take a target that is either pre-seeded or player-chosen.

**Targeting**: target can be pre-set when queuing (e.g. "self") or left for the player to pick
from a filtered set. Common target filters:
- `self` — the acting hero
- `adj` — adjacent character (monster or hero depending on action content, including self)
- `range` — monster within hero's attack range
- `range2`, `range3` — monster within fixed range N (not hero's attack range)
- `any` — any card on hero's tableau
- `equip` — equipment card on hero's tableau

**Filter conditions** (quoted, appended to target): `'rank<=2'`, `'hp<=2'`, `'rank3+legend'`

**Chaining**: `;` separates multiple operations from one card, e.g. `dealDamage(adj);1moveMonster`

**Costs**: `cost:effect` notation for activated effects:
- `XspendMana:effect` — spend X mana from this card to perform effect, e.g. `3spendMana:3dealDamage(inRange)`
- `gainDamage:effect` — spend 1 durability (take [DAMAGE] on card) to perform effect, e.g. `gainDamage:1preventDamage`
- Multiple options separated by `/`: `(1spendMana:1moveHero)/(2spendMana:2dealDamage(adj))`

**`on` column** — timing trigger for when the card can be played:
- (empty) — play anytime during your turn (once per turn; tracked via token state=1, reset in turnEnd)
- `actionAttack` — play during/after an attack action
- `roll` — play after a dice roll
- `damage` — play when receiving damage
- `monsterMove` — play *before* the Monsters Move step (fires per player, allows Suppressive Fire)
- `monsterAttack` — play after a monster attacks you

- `damage X target` (Countable) — Deal X damage to target character (no dice).
  Used by: Kick, Courage, Lightning Bolt, Rain of Fire, etc.
- `heal X target` (Countable) — Remove X damage from target hero.
  Used by: Rest, Belt of Youth, Healing Potion, etc.
- `roll X target` (Countable) — Roll X attack dice against target (uses existing dice/combat system).
  Used by: Snipe, Hard Rock, Chain Lightning, Heat Stroke, etc.
- `moveHero X` (Countable) — Move hero up to X areas (subset of actionMove logic).
  Used by: Agility, Maneuver, Fleetfoot, Blown Away
- `moveMonster X target` (Countable, player) — Move target monster X areas. Player selects monster, then destination hex.
  Param: range filter (adj/inRange). Assumption: player will not select Grimheim as destination (no monsterEntersGrimheim logic).
  Used by: Kick, Swift Kick, Bowling
- `killMonster target` (player) — Kill target monster (with filter: rank, health, range).
  Used by: Back Down, Short Temper, Heat Death, In Charge
- `gainXp X(condition)` (auto) — Gain X gold/XP (move yellow crystals to tableau).
  Optional condition: `grimheim` (hero in Grimheim), `adjMountain` (hero adjacent to mountain).
  Used by: Miner (`2gainXp(adjMountain)`), Popular (`2gainXp(grimheim)`), Discipline, actionPractice
- `gainMana X target` (player) — Add X mana to target card.
  Used by: Power Surge, Elementary Student, Focus event
- `spendMana X source` (player) — Remove X mana from source card (cost/prerequisite).
  Used by: precondition for mana-activated abilities
- `drawEvent X` (Countable, confirm) — Draw X event cards. If hand is at limit, prompts discard first.
  Param: `max` — draw until hand is full (no discard prompt).
  Used by: actionPrepare (drawEvent), Starsong (2drawEvent), Preparations (drawEvent(max))
- `spendAction type` (auto) — Consume a main action slot without performing the action.
  Param(0): action type to spend (e.g. "actionPrepare").
  Used by: event cards that cost an action (e.g. Preparations)
- `addTownPiece` (auto) — Add 1 Town Piece back to Grimheim (returns a destroyed house to its hex).
  Used by: Inspire Defense (`2spendMana(grimheim):addTownPiece`)
- `preventDamage X` (auto) — Prevent up to X damage in current attack.
  Used by: Dodge, Stoneskin, Riposte, Dreadnought
- `repairCard X target` (Countable) — Remove up to X damage from target card on tableau.
  Use `99repairCard` for "remove all damage" (99 is effectively unlimited).
  Used by: Durability (99repairCard), Mend in Grimheim (5repairCard)
- `performAction type` (auto) — Queue an additional main action (attack/mend/focus/prepare/practice).
  Used by: Speedy Attack, Rapid Strike, Sophisticated, Trinket
- `monsterMoveAll` (auto) — Move all monsters toward Grimheim. Extracted from `turnMonster` to allow
  pre-movement triggers (e.g. Suppressive Fire). Monsters with a green crystal (stunned) skip movement;
  crystal stays on the monster until next `suppressiveFire` trigger. Data field: `charge` (bool) for skull turn bonus step.
- `suppressiveFire` (player, triggered) — Prevent a monster within range 3 from moving this monster turn.
  Triggered via `useAbility` on `monsterMove`. Places a green crystal on the monster (stun marker).
  Param(0): optional filter expression (e.g. `'rank<=2'` for Level I). Monsters with an existing green
  crystal are excluded from selection ("cannot choose same monster next turn"). On resolve, any existing
  stun crystal is moved to the new target. On skip, the old crystal is removed.

**Not separate operations** (handled as modifiers/hooks on existing operations):
- "Add X damage to this attack" — modifier applied in `actionAttack` resolve
- "Attack range +X this turn" — temporary attribute modifier via tracker (see Hero Attribute Trackers below)
- "Reroll all misses" — modifier on dice result in `actionAttack`
- "Add damage for each [RUNE]" — modifier on dice result
- "Prevent monster from moving" — green crystal placed on monster by `suppressiveFire`, checked by `monsterMoveAll` (crystal stays until next trigger)
- Static/persistent effects (strength bonus, armor, mana regen) — read from card data during relevant ops
- Equipment [DAMAGE] effects — consume durability, separate activation system
- Quest completion — specific quest logic, not a generic operation

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

[1] [https://www.youtube.com](https://www.youtube.com/watch?v=_6XfamMLPQ8&t=2)
[2] RULES
[3] [https://www.youtube.com](https://www.youtube.com/watch?v=ZPvuN_9b9L8&t=16)
[4] [https://www.youtube.com](https://www.youtube.com/watch?v=v-2_QiLx9y8&t=540)
[5] [https://boardgamegeek.com](https://boardgamegeek.com/thread/3650916/abilities)
[6] [https://boardgamegeek.com](https://boardgamegeek.com/thread/3556712/timing-of-attack-rolls-and-abilities-and-more-ques)
[7] [https://boardgamegeek.com](https://boardgamegeek.com/thread/3636742/questions-after-1st-play)
[8] RULES
[9] [https://boardgamegeek.com](https://boardgamegeek.com/thread/3650916/abilities)
[10] [https://boardgamegeek.com](https://boardgamegeek.com/thread/3541771/2-newbie-questions-on-abilities)
[11] [https://boardgamegeek.com](https://boardgamegeek.com/thread/3497389/move-towards-grimheim-plus-end-movement-question)



## Assumptions (to verify with game designer)

1. **Event discard pile is face up (public)**: The rules don't specify whether the event discard pile is face up or face down. We assume face up, as is standard for card game discard piles. All players can see discarded event cards.
2. **Event deck is not reshuffled when exhausted**: The rules don't mention reshuffling the discard pile when the event deck runs out. We assume the deck simply stays empty — no auto-reshuffle.
3. **"Move X" is mandatory; "Move up to X" is optional**: "Move 2 areas" means the hero must move exactly 2 steps if possible (falling back to max reachable if blocked). "Move up to 1 area" allows the player to choose not to move. Implemented via CountableOperation: `2moveHero` (count=2, mcount=2) vs `[0,1]moveHero` (count=1, mcount=0).
4. **moveMonster cannot push into Grimheim**: When a player moves a monster via card effects (Kick, Swift Kick, etc.), we assume the player will not select a Grimheim hex as the destination. No `monsterEntersGrimheim` logic is implemented for player-initiated monster movement.
5. **Stitching can heal adjacent heroes but only repair own equipment**: The card says "Remove damage from any hero or equipment within range 1." We assume "within range 1" applies to heroes (can heal any adjacent hero including self), but equipment repair is limited to the acting player's own tableau.
