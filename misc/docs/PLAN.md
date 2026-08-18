# Plan for Implementation of game Fate: Defenders of Grimheim on BGA

This document is a plan for the implementation of the game Fate: Defenders of Grimheim on Board Game Arena (BGA). It outlines the steps and tasks required to create a digital version of the game that can be played online.

This document also referred as TODO list.

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
[x] Other 3d pieces and iconography (png) — dice sprite done (img/dice.png), crystals

## High level plan

[x] Transform templated project into typescript enabled
[x] Copy boilerplate code from another game: tokens db, machine db, common utils, etc
[x] Phase 1: Core game framework and board setup
[x] Phase 2: Basic player turn with one hero (reduced rules)
[x] Phase 3: Monster system with one monster type (spawning + movement done)
[x] Phase 4: Combat and damage system
[x] Phase 5a: Hero attribute trackers (strength, range, move, health)
[x] Phase 5: Equipment, quests, and upgrades
[x] Phase 6: Full monster turn (movement, attack, reinforcements)
[x] Phase 7: Add remaining monster types and legends (all 3 factions done in Iter 2)
[x] Phase 8: Add remaining heroes
[ ] Phase 9: Polish, animations, and BGA compliance
[ ] Phase 10: Testing and alpha release

---

## Other rule gaps

[x] Encounters - there are crystals placed on map, we need to implement hero running into them
[x] Main weapon



## Quests
### Server

[x] Quest definitions on equipment cards (`quest_on` / `quest_r` fields in card_equip_material.csv)
[x] Quest progress tracking (red crystals on deck-top equip via `gainTracker` + `countTracker`)
[x] Quest completion → new equipment active (`Op_completeQuest` for player-initiated, `Op_trigger` walks deck-top for trigger-driven, bespoke `CardEquip_*` classes for custom)
[x] `effect_gainEquipment($cardId, $owner)` — places an equipment card on the player's tableau and fires `trigger(enter)`. Should be called from quest completion, upgrade flow, and starting equipment setup ([Game.php:127](modules/php/Game.php#L127)). Black Arrows ("starts with 3 arrows here") and Tiara ("starts with 6 gold here") need this for their `onEnter` hook to fire.
[x] Upgrade cost track: 5, 6, 8, 10 (red square), then 10 for all further upgrades
[x] End-of-turn upgrade option: spend XP for new ability or card improvement
[x] Mana generation at end of turn — Op_turnEnd iterates cards with mana field, generates crystals
[ ] Refactor: share the adjacent-mountain hex selection between `countAdjMountains` and `Op_c_orebiter` (one helper returning the hex list; counter counts it, Orebiter offers it) - separate commit after the Orebiter fix lands

### Client

### Tests



[x] Refactor: merge bug-named campaign tests into hero/card-grouped files - all 9 merged, 332 test methods preserved. New `Campaign_BjornAbilityTest` (Bjorn's ability/hero cards split out of `Campaign_BjornSoloTest`, plus Eagle Eye and Long Shot) and `Campaign_TerrainAdjacencyTest` (AdjTerrain + GrimheimTerrainIsolation); Treetreader and the gainMana prompt -> `Campaign_AlvaAbilityTest`; Speedy Attack -> `Campaign_AlvaEventTest`; multi-kill XP and sweep -> `Campaign_BoldurSweepTest`; multi-kill Helmet quest -> `Campaign_BjornQuestTest`; "Nothing to undo" -> `Campaign_UndoTest`. `BGA #<id>` citations kept in method docstrings, BUG_TRIAGE.md references updated.
[x] Refactor: fold the 4 `tests/Operations/Op_*Bug*Test.php` files into their op's main test file - Tunic mend -> `Op_actionMendTest`, crystal supply -> `Op_applyDamageTest`, two-mana-card prompt -> `Op_gainManaTest`, premature move end -> `Op_moveStepTest`, plus `HeroMoveAdjacentMonsterBug234817Test` -> `HexMapTest` (it owns `getReachableHexes`). `BGA #<id>` citations moved to the fixture helper / method docstrings. No bug-named test files remain. The `game-bug-fix` skill in `~/.claude/skills/` already carries the "add to the grouped file, never create `*Bug<id>Test`" rule; the stale duplicate that shadowed it at `.claude/skills/game-bug-fix/` is deleted.



---


## Iteration 14: Monster Die and Game Options

**Goal**: Optional monster die variant. Game options for time track length, difficulty, player count.

### Game Elements

[x] Add monster die game element — token_material.csv, dice_material.csv, Tokens.scss, Game.ts

### Server


[x] Game option: time track (short/long)
[ ] Game option: monster die (on/off)
[ ] Game option: difficulty
[x] Long time track support
[x] 1-4 player support with correct town piece counts

### Client

[ ] Monster die roll display


---

## Iteration 15: Polish and BGA Compliance

**Goal**: Animations, game log, responsive layout, BGA publishing requirements.

### UI/UX


[x] Responsive layout


### BGA Requirements

[x] `getGameProgression()` based on time track
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


---

