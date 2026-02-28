# Plan for Implementation of game Fate: Defenders of Grimheim on BGA

This document is a plan for the implementation of the game Fate: Defenders of Grimheim on Board Game Arena (BGA). It outlines the steps and tasks required to create a digital version of the game that can be played online.

## Documents

See misc/DESIGN.md for preliminary design
See misc/RULES.md for game rules
See CLAUDE.md for project overview


## Prepare game assets

[x] Read the rulebook of Fate: Defenders of Grimheim and create RULES.md.
[x] Assets of the game including rulebook PDF located at ~/Develop/bga/bga-assets/
[x] Main Board (jpg) — img/EN_Game_Board.jpg
[ ] Player boards (jpg) one per hero
[~] Cards (jpg) — monster cards sprite exists (img/EN_Monster_Cards.jpg), hero cards TODO
[~] Miniatures (png) — hero sprites (img/mini_heroes.png), house sprites (img/mini_houses.png), monster sprites TODO
[ ] Other 3d pieces and iconography (png) - sprite (crystals, dice, etc.)

## High level plan

[x] Transform templated project into typescript enabled
[x] Copy boilerplate code from another game: tokens db, machine db, common utils, etc
[x] Phase 1: Core game framework and board setup
[x] Phase 2: Basic player turn with one hero (reduced rules)
[~] Phase 3: Monster system with one monster type (spawning done, movement TODO)
[ ] Phase 4: Combat and damage system
[ ] Phase 5: Equipment, quests, and upgrades
[ ] Phase 6: Full monster turn (movement, attack, reinforcements)
[x] Phase 7: Add remaining monster types and legends (all 3 factions done in Iter 2)
[ ] Phase 8: Add remaining heroes
[ ] Phase 9: Polish, animations, and BGA compliance
[ ] Phase 10: Testing and alpha release

---

## Iteration 0: Skeleton Game Loop (No Real Rules)

**Goal**: Game starts, shows board with map, each player has a hero on the board. Player can do "practice" (gain 1 XP) or "move" (click hex to move hero). Then monster turn just advances time track. Game ends when time track runs out. **Playable end-to-end on BGA.**

**What already exists**: hex map rendering, token framework, Op_turn scaffold, Op_actionMove/Op_actionPractice stubs, state machine wiring.

### Server
[x] `setupNewGame()`: create 1 hero per player in Grimheim, create rune stone on time track position 1, create town pieces (houses) in Grimheim
[x] `Op_actionPractice`: implement — gain 1 XP (increment player counter)
[x] `Op_actionMove`: implement — player picks a hex, hero moves there (no validation yet beyond "is it a hex on the map")
[x] `Op_turnEnd`: reset, schedule next player or when everybody did one turn - schedule turnMonster
[x] `Op_turnMonster`: advance rune stone on time track by 1 step, Win/loss check: if time track reaches end → win. (Loss condition deferred)
[x] `getAllDatas()`: should work

### Client
[X] Show hero tokens on map at their hex positions
[X] Practice action: button that sends action to server, updates XP counter
[X] Move action: click hex → send target hex to server → animate hero move
[x] Show time track position (rune stone counter or simple text)


### Validation
[x] Deploy to BGA studio, start a 1-player game, take turns, game ends

---

## Iteration 1: Basic Movement Rules

**Goal**: Movement follows actual rules — up to 3 steps, can't enter lakes/mountains (for heroes), can't pass through occupied hexes, entering Grimheim ends movement.

### Server

[x] Implement adjacency helper functions in Game PHP:
  - `getAdjacentHex($hexId)` — returns list of adjacent area IDs (hex id is hex_9_9)
  - `getHexTerrain($hexId)` — returns terrain type
  - `isOccupied($hexId)` — checks if a character is on this area
  - `getMoveDistance($hexId, $otherHexId)` - distance beetwin hexes, if adjecent its 1, if one of them invalid its -1

[x] Movement validation: check terrain, occupied hexes, max 3 steps
[x] Grimheim special rules: entering ends movement, exiting can go to any adjacent non-mountain hex
[x] Reachability calculation: given hero position, return set of valid destination hexes (`getReachableHexes()`)

### Client
[x] Highlight reachable hexes when move action is active
[ ] Show path preview - SKIP FOR NOW

### Tests
[x] PHP unit tests for movement validation and reachability

---

## Iteration 2: Monsters on the Board (Static)

**Goal**: Monsters appear on the board. No movement or combat yet — just spawning from monster cards during reinforcement.

### Server
[x] Define monster tokens in material (goblins only to start — Trollkin rank 1) — already in monster_material.csv
[x] Monster card data: define a few yellow monster cards with goblin placement
[ ] Fix spawn locations in monster cards — current data is not correct
[x] `Op_reinforcement`: draw monster card, place goblins at specified locations
[x] Trigger reinforcement on time track spots marked with crossed axes
[x] Heroes can't move into hexes occupied by monsters — done in Iteration 1

### Client
[x] Render monster tiles on map hexes — placeholder circles with faction color and name label
[ ] Add proper monster sprite graphics (img/mini_monsters.png) and update css - SKIP FOR NOW
[ ] Add crystal sprite graphics and update CSS (currently using colored circle placeholders)

### Tests
[X] PHP tests for monster placement from cards

---

## Iteration 3: Monster Movement

**Goal**: Monsters move toward Grimheim on monster turn, following paths/arrows/roads. Monster entering Grimheim destroys a town piece.

### Server
[ ] Monster path calculation: arrows → roads → Grimheim
  - `getMonsterPath($hexId)` — returns next area on path toward Grimheim
[ ] Monster movement order: closest to Grimheim first
[ ] Movement rules: don't move if adjacent to hero, can't enter occupied hex
[ ] Monster entering Grimheim: remove monster + remove 1 town piece
[ ] Loss condition: all town pieces destroyed (Freyja's Well is last)

### Client
[x] Animate monster movement (snap-to-position is fine)
[ ] Show town piece removal
[ ] Show win/loss end screen

### Tests
[ ] PHP tests for monster pathfinding
[ ] PHP tests for movement order
[ ] Integration test: monsters reach Grimheim → town piece removed

---

## Iteration 4: Basic Combat

**Goal**: Heroes can attack adjacent monsters. Simple dice rolling, damage tracking, monster death.

### Server
[ ] `Op_actionAttack`: select adjacent monster, roll dice, apply hits as damage
  - `getAreasInRange($areaId, $range)` — for ranged attacks
[ ] Dice system: implement die faces (hit, hit-with-cover, miss, rune), roll N dice
[ ] Hero attack strength: just base hero value for now (no equipment/abilities)
[ ] Damage tracking on monsters (counter on token)
[ ] Monster killed: remove from board, gain XP
[ ] Cover: if monster is in forest, hit-with-cover doesn't count

### Client
[ ] Attack action: highlight adjacent monsters, click to attack
[ ] Show dice results (text/simple display, no animation needed yet)
[ ] Show damage on monster tokens
[ ] Show monster death

### Tests
[ ] PHP tests for dice rolling and hit calculation
[ ] PHP tests for damage and monster death

---

## Iteration 5: Monster Attacks

**Goal**: After movement, monsters adjacent to heroes attack. Hero damage, knocked out heroes.

### Server
[ ] Monster attack phase: all monsters adjacent to heroes attack
[ ] Monster attack strength from monster data
[ ] Trollkin faction bonus: +1 per adjacent trollkin
[ ] Damage to hero (counter on hero card/token)
[ ] Knocked out: damage >= health → hero to Grimheim, damage set to 5, remove 2 town pieces
[ ] `Op_actionMend`: remove 2 damage from hero (5 if in Grimheim)

### Client
[ ] Show monster attacks in log
[ ] Show hero damage counter
[ ] Show knocked out animation (hero moves to Grimheim)
[ ] Mend action UI

### Tests
[ ] PHP tests for monster attack resolution
[ ] PHP tests for knocked out hero handling

---

## Iteration 6: Full Game Loop with Goblins

**Goal**: Complete playable game with Trollkin goblins only. All 6 actions work. Short time track. Win/loss conditions. This is the true MVP.

### Server
[ ] `Op_actionPrepare`: draw 1 event card (stub — just log "drew a card" if no cards yet)
[ ] `Op_actionFocus`: add 1 mana to a card (stub — just log for now)
[ ] Action selection: enforce "must pick 2 different actions" rule
[ ] Charge: on skull time track spots, monsters move +1 extra
[ ] Goblin special: moves 2 instead of 1

### Client
[ ] Action selection UI: show 6 action buttons, disable already-picked action
[ ] End turn button
[ ] Undo support for action selection

### Tests
[ ] Integration test: full round (all players turn + monster turn)
[ ] Test win condition (survive time track)
[ ] Test loss condition (all town pieces destroyed)

---

## Iteration 7: Add Brutes and Trolls

**Goal**: Full Trollkin faction. Brutes (rank 2) and Trolls (rank 3) with higher stats.

### Server
[x] Add brute and troll token types and material data — all 3 factions (trollkin, firehorde, dead) with 3 ranks each defined in monster_material.csv
[x] Add monster cards that spawn brutes and trolls — all 54 cards defined in monstercard_material.csv
[x] Red monster card deck (has stronger monsters) — 18 red cards defined, Op_reinforcement supports deck parameter
[x] Reinforcement: yellow cards on yellow spots, red cards on red spots — Op_reinforcement handles both decks

### Client
[x] Different visual for brutes and trolls vs goblins — all 9 monster types have distinct CSS in Minis.scss with rank-based sizing

### Tests
[x] Test mixed monster spawning and movement — Op_reinforcementTest covers brutes/trolls

---

## Iteration 8: Hero Cards and Equipment (1 Hero)

**Goal**: Pick simplest hero. Implement hero card, starting equipment, starting ability. Equipment gives attack bonus. Ability can be activated.

### Server
[ ] Define first hero's card data (hero card, 1 starting ability, 1 starting equipment)
[ ] Hero card effect applies during relevant actions
[ ] Equipment: attack strength bonus, once-per-turn activation
[ ] Ability: once-per-turn activation, costs mana
[ ] Focus action: actually adds mana to ability/equipment cards

### Client
[ ] Player board: show hero card, active ability, active equipment
[ ] Card tooltips with effects
[ ] Activate equipment/ability buttons (free actions)

### Tests
[ ] Test equipment attack bonus
[ ] Test ability activation and mana spending

---

## Iteration 9: Event Cards and Prepare Action

**Goal**: Event card deck works. Prepare action draws cards. Cards can be played as free actions.

### Server
[ ] Define first hero's event cards (start with 5-10 simplest ones)
[ ] Event deck setup: shuffle at game start
[ ] Prepare action: draw 1 event card, hand limit 4
[ ] Play event: free action, apply effect, discard
[ ] Event deck exhaustion: shuffle discard pile

### Client
[ ] Hand display (private to player)
[ ] Play card from hand
[ ] Discard interface when at hand limit

### Tests
[ ] Test draw, play, discard cycle
[ ] Test hand limit enforcement

---

## Iteration 10: Quest and Upgrade System

**Goal**: Equipment cards have quests. Completing quests unlocks new equipment. Upgrade system (spend XP to gain abilities or improve cards).

### Server
[ ] Quest definitions on equipment cards
[ ] Quest progress tracking
[ ] Quest completion → new equipment active
[ ] Upgrade cost track: 5, 6, 7, 8, 9, 10...
[ ] End-of-turn upgrade option: spend XP for new ability or card improvement
[ ] Mana generation at end of turn

### Client
[ ] Quest progress display on equipment cards
[ ] Upgrade prompt at end of turn
[ ] Ability pile and equipment pile browsing

### Tests
[ ] Test quest completion conditions
[ ] Test upgrade cost calculation

---

## Iteration 11: Second Monster Faction (Fire Horde)

**Goal**: Add Sprites, Elementals, Jotunn. Faction effect: range 2 attacks.

### Server
[ ] Fire Horde monster data and tokens
[ ] Range 2 attack for Fire Horde monsters
[ ] Monster cards that spawn Fire Horde monsters

### Client
[ ] Fire Horde visual styling
[ ] Range indicator for ranged monster attacks

### Tests
[ ] Test range 2 attacks

---

## Iteration 12: Third Monster Faction (Dead) + Legends

**Goal**: Add Imps, Skeletons, Draugr. Dead faction effect: runes = hits. Draugr armor. Add Legend monsters.

### Server
[ ] Dead faction monster data
[ ] Runes count as hits for Dead attacks
[ ] Draugr armor (prevent 1 damage each time)
[ ] Legend monsters: unique stats, destroy 3 town pieces, swap movement
[ ] All 54 monster cards defined

### Client
[ ] Dead faction visual styling
[ ] Legend monster special display

### Tests
[ ] Test Dead faction effects
[ ] Test Legend special rules

---

## Iteration 13: Remaining Heroes

**Goal**: Implement heroes 2, 3, 4 with full card decks.

### Server
[ ] Hero 2: card data, abilities, equipment, events, all effects
[ ] Hero 3: same
[ ] Hero 4: same

### Client
[ ] Hero-specific card art/styling

### Tests
[ ] Test each hero independently
[ ] Test hero combinations

---

## Iteration 14: Monster Die and Game Options

**Goal**: Optional monster die variant. Game options for time track length, difficulty, player count.

### Server
[ ] Monster die with 6 faces and effects
[ ] Game options in gameoptions.json: time track (short/long), monster die (on/off), difficulty
[ ] Long time track support
[ ] 1-4 player support with correct town piece counts

### Client
[ ] Monster die roll display
[ ] Game option selection in lobby

---

## Iteration 15: Polish and BGA Compliance

**Goal**: Animations, game log, responsive layout, BGA publishing requirements.

### UI/UX
[ ] Smooth movement animations (hero and monster)
[ ] Dice roll animation
[ ] Card play/draw animations
[ ] Responsive layout
[ ] Card zoom on hover
[ ] Visual indicators (current turn, threats, quest progress)

### BGA Requirements
[ ] `getGameProgression()` based on time track
[ ] `zombieTurn()` for disconnected players
[ ] Game statistics in stats.json
[ ] All strings translatable
[ ] Tooltips on all interactive elements
[ ] Input validation review (security)
[ ] Private info check (event cards in hand)

### Testing
[ ] Full game playthrough (short + long track)
[ ] All hero combinations
[ ] All player counts
[ ] Edge cases (multiple knockouts, legend interactions, empty decks)
[ ] Pre-alpha BGA checklist

---
