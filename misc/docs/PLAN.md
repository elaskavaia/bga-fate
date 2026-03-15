# Plan for Implementation of game Fate: Defenders of Grimheim on BGA

This document is a plan for the implementation of the game Fate: Defenders of Grimheim on Board Game Arena (BGA). It outlines the steps and tasks required to create a digital version of the game that can be played online.

This document also refered as TODO list.

## Documents

See misc/docs/DESIGN.md for preliminary design
See misc/docs/RULES.md for game rules
See CLAUDE.md for project overview


## Prepare game assets

[x] Read the rulebook of Fate: Defenders of Grimheim and create RULES.md.
[x] Assets of the game including rulebook PDF located at ~/Develop/bga/bga-assets/
[x] Main Board (jpg) — img/EN_Game_Board.jpg
[x] Cards (jpg) — monster cards sprite exists (img/EN_Monster_Cards.jpg), hero cards
[x] Miniatures (png) — hero sprites (img/mini_heroes.png), house sprites (img/mini_houses.png), monster sprites
[~] Other 3d pieces and iconography (png) — dice sprite done (img/dice.png), crystals TODO

## High level plan

[x] Transform templated project into typescript enabled
[x] Copy boilerplate code from another game: tokens db, machine db, common utils, etc
[x] Phase 1: Core game framework and board setup
[x] Phase 2: Basic player turn with one hero (reduced rules)
[x] Phase 3: Monster system with one monster type (spawning + movement done)
[x] Phase 4: Combat and damage system
[ ] Phase 5: Equipment, quests, and upgrades
[x] Phase 6: Full monster turn (movement, attack, reinforcements)
[x] Phase 7: Add remaining monster types and legends (all 3 factions done in Iter 2)
[x] Phase 8: Add remaining heroes
[ ] Phase 9: Polish, animations, and BGA compliance
[ ] Phase 10: Testing and alpha release

---

## Iteration 0: Skeleton Game Loop (No Real Rules)

**Goal**: Game starts, shows board with map, each player has a hero on the board. Player can do "practice" (gain 1 XP) or "move" (click hex to move hero). Then monster turn just advances time track. Game ends when time track runs out. **Playable end-to-end on BGA.**

**What already exists**: hex map rendering, token framework, Op_turn scaffold, Op_actionMove/Op_actionPractice stubs, state machine wiring.

### Game Elements
[x] Add hero miniature tokens (hero_1..hero_4) — token_material.csv
[x] Add house tokens (house_0..house_9) — token_material.csv
[x] Add rune stone token — token_material.csv
[x] Add crystal tokens (green, red, yellow) — token_material.csv
[x] Add action marker tokens — token_material.csv

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


### Tests
[x] PHP unit tests for movement validation and reachability

---

## Iteration 2: Monsters on the Board (Static)

**Goal**: Monsters appear on the board. No movement or combat yet — just spawning from monster cards during reinforcement.

### Game Elements
[x] Add monster tokens game element — monster_material.csv
[x] Add monster card game element — monstercard_material.csv

### Server
[x] Define monster tokens in material (goblins only to start — Trollkin rank 1) — already in monster_material.csv
[x] Monster card data: define a few yellow monster cards with goblin placement
[x] `Op_reinforcement`: draw monster card, place goblins at specified locations
[x] Trigger reinforcement on time track spots marked with crossed axes
[x] Heroes can't move into hexes occupied by monsters — done in Iteration 1

### Client
[x] Render monster tiles on map hexes — placeholder circles with faction color and name label
[x] Add proper monster sprite graphics (img/mini_monsters.png) and update css 


### Tests
[X] PHP tests for monster placement from cards

---

## Iteration 3: Monster Movement

**Goal**: Monsters move toward Grimheim on monster turn, following paths/arrows/roads. Monster entering Grimheim destroys a town piece.

### Server
[x] Monster path calculation: arrows → roads → Grimheim — `getMonsterNextHex()` + `getDistanceMapToGrimheim()` in HexMap.php
[x] Monster movement order: closest to Grimheim first — `getMonstersOnMap()` sorts by distance
[x] Movement rules: don't move if adjacent to hero, can't enter occupied hex — `moveMonsterOneStep()` in Op_turnMonster.php
[x] Monster entering Grimheim: remove monster + remove 1 town piece — `monsterEntersGrimheim()`, legends destroy 3
[x] Loss condition: all town pieces destroyed (Freyja's Well is last) — `isHeroesWin()` in Game.php

### Client
[x] Animate monster movement (snap-to-position is fine)
[x] Show town piece removal


### Tests
[x] PHP tests for monster pathfinding — 8 tests in MonsterMovementTest.php
[x] PHP tests for movement order — sorting + closest-first integration tests
[x] Integration test: monsters reach Grimheim → town piece removed — house destruction, Freyja's Well, legend, charge tests

---

## Iteration 4: Basic Combat

**Goal**: Heroes can attack adjacent monsters. Simple dice rolling, damage tracking, monster death.

### Game Elements
[x] Add attack dice game element — token_material.csv, dice_material.csv, Tokens.scss, Game.ts

### Server
[x] `Op_actionAttack`: select adjacent monster, roll dice, apply hits as damage
[x] `getAreasInRange($areaId, $range)` — for ranged attacks
[X] Dice system: implement die faces (hit, hit-with-cover, miss, rune), roll N dice
[X] Hero attack strength: just base hero value for now (no equipment/abilities)
[X] Damage tracking on monsters (red crystals placed on monster/hero)
[X] Monster killed: remove from board, gain XP
[X] Cover: if monster is in forest, hit-with-cover doesn't count

### Client
[X] Attack action: highlight adjacent monsters, click to attack
[X] Show dice results (text/simple display, no animation needed yet)
[X] Show damage on monster tokens
[x] Show monster death

### Tests
[x] PHP tests for dice rolling and hit calculation
[x] PHP tests for damage and monster death

---

## Iteration 5: Monster Attacks

**Goal**: After movement, monsters adjacent to heroes attack. Hero damage, knocked out heroes.

### Server
[x] Monster attack phase: all monsters adjacent to heroes attack
[x] Monster attack strength from monster data
[x] Trollkin faction bonus: +1 per adjacent trollkin
[x] Damage to hero (counter on hero card/token)
[x] Knocked out: damage >= health → hero to Grimheim, damage set to 5, remove 2 town pieces
[x] `Op_actionMend`: remove 2 damage from hero (5 if in Grimheim)

### Client
[x] Show monster attacks in log
[x] Show hero damage counter
[x] Show knocked out animation (hero moves to Grimheim)
[x] Mend action UI

### Tests
[x] PHP tests for monster attack resolution
[x] PHP tests for knocked out hero handling

---

## Iteration 6: Full Game Loop with Goblins

**Goal**: Complete playable game with Trollkin goblins only. All 6 actions work. Short time track. Win/loss conditions. This is the true MVP.

### Server
[x] `Op_actionPrepare`: draw 1 event card
[x] `Op_actionFocus`: add 1 mana to a card 
[x] Action selection: enforce "must pick 2 different actions" rule
[x] Charge: on skull time track spots, monsters move +1 extra
[x] Goblin special: moves 2 instead of 1

### Client
[x] Action selection UI: show 6 action buttons, disable already-picked action
[x] End turn button
[xs] Undo support for action selection

### Tests
[x] Integration test: full round (all players turn + monster turn) — Campaign_BjornSoloTest
[x] Test win condition (survive time track)
[x] Test loss condition (all town pieces destroyed)

---



## Monsters: Brutes and Trolls

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


## Monster Faction (Fire Horde)

### Server
[x] Fire Horde monster data and tokens — done in Iter 7
[x] Range 2 attack for Fire Horde monsters — Monster::getAttackRange(), Op_monsterAttack uses getHexesInRange()
[x] Monster cards that spawn Fire Horde monsters — done in Iter 7

### Client
[x] Fire Horde visual styling — done in Iter 7


### Tests
[x] Test range 2 attacks — MonsterTest, Op_monsterAttackTest, HeroTest, Op_actionAttackTest

---

## Monsters: Dead + Legends

### Server
[x] Dead faction monster data — done in Iter 7
[x] Runes count as hits for Dead attacks — Character::applyDamage() checks attacker faction
[x] Draugr armor (prevent 1 damage each time) — Character::getArmor(), beginDefense(), armor absorbs in applyDamage()
[x] Legend monsters: unique stats, destroy 3 town pieces, swap movement — material + movement done in Iter 3/7
[x] Legend reinforcement: legend cards spawn legend tokens + escort monsters — Op_reinforcement
[x] All 54 monster cards defined — done in Iter 7

### Client
[x] Dead faction visual styling — done in Iter 7


### Tests
[x] Test Dead faction effects — MonsterTest: rune-as-hit, draugr armor, armor absorb/reset
[x] Test Legend special rules — MonsterMovementTest: legend destroys 3 houses, legend movement

---

---

## Iteration 8: Hero Cards (1 Hero)

**Goal**: Pick simplest hero. Implement hero card, starting equipment, starting ability. Equipment gives attack bonus.

### Game Elements
[x] Add hero card game element (all heroes) — card_material.csv, Cards.scss, Game.ts
[x] Add hero card sprite for first hero — img/<hero>_hero_cards.jpg

### Server
[x] Define first hero's card data (hero card, 1 starting ability, 1 starting equipment)
[x] Equipment: attack strength bonus
[x] Focus action: actually adds mana to ability/equipment cards — Op_actionFocus, Op_actionFocusTest

### Client
[x] Player board: show hero card, active ability, active equipment
[x] Card tooltips with effects
[x] Activate equipment/ability buttons (free actions)

### Tests


---

## Iteration 9: Event Cards and Prepare Action

**Goal**: Event card deck works. Prepare action draws cards. Cards can be played as free actions.

### Server
[x] Event deck setup: shuffle at game start
[x] Prepare action: draw 1 event card, hand limit 4 — Op_actionPrepare queues Op_drawEvent
[x] Op_drawEvent: auto-draws if hand < 4, else asks player to discard or skip
[x] Op_discardEvent: discard a card from hand to discard pile
[x] Play event: select from hand, discard, apply effect — Op_playEvent queues effect operations from `r` column

### Client
[x] Hand display (private to player)
[x] Play card from hand
[x] Discard interface when at hand limit — Op_drawEvent shows hand cards to pick

### Tests
[x] Test draw, play, discard cycle — Op_drawEventTest, Op_discardEventTest, Op_actionPrepareTest
[x] Test hand limit enforcement — Op_drawEventTest

---

## Iteration 9.5: Card Effect Operations

**Goal**: Implement the generic parameterized operations that are building blocks for event/equipment/ability card effects. These are queued by `playEvent`/`useEquipment`/`useAbility` after a card is played. See DESIGN.md "Card Effect Operations" for full notation.

### Operations (new Op_ classes)
- [x] `dealDamage` (Countable) — Deal X damage to target character (no dice). Used by: Kick, Courage, Lightning Bolt, Rain of Fire, Swift Kick, etc.
- [x] `heal` (Countable) — Remove X damage from target hero. Used by: Rest, Stitching, Belt of Youth, etc.
- [x] `roll` (Countable) — Roll X attack dice against target monster. Used by: Snipe, Hard Rock, Chain Lightning, Fire Spark, etc.
- [x] `moveHero` (Countable) — Move hero up to X areas. Used by: Agility, Maneuver, Fleetfoot, Quick Reflexes
- [x] `moveMonster` — Move target monster X areas. Used by: Kick, Swift Kick, Bowling
- [x] `killMonster` — Kill target monster matching filter (rank, health, range). Used by: Back Down, Short Temper, Heat Death, In Charge
- [x] `gainXp` (Countable) — Gain X gold/XP. Used by: Miner, Popular, Discipline
- [x] `gainMana` — Add X mana to target card. Used by: Power Surge, Elementary Student
- [x] `spendMana` — Remove X mana from source card (cost). Used by: mana-activated abilities
- [ ] `gainDamage` — Add 1 damage to equipment card (durability cost). Used by: equipment activated effects
- [x] `addTownPiece` — Add 1 Town Piece to Grimheim. Used by: Inspire Defense (`2spendMana(grimheim):addTownPiece`)
- [ ] `preventDamage` (Countable) — Prevent up to X incoming damage. Used by: Dodge, Stoneskin, Riposte, Dreadnought
- [x] `repairCard` — Remove X damage from target card. Used by: Durability, Sewing
- [x] `performAction` — Queue an additional main action. Used by: Speedy Attack, Rapid Strike, Sophisticated
- [x] `spendAction` — Consume a main action slot without performing it. Used by: event cards that cost an action
- [x] `drawEvent` — already exists, make Countable for multi-draw (Starsong)

### Integration
- [x] `playEvent` resolve: parse `r` column notation, queue corresponding operations
- [x] Operation parser: target params `(adj)`, `(self)`, `(inRange)` — already supported via `getParam()`
- [x] Operation parser: chaining with `;` and cost notation with `:` — already supported



---

## Iteration 10: Equipment and Ability Activation

**Goal**: Equipment and abilities can be activated as free actions. Equipment has durability cost. Abilities cost mana. Hero card effect applies during relevant actions.

### Server
[ ] Equipment: once-per-turn activation
[ ] Ability: once-per-turn activation
[ ] Ability: costs mana
[ ] Hero card effect applies during relevant actions
[ ] `useEquipment` resolve: parse `r` column, handle `gainDamage:effect` cost
[ ] `useAbility` resolve: parse `r` column, handle `spendMana:effect` cost

### Tests
[ ] Test ability activation and mana spending
[ ] Integration tests: use equipment with durability cost → effect executes
[ ] Test equipment attack bonus

---

**Goal**: Equipment cards have quests. Completing quests unlocks new equipment. Upgrade system (spend XP to gain abilities or improve cards).

### Server
[ ] Quest definitions on equipment cards
[ ] Quest progress tracking
[ ] Quest completion → new equipment active
[ ] Upgrade cost track: 5, 6, 7, 8, 9, 10...
[ ] End-of-turn upgrade option: spend XP for new ability or card improvement
[ ] Mana generation at end of turn

### Client
[ ] Show damage and mana on cards as crystal counters (unlike hero/monster damage buckets which are icons)
[ ] Fix missing animation when damage crystals are removed from cards (e.g. repairCard/Durability)
[ ] Quest progress display on equipment cards
[ ] Upgrade prompt at end of turn
[ ] Ability pile and equipment pile browsing

### Tests
[ ] Test quest completion conditions
[ ] Test upgrade cost calculation

---



---

## Iteration 13: Remaining Heroes

**Goal**: Implement heroes 2, 3, 4 with full card decks.

### Game Elements
[ ] Add hero card sprites for heroes 2, 3, 4 — img/<hero>_hero_cards.jpg

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

### Game Elements
[x] Add monster die game element — token_material.csv, dice_material.csv, Tokens.scss, Game.ts

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
[ ] Pre-alpha BGA checklist (see below)

---

## Pre-Alpha BGA Checklist

Source: https://en.doc.boardgamearena.com/Pre-release_checklist
See misc/docs/CHECKLIST.md


## TODO

* Fix stacked tooltips
* [x] Add hero stats to tooltip (health, attack strength from hero card on tableau)
* Check if damage dice (8 in rules) are meant to be limited or just a physical constraint — verify on BGG forum or designer notes
[ ] Fix spawn locations in monster cards — current data is not correct 
[ ] Add crystal sprite graphics and update CSS (currently using colored circle placeholders) 
[ ] Show win/loss end screen — BGA default end screen works, custom UI 
[ ] Range indicator for ranged monster attacks
[ ] Legend monster special display