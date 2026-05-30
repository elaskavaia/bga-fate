## TODO



[ ] Quest UI polish: progress badge (`X / N`) on deck-top card, `completeQuest` button styling/icon, completion fanfare animation, enriched log lines (running progress + completion).
[ ] Dice roll animation




### Code TODOs / XXX (swept from source on 2026-05-13)

- [ ] [Op_monsterAttack.php:60](../../modules/php/Operations/Op_monsterAttack.php#L60) — Hero selection picks first hex; rules may want closest / random / player choice. **Valid, gameplay-affecting.**
- [ ] [Operation.php:790](../../modules/php/OpCommon/Operation.php#L790) — AI/auto-resolve picks one target at random when op expects multi-select. **Valid** — only matters once multi-select ops are auto-resolved (e.g. NPC turns), low impact today.
- [ ] [Game.php:331](../../modules/php/Game.php#L331) — Crystal supply assumes infinite; `pickTokensForLocation` will return fewer than requested if the supply runs out. **Probably valid** — confirm rules cap (do we ever exhaust the supply?) and either log or auto-create.
- [ ] [GameMachine.ts:484](../../src/GameMachine.ts#L484) — `const skippable = false; // XXX` hardcoded in `onMultiSelectionUpdate`. **Valid** — multi-select ops can't currently expose a skip button; revisit if any op needs it.




### Designer rulings to implement (from DESIGN.md Rule clarifications #7–#11)

- [x] **Stitching cross-tableau repair (DESIGN.md #8)** — `Op_removeDamage::getCardOwners()` now extends to the acting hero + heroes within range 1 when mode is `adj`. Stitching I/II CSVs switched to `repairCard(adj)` so equipment on adjacent allies' tableaus is eligible.
- [x] **Windbite recursive chain (DESIGN.md #9)** — `Op_addRoll::resolve` now counts new runes on the just-rolled dice and re-queues itself with that delta when Windbite is on tableau. Dice stay on display_battle (no consumption), so other rune-readers (Wildfire, Bone Bane Bow) still see them.
- [x] **"Move X" is always "up to X" (DESIGN.md #11)** — Op_move no longer filters reachable hexes to exactly maxSteps; all distances 1..count are offered, so Agility II "Move 2" correctly allows stopping at 1. No other cards needed CSV changes.


### Legend factions (designer-confirmed via [BGG 3426870](https://boardgamegeek.com/thread/3426870))

Designer ruling: *"Legends have a faction written on the legend card. Since Nidhuggr specifically is a Wyrm and not a Fire Horde, it doesn't have attack range +1."* Each legend belongs to its printed faction and shares that faction's abilities; effects scoped to a faction (e.g. Tough Guy: "+2 dice for each adjacent trollkin") should include legends of the same faction.

- [x] **Fix Nidhuggr faction: `dead` → `wyrm`** in [monster_material.csv:50-51](../../misc/monster_material.csv#L50). (Note: armor was never inherited from faction — only rune-as-hit was wrongly granted.)
- [x] **Add Wyrm as a new faction** — string label added in `strings_material.csv`. No shared abilities; existing faction-lookup paths (`Monster::getAttackRange`, `Monster::countHit`, `Op_monsterAttack::getMonsterStrength`) treat unknown factions as a no-op, so "wyrm" is handled gracefully.
- [x] **Audit faction-scoped iteration** — existing iterators already use `getRulesFor(monsterId, "faction")` and filter on `getPart(char, 0) === "monster"`, which includes legends. Confirmed: Trollkin adjacency bonus ([Op_monsterAttack.php:165](../../modules/php/Operations/Op_monsterAttack.php#L165)) counts legend trollkin (Grendel/Hrungbald); Fire Horde range 2 ([Monster.php:28](../../modules/php/Model/Monster.php#L28)) applies to Seer/Surt; Dead rune-as-hit ([Monster.php:65](../../modules/php/Model/Monster.php#L65)) applies to Queen. Tests in [tests/Model/MonsterTest.php](../../tests/Model/MonsterTest.php) pin the Nidhuggr and Queen behavior.


### Low priority

[ ] Allow moveMonster to push into Grimheim (designer-confirmed)
[ ] Share Gold - non-interactive, like "e-mail" it or drop on the board, do not want to stop game to give other player control
  [ ] **Remove `Op_performAction` — useless wrapper.** `performAction(X)` does `$this->queue($X)` which the DSL already does for bare `X` (see Speedy Attack `discardEvent:actionAttack` vs Rapid Strike `performAction(actionAttack)` — equivalent). Migrate Rapid Strike I/II, Alva's Bracers, and any other callers to bare-action form, then delete `Op_performAction.php` and the `performAction` row from `op_material.csv`.
[ ] Add `tracker_armor` for consistency with other stats — move armor out of Material-only read path so it can be modified by cards
[ ] Turn signal in title bar — currently says "laskava1 performs an action" using player name; should say "Bjorn / laskava1" or just hero name so the active *hero* is identifiable.