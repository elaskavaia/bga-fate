# Plan for Implementation of game Fate: Defenders of Grimheim on BGA

This document is a plan for the implementation of the game Fate: Defenders of Grimheim on Board Game Arena (BGA). It outlines the steps and tasks required to create a digital version of the game that can be played online.

## Prepare game assets

[x] Read the rulebook of Fate: Defenders of Grimheim and create RULES.md.
[x] Assets of the game including rulebook PDF located at ~/Develop/bga/bga-assets/
[ ] Main Board (jpg) at least 2048px width
[ ] Player boards (jpg) one per hero
[ ] Cards (jpg) at least 125px width - sprite - one per hero plus monster cards one sprite for all
[ ] Miniatures (png) - sprite
[ ] Other 3d pieces and iconography (png) - sprite

## High level plan

[x] Transform templated project into typescript enabled
[x] Copy boilerplate code from another game: tokens db, machine db, common utils, etc
[ ] Phase 1: Core game framework and board setup
[ ] Phase 2: Basic player turn with one hero (reduced rules)
[ ] Phase 3: Monster system with one monster type
[ ] Phase 4: Combat and damage system
[ ] Phase 5: Equipment, quests, and upgrades
[ ] Phase 6: Full monster turn (movement, attack, reinforcements)
[ ] Phase 7: Add remaining monster types and legends
[ ] Phase 8: Add remaining heroes
[ ] Phase 9: Polish, animations, and BGA compliance
[ ] Phase 10: Testing and alpha release

---

## Phase 1: Core Game Framework and Board Setup

Goal: Get the game starting with a board, players placed in Grimheim, town pieces, and time track visible.

### 1.1 Define Game Elements in CSV / Material

[X] Create `token_material.csv` — define all token types:
  - `hero_<name>` — hero miniatures (one per hero: start with 2 heroes from 1 hero box)
  - `house_X` — 9 house tokens (X is 1 to 9)
  - `house_0` — Freyja's Well (the last town piece, losing condition)
  - `runestone` — time track marker
  - `crystal_green` (mana), `crystal_yellow` (gold/experience), `crystal_red` (damage)
  - `die_damage` — damage dice for tracking monster damage
  - `die_attack` — attack dice (20 total)
  - `die_monster` — optional monster die
  - `marker_{player_color}_X` — 3 per player (2 action markers + 1 upgrade cost marker)
  - Monster tiles: `monster_goblin`, `monster_brute`, `monster_troll`, `monster_sprite`, `monster_elemental`, `monster_jotunn`, `monster_imp`, `monster_skeleton`, `monster_draugr`, `monster_legend_<name>`
[ ] Create `card_material.csv` — define card types:
  - Monster cards: `mcard_yellow_<id>`, `mcard_red_<id>` (54 total: 36 yellow, 18 red)
  - Hero cards, ability cards, equipment cards, event cards (per hero deck)
[ ] Create `location_material.csv` — define board locations:
  - `grimheim` — single area, central town
  - Time track spots (short and long variants)
  - Monster supply spots on board
  - Player board slots: action slots (move, attack, prepare, focus, mend, practice), ability pile, equipment pile, event deck, discard, gold/experience storage
[ ] Create `map_material.csv` — define board hexes. Map is hex grid 9x9 with flat top. Use Axial Coordinates with center being 0,0
  - Named locations: Troll Caves, Nailfare, Wyrm Lair, Spewing Mountain, Dead Plains, etc.
  - Hex areas with terrain types: plains, mountain, forest, lake
  - Roads connecting areas
[ ] Run `npm run genmat` to generate Material.php sections
[ ] Define game constants in Material.php manual sections:
  - Terrain types: TERRAIN_PLAINS, TERRAIN_MOUNTAIN, TERRAIN_FOREST, TERRAIN_LAKE
  - Monster ranks: RANK_1, RANK_2, RANK_3, RANK_LEGEND
  - Monster factions: FACTION_TROLLKIN, FACTION_FIRE_HORDE, FACTION_DEAD
  - Die faces: DIE_HIT, DIE_HIT_COVER, DIE_MISS, DIE_RUNE
  - Action types: ACTION_MOVE, ACTION_ATTACK, ACTION_PREPARE, ACTION_FOCUS, ACTION_MEND, ACTION_PRACTICE

### 1.2 Board Topology (Hex Map)

[ ] Define hex grid adjacency data structure (PHP array in hex_material.csv)
  - Each hex area identified by coordinate or ID
  - Store: terrain type, location name (if any), adjacent hexes, road connections
  - Mark which areas are part of multi-area locations (Troll Caves = 3 mountain hexes, Nailfare = 2 lake hexes)
  - Define monster paths: for each edge of the board, the path arrows directing monsters toward Grimheim
  - Define road paths: where roads redirect monster movement
[ ] Implement adjacency helper functions in PHP:
  - `getAdjacentAreas($areaId)` — returns list of adjacent area IDs
  - `getAreaTerrain($areaId)` — returns terrain type
  - `isOccupied($areaId)` — checks if a character is on this area
  - `getMonsterPath($areaId)` — returns next area on path toward Grimheim
  - `getAreasInRange($areaId, $range)` — for ranged attacks
  - `isAdjacentToGrimheim($areaId)` — boundary check

### 1.3 Database and Token Setup

[ ] Update `setupNewGame()` in Game.php:
  - Create town pieces in Grimheim based on player count (1p=4, 2p=6, 3p=8, 4p=10)
  - Create rune stone on first time track spot
  - Create bonus markers: 3 red on Troll Caves area, 3 green on Nailfare area, 3 yellow on Wyrm Lair area
  - Shuffle yellow and red monster card decks (as tokens in deck locations)
  - Per player:
    - Create hero miniature in Grimheim
    - Create 3 player markers (2 action + 1 upgrade cost at position 5)
    - Set up hero card (active), starting ability (active), starting equipment (active)
    - Shuffle remaining 5 ability cards into ability pile (level I side up)
    - Shuffle remaining equipment cards into equipment pile (face up)
    - Shuffle event cards into event deck (face down)
    - Give 2 gold, 1 mana on starting ability, draw 1 event card
  - Each player draws 1 yellow monster card and places initial monsters
[ ] Add game option for time track length (short vs long) in `gameoptions.json`
[ ] Add game option for monster die (on/off) in `gameoptions.json`
[ ] Add game option for difficulty (skip step 13, extra upgrade cost) in `gameoptions.json`

### 1.4 Client-Side Board Layout

[ ] Create board HTML/CSS layout in GameXBody.ts / SCSS:
  - Main board with hex grid overlay (positioned divs or SVG for hex areas)
  - Grimheim central area (special: holds multiple heroes + town pieces)
  - Clickable hex areas with terrain-type CSS classes
  - Monster supply areas around board edges
  - Time track display (highlight current position, show reinforcement/skull markers)
[ ] Create player board HTML/CSS:
  - Action slots (move, attack, prepare, focus, mend, practice) with 2 empty slots for action markers
  - Upgrade cost track
  - Ability pile, equipment pile, event deck, discard pile areas
  - Gold/experience counter display
  - Hand area (up to 4 event cards)
[ ] Create token/piece CSS sprites:
  - Hero miniatures
  - Town pieces (houses + well)
  - Monster tiles (colored by faction)
  - Crystal tokens (green/yellow/red)
  - Player markers
  - Dice faces
[ ] Implement `getAllDatas()` to send full board state to client
[ ] Implement `setup()` in JS to render initial game state from getAllDatas

### 1.5 Tooltips and Info

[ ] Add tooltips for all board locations (terrain type, special rules)
[ ] Add tooltips for monster types (strength, health, rank, faction effect)
[ ] Add tooltips for action slots (what each action does)
[ ] Add player board tooltips (upgrade cost track explanation)

---

## Phase 2: Basic Player Turn (Reduced Rules)

Goal: Players can take 2 actions per turn (move, attack, prepare, focus, mend, practice), then end turn. Start with one hero only.

### 2.1 Turn Structure Operations

[ ] Create `Op_turn.php` — main player turn operation:
  - Player must select 2 different actions (move action markers to action slots)
  - Track which actions have been taken this turn
  - Allow free actions between/after actions
  - Enforce "cannot pick same action twice" rule
[ ] Create `Op_endOfTurn.php` — end of turn sequence:
  - Reset action markers to empty spots
  - Check for upgrade eligibility (Phase 5)
  - Add mana to cards with mana generation
  - Draw 1 card (if hand < 4, or allow discard first)
  - Allow cycling top equipment or top ability card
[ ] Update state machine flow:
  - Game starts → first player's turn
  - Player turn → 2 actions + free actions → end of turn → next player
  - After all players → monster turn (Phase 6)
  - After monster turn → advance time → check win/loss → next round

### 2.2 Action Operations

[ ] Create `Op_actionMove.php` — Move action: 
  - Hero moves up to 3 areas
  - Cannot move through occupied areas
  - Cannot move into mountains (heroes) or lakes
  - Entering Grimheim ends movement
  - Exiting Grimheim can go to any adjacent non-mountain area
  - Client: highlight reachable hexes, click to move step by step or click final destination
[ ] Create `Op_actionAttack.php` — Attack action:
  - Sum attack strength from hero + equipment + abilities
  - Select target monster within range (default range 1)
  - Roll attack dice
  - Resolve hits (check cover for forest)
  - Apply damage to monster; if killed, gain experience and remove tile
  - Allow "this attack action" event cards after dice roll
[ ] Create `Op_actionPrepare.php` — Prepare action:
  - Draw 1 event card from personal event deck
  - Hand limit is 4; allow discard to make room
[ ] Create `Op_actionFocus.php` — Focus action:
  - Add 1 mana (green) to one of player's abilities or equipment that uses mana
  - Client: show eligible cards, click to add mana
[ ] Create `Op_actionMend.php` — Mend action:
  - Remove 2 damage from hero card
  - If in Grimheim: may remove from equipment too, total 5 instead of 2
  - Client: show damage tokens that can be removed, click to select
[ ] Create `Op_actionPractice.php` — Practice action:
  - Add 1 experience (yellow) to player board

### 2.3 Free Action Operations

[ ] Create `Op_useEquipment.php` — Use Equipment free action:
  - Equipment can be used once per turn (track usage)
  - Some require damage marker placement (check durability)
  - Damage prevention equipment: can be used each time damage is received
[ ] Create `Op_useAbility.php` — Use Ability free action:
  - Abilities can be used once per turn
  - Many require spending mana from the same card
  - Damage prevention abilities: can be used each time damage is received
[ ] Create `Op_playEvent.php` — Play Event free action:
  - Play event card from hand
  - Perform effect, then discard
  - Cannot play during an action (except "this attack action" cards)
  - Some events can be played outside your turn
[ ] Create `Op_shareGold.php` — Share Gold free action:
  - Give/receive gold with adjacent hero

### 2.4 Client-Side Turn UI

[ ] Implement action selection UI:
  - Show available action slots (highlight unfilled ones)
  - Click action slot to commit an action marker
  - Show "End Turn" button after 2 actions taken
  - Show "Undo" button to reverse action selection
[ ] Implement move action UI:
  - Highlight reachable areas within 3 steps
  - Click-to-move with path visualization
  - Animate hero movement along path
[ ] Implement attack action UI:
  - Highlight attackable monsters (in range)
  - Click monster to attack
  - Show dice roll animation
  - Display hit/miss results
  - Show damage applied to monster
[ ] Implement card management UI:
  - Hand display (event cards, up to 4)
  - Click card to play (with confirmation)
  - Discard interface when at hand limit

---

## Phase 3: Monster System (One Type First)

Goal: Implement monster tokens, monster cards for spawning, and basic monster presence on the board. Start with Trollkin faction (Goblins, Brutes, Trolls).

### 3.1 Monster Token Management

[ ] Define monster properties in monster_material.csv:
  - Per monster type: faction, rank, strength, health, experience reward
  - Faction effects: Trollkin (+1 attack per adjacent trollkin), Fire Horde (range 2), Dead (runes = hits)
  - Special: Goblin moves 2, Draugr has armor
[ ] Create monster tile tokens during setup (in supply locations)
[ ] Implement monster placement from monster cards:
  - Parse card data for monster types and placement locations
  - Check placement validity (supply available, areas not occupied, legend not already in play)
  - If invalid, discard and draw new card
[ ] Client: render monster tiles on board with faction-colored styling

### 3.2 Monster Card Deck

[ ] Define monster card data (36 yellow + 18 red cards):
  - Each card specifies: location, list of monsters to place, and positions within location
  - Legend cards: special stats, effects, accompanying monsters
[ ] Implement monster card draw and resolution during reinforcement phase
[ ] Handle "draw until valid card" logic

---

## Phase 4: Combat and Damage System

Goal: Full attack resolution for heroes attacking monsters and monsters attacking heroes, including dice, cover, and knocked out heroes.

### 4.1 Dice System

[ ] Implement attack dice roller (PHP):
  - 20 attack dice with faces: Hit, Hit-with-cover, Miss, Rune
  - Determine probabilities/face distribution from game components
  - Roll N dice, return array of results
[ ] Implement dice resolution:
  - Count hits (Hit always counts; Hit-with-cover counts unless defender in forest)
  - Rune: miss by default, but some effects trigger on runes
  - Apply card effects that modify dice ("this attack action" events)
[ ] Client: dice roll animation with result display

### 4.2 Hero Attacks Monster

[ ] Calculate total attack strength:
  - Hero card base strength
  - + equipment strength bonuses
  - + ability strength bonuses
  - + temporary modifiers from events/effects
[ ] Apply damage to monster:
  - Track damage on monster tile with damage dice
  - If damage >= health: monster killed
  - Gain experience = monster's rank value (1/2/3)
  - Remove monster tile, return to supply
  - Legends: gain experience per legend card
[ ] Handle Draugr armor (prevent 1 damage each time)

### 4.3 Monster Attacks Hero

[ ] Calculate monster attack strength:
  - Base strength from monster type
  - Trollkin bonus: +1 per adjacent trollkin
  - Monster die bonus: +1 if monster die shows "Attack"
[ ] Apply damage to hero:
  - Place damage (red) on hero card
  - Allow damage prevention effects (equipment/abilities)
  - If damage >= health: hero is knocked out
[ ] Knocked out hero handling:
  - Move hero to Grimheim
  - Set damage to exactly 5
  - Remove 2 town pieces from Grimheim
  - Check loss condition (Freyja's Well removed)
[ ] Fire Horde range 2 attacks
[ ] Dead faction: runes count as hits when Dead monsters attack

### 4.4 Line of Sight

[ ] Implement range checking:
  - Default range 1 (adjacent)
  - Fire Horde range 2
  - Cannot shoot "over" Grimheim — count around borders
  - No other line-of-sight blocking (can target behind mountains/characters)

---

## Phase 5: Equipment, Quests, and Upgrades

Goal: Implement the quest/equipment system and hero upgrade mechanics.

### 5.1 Quest System

[ ] Define quest requirements per equipment card in material data:
  - Quest text parsing or structured data (e.g., "spend mend action on Nailfare")
  - Quest types: spend action at location, kill monster, collect resources, etc.
[ ] Implement quest progress tracking:
  - Use tokens/state to track partial quest completion
  - "Spend" action mechanic: take action without normal effect
[ ] Implement quest completion:
  - Move equipment card from pile to active cards
  - Reveal new equipment card / new quest
  - Handle Main Weapon replacement rule (only 1 main weapon; new covers old)

### 5.2 Equipment Card Effects

[ ] Implement equipment activation (free action):
  - Once per turn usage tracking
  - Durability system (damage markers on equipment)
  - Various effect types: draw cards, gain resources, deal damage, prevent damage, modify attacks
[ ] Implement equipment damage prevention (can be used each time damage received, separate from once-per-turn)

### 5.3 Upgrade System

[ ] Implement upgrade cost track:
  - Costs: 5, 6, 7, 8, 9, 10, 10, 10... (track on player board)
  - At end of turn, if player has enough experience: may upgrade
  - Only one upgrade per turn
[ ] Implement upgrade choices:
  - A) Gain new ability: move top ability from pile to active cards
  - B) Improve existing card: flip ability or hero card to upgraded (level II) side
[ ] Mana generation on new abilities triggers immediately upon upgrade

### 5.4 Ability Cards

[ ] Define ability card data per hero in material:
  - Level I and Level II sides
  - Effects, mana costs, mana generation values
  - Starting ability identification
[ ] Implement ability activation (free action):
  - Once per turn usage tracking
  - Mana spending from the same card
  - Various effect types per hero
[ ] Implement mana generation at end of turn (all cards with green icon generate mana to themselves)

---

## Phase 6: Full Monster Turn

Goal: Complete monster turn sequence — time track advance, monster movement along paths, monster attacks, reinforcements.

### 6.1 Time Track

[ ] Implement time track advancement:
  - Move rune stone one step
  - Check spot type: normal, reinforcement (crossed axes), skull (charge), or final spot
  - Two track variants (short 30min/player, long 40min/player)
[ ] Win condition: if Freyja's Well remains after last turn on time track
[ ] Loss condition: if all town pieces (including Well) removed at any point

### 6.2 Monster Movement

[ ] Implement monster path following:
  - Arrows at board edges define initial direction
  - Follow arrows until reaching a road, then follow road to Grimheim
  - Move order: closest to Grimheim first, farthest last
  - If 2 monsters would enter same road area: one already on road moves first
[ ] Movement rules:
  - Monsters adjacent to heroes do NOT move
  - Monsters cannot move into occupied areas (except legends can swap)
  - Normal movement: 1 area per turn
  - Goblins: move 2 areas instead of 1
[ ] Charge rules:
  - On skull turns: all monsters charge (move 1 additional area)
  - Monster die "Charge" result: rank 1 monsters charge
  - If monster can't attack after normal move but could by charging: it charges
[ ] Legend movement: swap places with monsters blocking their path
[ ] Monster entering Grimheim: remove monster + 1 town piece (legend = 3 town pieces)

### 6.3 Monster Attacks

[ ] After movement, all monsters adjacent to heroes attack:
  - Players choose which hero is attacked if monster adjacent to multiple
  - Apply monster attack strength + faction bonuses
  - Resolve damage to hero (see Phase 4)
[ ] Attack order chosen by players

### 6.4 Reinforcements

[ ] On reinforcement spots (yellow border = yellow cards, red border = red cards):
  - Each player draws 1 monster card from appropriate deck
  - Place monsters on board per card instructions
  - Handle invalid placements (draw new card)

### 6.5 Monster Die (Optional Variant)

[ ] Implement monster die with 6 faces:
  - Maneuver clockwise: monsters rotate around adjacent heroes
  - Maneuver counter-clockwise: same but other direction
  - Attack +1: all monsters get +1 strength this turn
  - Push: heroes adjacent to monsters pushed 1 area toward Grimheim
  - Charge: all rank 1 monsters charge
  - Ambush: each hero (not in Grimheim) places 1 goblin adjacent to self

---

## Phase 7: Add Remaining Monster Types and Legends

Goal: Implement all monster factions and legend monsters.

### 7.1 Fire Horde Faction

[ ] Add Sprite (rank 1: str 1, hp 2), Elemental (rank 2: str 3, hp 4), Jotunn (rank 3: str 5, hp 6)
[ ] Implement faction effect: attack range 2 for all fire horde monsters

### 7.2 Dead Faction

[ ] Add Imp (rank 1: str 2, hp 2), Skeleton (rank 2: str 4, hp 3), Draugr (rank 3: str 6, hp 5)
[ ] Implement faction effect: runes count as hits when Dead attack
[ ] Implement Draugr armor: prevent 1 damage each time dealt damage

### 7.3 Trollkin Faction (already started in Phase 3)

[ ] Verify: Goblin (rank 1: str 1, hp 2, move 2), Brute (rank 2: str 3, hp 3), Troll (rank 3: str 6, hp 7)
[ ] Verify faction effect: +1 attack per adjacent trollkin

### 7.4 Legend Monsters

[ ] Add 6 legends with yellow/red level sides:
  - Grendel, Nidhuggr, Surt, Queen of the Dead, Hrungbald, Seer of Odin
[ ] Implement per-legend: unique stats, effects, reward experience
[ ] Implement legend rules: destroy 3 town pieces on entering Grimheim, swap with monsters in path
[ ] Legend cards: define monster card data for each legend (accompanying monsters and placement)

### 7.5 All Monster Cards

[ ] Define all 36 yellow monster cards (location + monster placement data)
[ ] Define all 18 red monster cards (location + monster placement data, includes legends)

---

## Phase 8: Add Remaining Heroes

Goal: Implement all 4 hero decks with their unique cards and abilities.

### 8.1 First Hero (pick simplest)

[ ] Define hero card data (level I + II): attack strength, health, effect
[ ] Define 6 ability cards (level I + II each): effects, mana costs, mana generation
[ ] Define ~10 equipment cards: quests, effects, strength bonus, durability
[ ] Define ~23 event cards: effects
[ ] Implement all card effects as operations or effect handlers
[ ] Test hero thoroughly

### 8.2 Second Hero

[ ] Repeat card definitions and effect implementations for hero 2
[ ] Test hero 2

### 8.3 Third and Fourth Heroes

[ ] Repeat for heroes 3 and 4
[ ] Test all heroes in combination

---

## Phase 9: Polish, Animations, and BGA Compliance

Goal: Make the game look good, play smoothly, and meet BGA publishing requirements.

### 9.1 Animations

[ ] Hero movement animation (smooth path following)
[ ] Monster movement animation (path following, charging)
[ ] Dice roll animation (3D or sprite-based)
[ ] Card draw/play/discard animations
[ ] Damage token placement/removal animations
[ ] Monster spawn animation
[ ] Town piece destruction animation
[ ] Mana/gold/experience gain animations

### 9.2 Game Log

[ ] Comprehensive game log entries for all actions:
  - Player actions (move, attack results, prepare, focus, mend, practice)
  - Free actions (equipment use, ability use, event play)
  - Monster movement, attacks, damage
  - Reinforcements (which monsters appeared where)
  - Quest completion, upgrades
  - Town pieces destroyed, heroes knocked out
  - Win/loss conditions
[ ] Include card names and relevant values in log messages
[ ] All log strings marked for translation

### 9.3 UI Polish

[ ] Responsive layout for different screen sizes
[ ] Mini player boards in the BGA player panel area
[ ] Card zoom on hover
[ ] Clear visual indicators for:
  - Current player's turn
  - Available actions
  - Attackable targets
  - Monster threat level (adjacent to Grimheim)
  - Quest progress
  - Time track progression
[ ] Help mode / rule reminders

### 9.4 BGA Compliance

[ ] Implement `getGameProgression()` — based on time track position
[ ] Implement zombie mode (`zombieTurn()`) — auto-pass for disconnected players
[ ] Define game statistics in `stats.json`:
  - Monsters killed (total, per type/rank)
  - Damage dealt / received
  - Cards played
  - Equipment acquired
  - Upgrades performed
  - Town pieces lost
  - Turns survived
[ ] Implement tiebreaking (cooperative game — win/loss only, but track contribution stats)
[ ] Ensure all UI strings use translation functions
[ ] Add tooltips to all interactive elements
[ ] Verify hand-limit enforcement and all edge cases

---

## Phase 10: Testing and Alpha Release

### 10.1 Automated Tests

[ ] Unit tests for board topology / adjacency
[ ] Unit tests for monster path calculation
[ ] Unit tests for dice probability / combat resolution
[ ] Unit tests for quest completion conditions
[ ] Unit tests for upgrade cost calculation
[ ] Integration tests for full turn cycle (player turn → monster turn → reinforcements)
[ ] Integration tests for win/loss conditions
[ ] Integration tests for knocked out hero handling
[ ] Test with 1, 2, 3, and 4 players

### 10.2 Manual Testing

[ ] Play through complete game with short time track
[ ] Play through complete game with long time track
[ ] Test all hero decks individually
[ ] Test hero combinations
[ ] Test monster die variant
[ ] Test edge cases:
  - All town pieces destroyed (loss)
  - Hero knocked out multiple times
  - Multiple monsters entering Grimheim same turn
  - Legend monster interactions
  - Equipment pile exhausted
  - Event deck exhausted (shuffle discard)
  - Multiple heroes in Grimheim
  - Charge + movement blocking scenarios

### 10.3 Pre-Alpha Checklist

[ ] Run `npm run build` with no errors
[ ] Run `npm run tests` with all passing
[ ] Verify game starts correctly for all player counts
[ ] Verify game can be completed (win and loss scenarios)
[ ] Check spelling in all translatable strings
[ ] Review code for security issues (input validation on all actions)
[ ] Check that no game state information leaks to wrong players (event cards in hand are private)
[ ] Verify mobile/tablet layout works reasonably
[ ] Submit for BGA Alpha review

---

## Reduced-Rules First Iteration

To keep development manageable per WALKTHROUGH.wiki guidance, the **first playable version** should include:

1. **1 hero only** (pick the simplest deck)
2. **1 monster faction only** (Trollkin: Goblins + Brutes only, no Trolls initially)
3. **Short time track only**
4. **No monster die**
5. **No legends**
6. **Simplified quest system** (only 2-3 equipment cards)
7. **2 players** (fixed count, easiest to test)
8. **No damage prevention effects** (simplify combat)
9. **Basic animations only** (snap-to-position, no smooth movement)

This gets a functional game loop running: player turns → monster spawns → monster moves → monster attacks → repeat until win/loss. Then expand from there.

---

## Token Naming Convention

Following the project's token naming pattern (`key = supertype_type_instance`):

| Token | Key Pattern | Example |
|-------|------------|---------|
| Hero miniature | `hero_<heroname>` | `hero_bjorn` |
| Town piece | `town_house_<n>` / `town_well` | `town_house_3`, `town_well` |
| Monster tile | `monster_<type>_<n>` | `monster_goblin_5`, `monster_troll_2` |
| Monster card | `mcard_<color>_<n>` | `mcard_yellow_12`, `mcard_red_5` |
| Hero card | `hcard_<hero>` | `hcard_bjorn` |
| Ability card | `ability_<hero>_<n>` | `ability_bjorn_3` |
| Equipment card | `equip_<hero>_<n>` | `equip_bjorn_7` |
| Event card | `event_<hero>_<n>` | `event_bjorn_15` |
| Player marker | `pmarker_<color>_<n>` | `pmarker_ff0000_1` |
| Crystal (mana) | `mana_<owner>_<n>` | `mana_card_ability_bjorn_3_1` |
| Crystal (gold/xp) | `gold_<player>_<n>` | counter, not individual tokens |
| Crystal (damage) | `damage_<target>_<n>` | counter on hero/equipment card |
| Rune stone | `rune_stone` | `rune_stone` |
| Attack die | `adie_<n>` | `adie_1` through `adie_20` |
| Damage die | `ddie_<n>` | `ddie_1` through `ddie_8` |

**Location naming:**
| Location | Key Pattern | Example |
|----------|------------|---------|
| Board hex area | `area_<id>` | `area_23` |
| Grimheim | `grimheim` | `grimheim` |
| Monster supply | `supply_<type>` | `supply_goblin` |
| Player hand | `hand_<color>` | `hand_ff0000` |
| Player board | `pboard_<color>` | `pboard_ff0000` |
| Active cards | `active_<color>` | `active_ff0000` |
| Ability pile | `abilitypile_<color>` | `abilitypile_ff0000` |
| Equipment pile | `equippile_<color>` | `equippile_ff0000` |
| Event deck | `eventdeck_<color>` | `eventdeck_ff0000` |
| Discard pile | `discard_<color>` | `discard_ff0000` |
| Monster card deck | `mcardeck_yellow` / `mcardeck_red` | `mcardeck_yellow` |
| Time track spot | `timetrack_<variant>_<n>` | `timetrack_short_5` |

---

## Key Technical Decisions

1. **Cooperative game**: All players win or lose together. No hidden scoring. The `is_coop: 1` flag is already set in gameinfos.
2. **Player order**: Fixed order, chosen at start. All players act each round, then monsters act.
3. **Hex board representation**: Store adjacency as a PHP array (or JSON data file). Do not try to compute hex math — the board is irregular with named locations.
4. **Monster movement**: Pre-compute paths from each board edge to Grimheim (following arrows + roads). Store as lookup table.
5. **Crystals as counters**: Gold/experience and mana are tracked as integer counters on tokens (player board, cards), not as individual crystal tokens. Use `token_state` for counters.
6. **Damage as counters**: Damage on heroes/monsters tracked as `token_state` integer on the target token, not as individual damage markers.
7. **Card effects**: Implement as operations. Each unique card effect gets an operation class or a parameterized generic operation.
8. **Undo support**: Use existing DbMultiUndo infrastructure. Allow undo within a turn (before confirming end of turn).
9. **Monster AI**: Fully deterministic (no choices for monsters), so monster turn can be auto-resolved on server. Client just animates notifications.
10. **Event deck exhaustion**: When event deck is empty, shuffle discard pile to form new deck (standard card game rule, verify with actual rules).
