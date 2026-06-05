## TODO



[ ] Quest UI polish: progress badge (`X / N`) on deck-top card, `completeQuest` button styling/icon, completion fanfare animation, enriched log lines (running progress + completion).
[ ] Dice roll animation




### Rules-sweep gaps (RLIST 2026-06-02)

Surfaced by the 7-agent code-review pass against [RLIST.md](RLIST.md). Cross-refs back to RLIST rule IDs. Bugs at the top, missing rules/options below, behaviour-drift items needing a design decision after that, and untested-but-implemented coverage gaps at the bottom.

#### Bugs (code disagrees with rules)

- [x] **R.20.3 — `Op_adj` ignores hero's own-hex terrain.** Fixed in [Op_adj.php](../../modules/php/Operations/Op_adj.php) — own-hex is now checked before the adjacent-hex loop via a shared `matches()` helper. Quests like Dwarf Mail's `adj(mountain)` now count when the hero stands on the matching terrain. Regression test: `Op_adjTest::testGatePassesWhenHeroIsOnTerrain`.

#### Missing rules / unimplemented options

- [ ] **R.14.1 — No player choice on monster attack resolution order.** [Op_monsterAttackAll.php:24–44](../../modules/php/Operations/Op_monsterAttackAll.php#L24) auto-buckets by hero hex with no prompt. Rule (RULES.md:284): "In whichever order the players decide". Matters when ≥2 monsters attack the same hero and chain effects (Trollkin adjacency, KO timing) depend on order. Distinct from R.14.2 (which monster picks which hero); this one is which monster goes first.
- [ ] **R.21.3 — No gameoption for "skip starting kit"** (start with no gold, mana, or hand). [gameoptions.json](../../gameoptions.json) has only options 100/101/102.
- [ ] **R.21.4 — No gameoption for "start one step up on upgrade-cost track"** to make upgrades more expensive. Same file.


#### Behaviour drift — needs a design decision

- [ ] **R.12.2 / R.12.3 — Maneuver uses `getPlayerColors()` not first-player turn order.** [Op_monsterDieManeuver.php](../../modules/php/Operations/Op_monsterDieManeuver.php) iterates by player_id; rule (RULES.md:254) says "in player order". For chain effects this can change which monster ends where. Likely benign for most rotations, but technically off.
- [ ] **R.13.7 — Road-merge tiebreak uses `strcmp(hex)`, not "monster already on road moves first".** [HexMap.php:633](../../modules/php/Common/HexMap.php#L633). Two monsters at equal distance, one already on a road and one stepping onto it, may resolve in wrong order.


#### Missing test coverage (implemented but no test pins it)

- [ ] **R.1.2** — Roads work via `dir` clock-tag indirection; CSV has a `road` column code never reads explicitly. No test exercises a non-`dir` road semantic.
- [ ] **R.1.5** — Forest cover (`hitcov` in `Character::countHit`) has no dedicated unit test.
- [ ] **R.2.4** — Exit-Grimheim-blocked-by-mountain works via `canStopOn` but no explicit test asserts it.
- [ ] **R.3.4** — Win-by-track-end branch in `Game::isEndOfGame` has no test.
- [ ] **R.4 (most rows)** — Setup steps R.4.1, R.4.2, R.4.5, R.4.6, R.4.8 (action markers), R.4.9, R.4.10, R.4.11, R.4.12 are implemented but largely under-asserted. A single `Campaign_SetupTest` covering "post-setup state matches RULES.md §4" would close most.
- [ ] **R.5.5** — Round → monster turn transition only covered indirectly via campaign tests.
- [ ] **R.6.6** — No dedicated `Op_actionPracticeTest`.
- [ ] **R.7.4, R.7.5, R.7.8** — Event-card timing rules ("can't interrupt mid-action", "after roll", "free actions never count as attack action") work architecturally but no rule-named tests pin them.
- [ ] **R.10.7** — Mana-on-gained-ability fires this turn; `Campaign_UpgradeTest::testUpgradeGainNewAbility` doesn't assert.
- [ ] **R.10.8** — 10-gold upgrade cost cap is implicit (`min(cost+1, 10)`); no test pinning behavior at the cap.
- [ ] **R.13.15** — Suppressive Fire `stunmarker` is only checked in the normal-move loop (correct per rule); no test confirms Monster-Dice rotation / Legend push *bypass* it.
- [ ] **R.15.4** — Legend yellow/red side selection (correct side per turn track) not asserted.

#### Open per-card sweeps (not a code search — needs reading every card's `r` declaration)

- [ ] **R.6.7** — "Move +1" vs "Move 1 area" is a card-text distinction; per-card sweep across `card_*_material.csv`.
- [ ] **R.7.9** — Always-on / on-trigger abilities skipping `spendUse` — correctness depends on each card's `r` declaration.



### Code TODOs / XXX (swept from source on 2026-05-13)

- [ ] [Op_monsterAttack.php:60](../../modules/php/Operations/Op_monsterAttack.php#L60) — Hero selection picks first hex; rules may want closest / random / player choice. **Valid, gameplay-affecting.** *(matches RLIST **R.14.2**)*
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

[ ] Allow moveMonster to push into Grimheim (designer-confirmed) *(matches RLIST **R.22.6**)*
[ ] Share Gold - non-interactive, like "e-mail" it or drop on the board, do not want to stop game to give other player control *(matches RLIST **R.7.7**)*
  [ ] **Remove `Op_performAction` — useless wrapper.** `performAction(X)` does `$this->queue($X)` which the DSL already does for bare `X` (see Speedy Attack `discardEvent:actionAttack` vs Rapid Strike `performAction(actionAttack)` — equivalent). Migrate Rapid Strike I/II, Alva's Bracers, and any other callers to bare-action form, then delete `Op_performAction.php` and the `performAction` row from `op_material.csv`.
[ ] Add `tracker_armor` for consistency with other stats — move armor out of Material-only read path so it can be modified by cards
[ ] Turn signal in title bar — currently says "laskava1 performs an action" using player name; should say "Bjorn / laskava1" or just hero name so the active *hero* is identifiable.
