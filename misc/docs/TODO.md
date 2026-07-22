## TODO



[ ] Quest UI polish: progress badge (`X / N`) on deck-top card, `completeQuest` button styling/icon, completion fanfare animation, enriched log lines (running progress + completion).
[x] **Miniboard redesign (sketch 05 variant B).** Panel stats reorganized into two pill rows over the portrait: combat (attack, defence, damage - red tint when wounded) and economy (hand n/N, signature stat, gold merged with upgrade cost as x/y capsule). Signature stat is data-driven: range shows when != 1, move when != 3; Boldur gets a static armor pill (shield emoji placeholder, swap to sprite icon later). Dark theme (`html[data-theme=dark]`) switches to washed-out art + dark pills (sketch variant A). Sketches in staging/sketch/ (04-06). Files: [Tokens.scss](../../src/css/Tokens.scss), [Game.ts](../../src/Game.ts) (armor pill). Note: harness snapshot shows no portrait/banner because harness gamedatas lacks heroNo - real BGA is fine; worth fixing in harness someday.
[ ] Dice roll animation
[x] **Tiara bug: single step ignored mid-move.** FIXED: the move machinery was fine - the Tiara's `quest_on` column was empty (a manual-only quest), so reaching the Dark Forest never fired it. Set `quest_on=TMove` in `card_equip_material.csv` (matches peer quests like Smiterbiter) + genmat; now auto-completes on move. Test rewritten: `Campaign_AlvaQuestTest::testTiaraAutoCompletesWhenMovingIntoDarkForest`. Full suite green.
[x] **Mobile: action buttons overlapping the map.** FIXED: added an `@media (max-width: 740px)` rule in [Game.scss](../../src/css/Game.scss) that drops `.game_top_bar` back to `position:static` on narrow screens, so `#page-title` grows to fit the wrapped buttons and the map (`#thething_wrap`) starts below them. scss rebuilt. (Visual check on device recommended - breakpoint may want tuning.)
[x] **Spawn: adjacent monster cannot be placed in Grimheim.** FIXED: added `&& !$this->game->hexMap->isInGrimheim($hex)` to `Op_spawn::getFreeAdjacentHexes()` ([Op_spawn.php](../../modules/php/Operations/Op_spawn.php)). Move/push-into-Grimheim (R.22.6) untouched. New test `testSpawnNeverTargetsGrimheim`; two existing tests corrected (they had relied on spawning onto the Grimheim neighbour). Full suite green.
[ ] **Card "Sophisticated": Mend throws a SERVER ERROR on click (hero HAD damage).** STILL OPEN. Repro details from Victoria (2026-07-09): hero had 4 damage, had JUST MOVED into Grimheim that same turn, then played Sophisticated; the choice dialog OFFERED Mend, clicking it threw a server error "like it was invalid choice" (checkUserTargetSelection rejection). So the choice was built as valid but re-validated as void on resolve - suspect stale state between args build and resolve (delegate switch `2heal` vs `5removeDamage` depends on hero hex; occupancy/args caching). Path to trace: `Op_or -> Op_actionMend -> 5removeDamage` (`Op_actionMend.php`, `Op_removeDamage.php`). Harness STILL cannot reproduce even with the exact scenario (real move into Grimheim + 4 damage + Sophisticated -> Mend): `Op_actionMendGrimheimMoveTest` is green. `checkUserTargetSelection:01` ("Internal Error") means the picked choice key was MISSING from freshly recomputed moves - `Op_or::getPossibleMoves` returns bare `ERR_COST` when ALL choices are void at resolve time. Remaining suspects: multiplayer state, the real card-play path (costs), playing the card mid step-move, undo savepoint interaction. NEED: exact error text from BGA log / table id.
[x] **(follow-up found while investigating the above) Mend when VOID - NOT a bug.** An agent proposed making `Op_actionMend` never-void (allow a "wasted" Mend); REJECTED - Mend must stay void when nothing is damaged. `Op_or::paramInfo` already marks void sub-choices `ERR_NOT_APPLICABLE` (unselectable in UI), and the `UserException` only fires when a test force-picks the disabled choice - correct server-side validation. Behaviour pinned in `Op_actionMendVoidTest`.
[x] **Move: sometimes need to click the target area twice** to register the move. FIX APPLIED (medium confidence): hero/monster minis are `height:100%` and overflow their hex ([Minis.scss](../../src/css/Minis.scss)); a click on the overflow resolved to the figure's inactive source hex. Added `pointer-events:none` to `.hex > .hero`/`.monster` (the hex owns the click listener) so clicks fall through to the hex under the pointer; scoped to `#map_area:has(.active_slot)` so figures keep hover tooltips outside target selection. scss rebuilt. (Likely also a re-render timing race - verify on device; if it persists, walk `event.target` up to the nearest `.hex` in `onClickSanity`.)




### Legend special abilities (mostly unimplemented)

Dictated by Victoria 2026-07-21 (voice; garbled bits marked CONFIRM). Level I = yellow `_1`, Level II = red `_2`.
For each unbuilt one: implement (server) + tooltip note (`buildLegendTooltip`) + test, then check off.
Faction effects (trollkin support, firehorde range, dead runes) are separate and already covered (RLIST R.18/R.19).

Card format: the card prints the faction line, then that legend's own ability. An ability applies to the legend
only, unless its text explicitly names the faction/others - e.g. Hrungbald "double the Trollkin support",
Surt LI "all Fire Horde", Queen LII "other Dead".

Checkbox = implemented + tested. Verified against code 2026-07-21.

**Legend 1 - Queen of the Dead** (dead)
- [x] LI: may only be damaged by adjacent characters (range-2+ deals nothing). Queen only (FORUM.md:110). `Op_applyDamage` drops damage from a non-adjacent attacker; client tooltip note (1_1). Test `Op_applyDamageTest::testQueenIOnlyDamagedByAdjacentCharacters`.
- [x] LII: all other Dead monsters have +1 health while Queen II (`monster_legend_1_2`) is on the board. `Monster::getEffectiveHealth` gated on `Monster::isOnBoard`; client indicator/tooltip via `getMonsterMaxHealth` + tooltip note (1_2). Test `MonsterTest::testQueenIIGivesOtherDeadPlusOneHealth`.

**Legend 2 - Seer of Odin** (DEAD both levels)
- [x] Faction data fix: `monster_legend_2_1/2` set to `dead` (were `firehorde`); regen; range now 1, gains dead rune-as-hit. Stale "Seer=firehorde" notes corrected in RLIST R.18.3/R.19.6 + the audit item. Test `MonsterTest::testSeerOfOdinIsDeadFaction`.
- [x] LI: on arrival, place skeletons in all unoccupied areas adjacent to Temple Ruins. Confirmed verbatim + designer-approved (FORUM.md:2934). `Op_reinforcement::spawnSeerSkeletons` (fires when monster_legend_2_1 is placed); client tooltip note (2_1). Test `Op_reinforcementTest::testSeerSpawnsSkeletonsAroundTempleRuins`.
- [x] LII: as its attack, deals 1 unpreventable damage to every hero. `Op_monsterAttack::resolveSeerAttack`.

**Legend 3 - Grendel** (trollkin)
- [x] LI: no unique ability - Trollkin support only. Nothing to build.
- [x] LII: attacks twice; each rune counts as two hits. `Op_monsterAttackAll` queues Grendel II (`monster_legend_3_2`) twice; `Monster::countHit` returns 2 for its runes; client tooltip note (3_2). Tests `MonsterTest::testGrendelIIRuneCountsAsTwoHits`, `Campaign_LegendAbilityTest::testGrendelIIAttacksTwice`. (Full "may split between 2 heroes" inherits the R.14.2 pick-first targeting limitation.)

**Legend 4 - Surt** (firehorde)
- [x] LI: runes count as hits for all Fire Horde while Surt I (`monster_legend_4_1`) is on the board. `Monster::countHit` gated on `Game::isMonsterOnBoard`; client tooltip note (4_1). Tests: `Op_monsterAttackTest::testSurtIGrantsFirehordeRuneAsHit` / `testSurtIIDoesNotGrantFirehordeRune`.
- [x] LII: attack range 3, Surt only. `Monster::getAttackRange` (id monster_legend_4_2); client tooltip via `getMonsterAttackRange`. Tests: `MonsterTest::testSurtIIHasRange3`, `Op_monsterAttackTest::testSurtIIAttacksAtRange3`. (Resolves FORUM.md:115; the FORUM.md:673 "all fire horde" remark is superseded.)

**Legend 5 - Hrungbald** (trollkin)
- [x] LI + LII: doubles the Trollkin support (+2 per adjacent trollkin while on the board). `Op_monsterAttack::isHrungbaldInPlay`, tooltip, test `testHrungbaldDoublesTrollkinSupport`.

**Legend 6 - Nidhuggr** (wyrm) - strength = remaining health, both levels
- [x] Client indicator + tooltip show strength = remaining health.
- [x] Server: `Monster::getBaseAttackStrength` returns remaining health for Wyrm; `Op_monsterAttack::getMonsterStrength` uses it. Test `Campaign_LegendAbilityTest::testNidhuggrAttacksWithRemainingHealth`.


### Rules-sweep gaps (RLIST 2026-06-02)

Surfaced by the 7-agent code-review pass against [RLIST.md](RLIST.md). Cross-refs back to RLIST rule IDs. Bugs at the top, missing rules/options below, behaviour-drift items needing a design decision after that, and untested-but-implemented coverage gaps at the bottom.

#### Bugs (code disagrees with rules)

- [x] **R.20.3 — `Op_adj` ignores hero's own-hex terrain.** Fixed in [Op_adj.php](../../modules/php/Operations/Op_adj.php) — own-hex is now checked before the adjacent-hex loop via a shared `matches()` helper. Quests like Dwarf Mail's `adj(mountain)` now count when the hero stands on the matching terrain. Regression test: `Op_adjTest::testGatePassesWhenHeroIsOnTerrain`.

#### Missing rules / unimplemented options

- [ ] **R.14.1 — No player choice on monster attack resolution order.** [Op_monsterAttackAll.php:24–44](../../modules/php/Operations/Op_monsterAttackAll.php#L24) auto-buckets by hero hex with no prompt. Rule (RULES.md:284): "In whichever order the players decide". Matters when ≥2 monsters attack the same hero and chain effects (Trollkin adjacency, KO timing) depend on order. Distinct from R.14.2 (which monster picks which hero); this one is which monster goes first.
- [ ] **R.21.3 — No gameoption for "skip starting kit"** (start with no gold, mana, or hand). [gameoptions.json](../../gameoptions.json) has only options 100/101/102.
- [ ] **R.21.4 — No gameoption for "start one step up on upgrade-cost track"** to make upgrades more expensive. Same file.


#### Behaviour drift — needs a design decision

- [x] **R.12.2 / R.12.3 — Maneuver uses player order.** Added `Base::getPlayerColorsInOrder()` and switched `Op_monsterDieManeuver::resolve` to use it; the chain now starts at the first player and follows the turn rotation, matching RULES.md:254.
- [x] **R.13.7 — Road-merge tiebreak.** `HexMap::getMonstersOnMap()` now sorts by (distance asc, road desc, hex strcmp). Equal-distance monsters already on a road move before those that aren't, matching RULES.md:269.


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

- [x] **R.6.7** — "Move +1" vs "Move 1 area" is a card-text distinction; per-card sweep across `card_*_material.csv`.
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
- [x] **Audit faction-scoped iteration** — existing iterators already use `getRulesFor(monsterId, "faction")` and filter on `getPart(char, 0) === "monster"`, which includes legends. Confirmed: Trollkin adjacency bonus ([Op_monsterAttack.php:165](../../modules/php/Operations/Op_monsterAttack.php#L165)) counts legend trollkin (Grendel/Hrungbald); Fire Horde range 2 ([Monster.php:28](../../modules/php/Model/Monster.php#L28)) applies to Surt (Seer is Dead, corrected 2026-07-21); Dead rune-as-hit ([Monster.php:65](../../modules/php/Model/Monster.php#L65)) applies to Queen and Seer. Tests in [tests/Model/MonsterTest.php](../../tests/Model/MonsterTest.php) pin the Nidhuggr and Queen behavior.


### Low priority

[ ] Allow moveMonster to push into Grimheim (designer-confirmed) *(matches RLIST **R.22.6**)*
[ ] Share Gold - non-interactive, like "e-mail" it or drop on the board, do not want to stop game to give other player control *(matches RLIST **R.7.7**)*
  [ ] **Remove `Op_performAction` — useless wrapper.** `performAction(X)` does `$this->queue($X)` which the DSL already does for bare `X` (see Speedy Attack `discardEvent:actionAttack` vs Rapid Strike `performAction(actionAttack)` — equivalent). Migrate Rapid Strike I/II, Alva's Bracers, and any other callers to bare-action form, then delete `Op_performAction.php` and the `performAction` row from `op_material.csv`.
[ ] Add `tracker_armor` for consistency with other stats — move armor out of Material-only read path so it can be modified by cards
[ ] Turn signal in title bar — currently says "laskava1 performs an action" using player name; should say "Bjorn / laskava1" or just hero name so the active *hero* is identifiable.
