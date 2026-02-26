
## Token Naming Convention

Following the project's token naming pattern (`key = supertype_type_instance`):

- `hero_<hero>` — Hero miniature (e.g. `hero_1` = Bjorn)
- `house_<n>` — Town piece (e.g. `house_0` = well)
- `monster_<type>_<n>` — Monster tile (e.g. `monster_goblin_5`, `monster_troll_2`)
- `card_monster_<color>_<n>` — Monster card (e.g. `card_monster_yellow_12`)
- `card_hero_<hero>` — Hero card (e.g. `card_hero_1`)
- `card_ability_<hero>_<n>` — Ability card (e.g. `card_ability_1_3`)
- `card_equip_<hero>_<n>` — Equipment card (e.g. `card_equip_1_7`)
- `card_event_<hero>_<n>` — Event card (e.g. `card_event_1_15`)
- `marker_<color>_<n>` — Player marker (e.g. `marker_ff0000_1`)
- `crystal_green_<n>` — Mana crystal (individual tokens on cards)
- `crystal_yellow_<n>` — Gold/XP crystal (individual tokens on cards)
- `crystal_red_<n>` — Damage crystal (individual tokens on cards and things)
- `rune_stone` — Time track marker (singleton)
- `die_attack_<n>` — Attack die (1..20)
- `die_damage_<n>` — Damage die (1..8)
- `die_monster_<n>` — Monster die

**Location naming:**

- `hex_<x>_<y>` — Board hex area (e.g. `hex_9_9` = Grimheim)
- `supply` — Monster supply
- `hand_<color>` — Player hand (e.g. `hand_ff0000`)
- `tableau_<color>` — Player board (e.g. `tableau_ff0000`)
- `cards_<color>` — Active cards area
- `deck_ability_<color>` — Ability pile
- `deck_equip_<color>` — Equipment pile
- `deck_event_<color>` — Event deck
- `discard_<color>` — Discard pile (? need per type?)
- `deck_monster_yellow` / `deck_monster_red` — Monster card decks
- `timetrack_<n>` — Time track step slot; actual step tracked as `token_state` on `rune_stone`

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
- `gain` (auto) — Awards resources/tokens to a player
- `pay` (auto) — Spends resources from a player
- `paygain` (auto) — Trade: pay one resource type, gain another

### Game Operations

- `turn` (player) — Main player turn: pick 2 actions + free actions — *implemented*
- `turnconf` (player) — Confirm end of turn (undo checkpoint) — *stub*
- `turnEnd` (auto) — Reset turn state, queue next player or monsterTurn — *implemented*
- `turnMonster` (auto) — Advance time track; check win/loss; queue next round — *implemented*
- `actionMove` (main) — Hero moves up to 3 hexes — *stub*
- `actionAttack` (main) — Hero attacks adjacent monster — *notimpl*
- `actionPrepare` (main) — Draw 1 event card — *notimpl*
- `actionFocus` (main) — Add 1 mana to a card — *notimpl*
- `actionMend` (main) — Remove 2 damage from hero — *notimpl*
- `actionPractice` (main) — Gain 1 XP (yellow crystal) — *implemented*
- `useEquipment` (free) — Activate an equipment card — *notimpl*
- `useAbility` (free) — Activate an ability card (costs mana) — *notimpl*
- `playEvent` (free) — Play an event card from hand — *notimpl*
- `shareGold` (free) — Give gold to another hero — *notimpl*

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


- `supply_dice` — Attack and damage dice pool
- `supply_crystal_green` / `supply_crystal_red` / `supply_crystal_yellow` — Crystal supply pools
- `supply_monster` — Undeployed monster tiles

- `deck_monster_yellow` / `deck_monster_red` — Monster card draw piles

- `oversurface` — Transparent overlay for phantom token animations (pointer-events: none)
- `limbo` — Off-screen sink for tokens not yet placed

---

## Key Technical Decisions

1. **Cooperative game**: All players win or lose together. No hidden scoring. The `is_coop: 1` flag is already set in gameinfos.
2. **Player order**: Fixed order, chosen at start. All players act each round, then monsters act.
3. **Hex board representation**: Store adjacency as a PHP array (or JSON data file). Do not try to compute hex math — the board is irregular with named locations.
4. **Monster movement**: Pre-compute paths from each board edge to Grimheim (following arrows + roads). Store as lookup table.
5. **Crystals as counters**: Gold/experience and mana are tracked as individual crystal tokens.
6. **Damage as counters**: Damage on heroes/monsters tracked as `token_state` integer on die_damage (which is on character).
7. **Card effects**: Implement as operations. Each unique card effect gets an operation class or a parameterized generic operation.
8. **Undo support**: Use existing DbMultiUndo infrastructure. Allow undo within a turn (before confirming end of turn).
9. **Monster AI**: Fully deterministic (no choices for monsters), so monster turn can be auto-resolved on server. Client just animates notifications.
10. **Event deck exhaustion**: When event deck is empty, shuffle discard pile to form new deck (standard card game rule, verify with actual rules).
