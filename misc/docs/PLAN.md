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
[~] Other 3d pieces and iconography (png) — dice sprite done (img/dice.png), crystals TODO

## High level plan

[x] Transform templated project into typescript enabled
[x] Copy boilerplate code from another game: tokens db, machine db, common utils, etc
[x] Phase 1: Core game framework and board setup
[x] Phase 2: Basic player turn with one hero (reduced rules)
[x] Phase 3: Monster system with one monster type (spawning + movement done)
[x] Phase 4: Combat and damage system
[x] Phase 5a: Hero attribute trackers (strength, range, move, health)
[~] Phase 5: Equipment, quests, and upgrades — equipment activation done, quests not tracked yet
[x] Phase 6: Full monster turn (movement, attack, reinforcements)
[x] Phase 7: Add remaining monster types and legends (all 3 factions done in Iter 2)
[x] Phase 8: Add remaining heroes
[ ] Phase 9: Polish, animations, and BGA compliance
[ ] Phase 10: Testing and alpha release

---

## Other rule gaps

[x] Encounters - there are crystals placed on map, we need to implement hero running into them
[x] Main weapon
[ ] Share Gold - non-interactive, like "e-mail" it or drop on the board, do not want to stop game to give other player control


## Quests
### Server

[~] Quest definitions on equipment cards — quest column exists in card_equip_material.csv but no gameplay tracking
[ ] Quest progress tracking
[ ] Quest completion → new equipment active
[x] `effect_gainEquipment($cardId, $owner)` — places an equipment card on the player's tableau and fires `trigger(enter)`. Should be called from quest completion, upgrade flow, and starting equipment setup ([Game.php:127](modules/php/Game.php#L127)). Black Arrows ("starts with 3 arrows here") and Tiara ("starts with 6 gold here") need this for their `onEnter` hook to fire.
[x] Upgrade cost track: 5, 6, 7, 8, 9, 10...
[x] End-of-turn upgrade option: spend XP for new ability or card improvement
[x] Mana generation at end of turn — Op_turnEnd iterates cards with mana field, generates crystals

### Client


[ ] Fix missing animation when damage crystals are removed from cards (e.g. repairCard/Durability)
[ ] Quest progress display on equipment cards
[ ] Ability pile and equipment pile browsing

### Tests

[ ] Test quest completion conditions



---


## Iteration 14: Monster Die and Game Options

**Goal**: Optional monster die variant. Game options for time track length, difficulty, player count.

### Game Elements

[x] Add monster die game element — token_material.csv, dice_material.csv, Tokens.scss, Game.ts

### Server

[ ] Monster die with 6 faces and effects
[ ] Game options in gameoptions.json: time track (short/long), monster die (on/off), difficulty
[x] Long time track support
[x] 1-4 player support with correct town piece counts

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

## TODO

- Fix stacked tooltips
- [x] Add hero stats to tooltip (health, attack strength from hero card on tableau)
- Check if damage dice (8 in rules) are meant to be limited or just a physical constraint — verify on BGG forum or designer notes
  [ ] Fix spawn locations in monster cards — current data is not correct
  [ ] Add crystal sprite graphics and update CSS (currently using colored circle placeholders)
  [ ] Show win/loss end screen — BGA default end screen works, custom UI
  [ ] Range indicator for ranged monster attacks
  [ ] Legend monster special display
  [ ] Suppressive Fire multiplayer bug: `findStunCrystal()` in Op_c_supfire finds the first green crystal on any monster globally — in multiplayer (Bjorn + Alva both have Suppressive Fire), one player's resolve/skip could move or remove the other player's stun crystal
  [ ] Flip animation for upgrades
  [x] Main weapon - restriction only one main weapon allowed
  [ ] **Manually test: double-confirm on comma-chained event card rules.** Multi-Shot (`r=2roll(inRange),2roll(inRange)`) creates a `seq` op for the comma-chain. Test via `Campaign_AlvaEventTest::testMultiShotRollsAgainstTwoDifferentMonsters` shows an extra `confirm` step is required after the card pick, before the first sub-op prompts. The root paygain already has `confirm=true` from `Card::useCard`; seq's expandOperation correctly strips confirm from children. Expected UX: click card → prompt for first monster hex (no intermediate confirm). Actual: click card → confirm button → prompt for first monster hex. Verify in the harness whether this is a UX bug (double-click) or intentional. If UX bug, likely fix is in `Op_seq::expandOperation` or how useCard wraps the op.
  [ ] **Remove `Op_performAction` — useless wrapper.** `performAction(X)` does `$this->queue($X)` which the DSL already does for bare `X` (see Speedy Attack `discardEvent:actionAttack` vs Rapid Strike `performAction(actionAttack)` — equivalent). Migrate Rapid Strike I/II, Alva's Bracers, and any other callers to bare-action form, then delete `Op_performAction.php` and the `performAction` row from `op_material.csv`.


[ ] Add `tracker_armor` for consistency with other stats — move armor out of Material-only read path so it can be modified by cards