# Bug Triage - project config and state

Game-specific configuration and running state for the `game-bug-triage` skill.
The skill itself is game-independent; everything project-specific lives here.
Read this file at the start of every triage run.

## Game reference

- **BGA game id:** 2758 (Fate: Defenders of Grimheim)
- **Bug list:** https://boardgamearena.com/bugs?game=2758
- **Single report:** https://boardgamearena.com/bug?id=<REPORT_ID>
- **Studio error log:** https://studio.boardgamearena.com/studioissues?game=fate - studio short name is `fate`, not the numeric id above. Add `&tableId=<TABLE_ID>` for one table, `&id=<ERROR_ID>` for one error, `&projects=frontend|backend` to filter. Verified working 2026-08-17.
- **Table game log:** `GET /archive/archive/logs.html?table=<TABLE_ID>&translated=true&from=<packet_id>` (needs `x-request-token`), paginate by the last `packet_id` + 1. Undone moves are missing from it.
- **Designer:** BGA user **Lord Aethan** (player id 91033064) is the game designer. His reports are design rulings, not player claims - never post comments or change status on his reports without Victoria's explicit go-ahead.

## Rules sources (for `rules`-type reports)

Check a reporter's claim against these, in order, before assuming the code is wrong:

1. [RULES.md](RULES.md) - the rulebook - you must read this no matter what you doing.
2. [FORUM.md](FORUM.md) - designer clarifications and rulings saved from the forum.

## Deploy model

Process: **creating a git tag = deployed.** To decide if a fix is live:

- Find the fix commit: `git log --oneline -i --grep='#<reportid>'` (commit messages reference `BGA #<id>`).
- Is it in the latest tag: `git merge-base --is-ancestor <commit> <latest-tag> && echo LIVE`.
- List tags containing it: `git tag --contains <commit> --sort=creatordate | head`.

In the latest deployed tag -> **Fixed**. Committed/tagged but that tag not deployed -> **Waiting for deploy**. When unsure whether a tag is deployed, ask.

## Investigation and tests

- All investigation (code reading, logs, repro tests) goes to background agents; the triage session only reads reports and decides status.
- Integration tests live under `tests/Campaign/`. Targeted unit tests mirror source layout.
- A reproducing test must be **green**, asserting the current buggy behavior, with a comment pinning it for `BGA #<id>` (flip the assertion when the fix lands) - see the skill's CONFIRMED note.

## Bug triage last checked

**2026-11-07 08:37 EST** - only triage BGA reports created/updated after this time; bump this line to `date` at run start after each run.

## Tracked bugs

Internal record of triaged reports (root cause, fix, test) - never put this detail in a public bug comment. Entries are removed once Fixed; history is in git and the run log.

Priority (set on triage, revisit each run): **P1** error / stuck UI, or a cheap fix with broad effect; **P2** rules wrong (for or against players); **P3** large feature-sized work or display only. No tag = waiting on info or a ruling.

### Confirmed (green pinning test in tree)

- [x] P1 **#250666 Windbite crashes on a Throwing Darts rune.** FIXED on main 2026-11-07 (e893e7c), not deployed -> flip with the next tag. A standalone hero roll now sets `marker_attack` and queues `endOfAttack` with `free=true` (cleanup without `TAfterActionAttack`); Smiterbiter ignores free rolls; Windbite void on an empty attack hex (unsticks live tables). Windbite now applies to every roll - FORUM.md "## More questions...", designer: "The Windbite effect applies all the time." Tests: `Campaign_AlvaEquipTest::testWindbiteOnThrowingDartsRuneAfterEarlierDartsKill`, `::testThrowingDartsDoesNotTriggerElvenBlade`, `Campaign_BoldurEquipTest::testSmiterbiterIgnoresOverkillFromFreeActionRoll`, `Op_addRollTest::testVoidWhenAttackHexIsEmpty`. The `free` flag on endOfAttack is the cheap "in an attack action" check #254509 needs. Table 930394670.
- [ ] P3 **#251068 / #251630 Starsong vs upgrade order.** Tests `Campaign_StarsongUpgradeTest::testStarsongGainedInUpgradeDoesNotDrawExtraSameTurn`, `::testStarsongDrawsBeforeUpgradeOnLaterTurns`. [Op_turnEnd](../../modules/php/Operations/Op_turnEnd.php) queues `trigger(TTurnEnd)` before `upgrade`. RULED 2026-10-19 (Victoria): Starsong fires at step 4 with the draw; designer reportedly agrees (per Peter_panq citing BGG 3785234, unverified, not in FORUM.md). FIX: TTurnEnd after upgrade + mana, before drawEvent. #251068 **Confirmed** 2026-11-07.
- [ ] P3 **#240269 (dup #251273) Trollkin attack label stale after a kill (client).** Test `src/tests/TrollkinLabel.spec.ts`. `prepareToken` ([src/Game1Tokens.ts](../../src/Game1Tokens.ts)) refreshes labels before `slideAndPlace` moves the token. FIX: refresh after the DOM move for monster tokens.
- [ ] P2 **#251878 Seek Shelter does not stop later card moves.** Test `Campaign_Bug251878Test::testManeuverStillMovesAfterSeekShelter`. FORUM.md "## Seek Shelter", designer: "the restriction applies after using the card". FIX: per-turn "no more movement" flag checked by [Op_move](../../modules/php/Operations/Op_move.php) (not the move tracker).
- [ ] P2 **#251884 Sweeping Strike leftover ignores Draugr armor.** Test `Campaign_BoldurSweepArmorTest::testSweepIgnoresDraugrArmor`. [Op_c_sweep](../../modules/php/Operations/Op_c_sweep.php) queues `applyDamage` directly. FIX: shared armor helper; `Op_c_nailed`, `Op_spendHealth` likely same.
- [ ] P2 **#251438 Hero spawn can place a monster on a lake.** Test `Op_spawnLakeTest::testSpawnFromTrollCavesOffersLakeHex`. [Op_spawn::getFreeAdjacentHexes](../../modules/php/Operations/Op_spawn.php) ignores terrain. FIX: `hexMap->canStopOn`, decide Nailfare for monsters.
- [ ] P3 **#251892 Monster next to two heroes auto-picks its target.** Test `Campaign_Bug251892Test::testMonsterAdjacentToTwoHeroesAttacksWithoutPrompt`. [RULES.md:284](RULES.md#L284) "the players decide which one is attacked". [Op_monsterAttack::pickTarget](../../modules/php/Operations/Op_monsterAttack.php) takes `$hexes[0]`. Open: who decides in multiplayer; attack order too.
- [x] P1 **#251305 Extra prevention prompt after damage fully prevented.** FIXED on main 2026-11-07 (264065d, 250171a), not deployed -> flip with the next tag. By design (Victoria): `useCard` re-queues itself and shows greyed cards that explain why on click - both kept. Fix is narrow, in [Op_preventDamage](../../modules/php/Operations/Op_preventDamage.php): once effective damage (after armor) is 0, drop the owner's pending useCard with `on` exactly [TResolveHits] if it has no valid target (Retaliation stays). Tests: `Campaign_BjornEquipTest` (no prompt after full prevention, second card while damage remains, greyed re-queue on attack prompt), `Campaign_BoldurEquipTest::testNoPreventionPromptWhenArmorAbsorbsTheRest`, `Campaign_EmblaEventTest::testRetaliationStillOfferedAfterHelmetPreventsTheHit`, `Op_preventDamageTest` on-filter pair.
- [x] P1 **Clicking a greyed card plays the only valid one (found fixing #251305, no report).** FIXED on main 2026-11-07 ("fix: validate an explicit target even when only one is valid"), not deployed. `Operation::_getCheckedArg` no longer swaps in the single valid target for an explicit target; the shortcut stays for no-target sends (auto-resolve, Confirm). Same as bga-euro 424162f. Test `Op_useCardTest::testGreyedCardClickRejectedWhenOneCardIsValid`; 6 campaign tests were relying on the swap (fixed). Open design call: a target missing from the op info now hits systemAssert "Internal Error" on single-target ops too (stale UI / double click) - maybe downgrade to userAssert. Server only, not browser-verified.
- [ ] P3 **#251864 Town Piece counter not updated on add.** Test `Campaign_Bug251864Test::testInspireDefenseDoesNotNotifyHouseCounter`. FIX: shared counter notify for add/destroy. Open: may pieces exceed the starting count.
- [ ] P2 **#254509 Nailed Together offered after a free-action kill.** Test `Campaign_Bug254509Test::testNailedTogetherOfferedAfterSureShotKill`. [RULES.md:191](RULES.md#L191) free actions never count as attack actions. [Op_applyDamage](../../modules/php/Operations/Op_applyDamage.php) sets `marker_attack` for any damage. FIX: set marker/overkill/Smiterbiter excess only during an attack. Same gap: Nailed II, Sweeping I/II, Smiterbiter.
- [ ] P2 **#252904 Precision Axes unpreventable damage stopped by Draugr armor.** Test `Campaign_Bug252904Test::testPrecisionAxesUnpreventableVsDraugrArmor`. [RULES.md:410](RULES.md#L410). FIX: unpreventable dealDamage variant.
- [ ] P2 **#253990 (dup #255176) Dwarf Mail prevents on every attack in a monster turn.** Test `Campaign_Bug253990Test::testDwarfMailOfferedAgainOnSecondAttackInOneMonsterTurn`. FORUM.md "## Dwarf Mail": "Effectively, this will prevent 1 damage per monster turn." FIX: per-monster-turn use tracking. UX: auto-apply?
- [x] P1 **#253727 / #253962 Highlands has 10 areas, cards need 12.** FIXED on main 2026-11-07 (f5de8de), not deployed -> flip both with the next tag. Data only: hex_7_16 + hex_8_16 tagged Highlands (Victoria confirmed), Surt `----ELJ---E-`, Burnt Offerings `---ESE---E-E`, Fiery Projectiles padded. Player-visible: 8 Highlands cards (4, 7, 8, 10, 11, 42, 43, 44) never spawned before, incl. Surt and Nidhuggr II - release-note line. Tests: `Op_reinforcementTest` Highlands area order + per-card placement; length test now covers every location.
- [ ] P2 **#253550 Mastery double-counts Singing Bow quest dice.** Test `Campaign_Bug253550Test::testMasteryAddRollDoubleCountsSingingBowProgress`. `countDice` counts all dice on `display_battle`. FIX: `countNewDice` high-water mark. Windbite likely same.
- [ ] P2 **#253223 Home Sewn Cape quest offered with a monster adjacent.** Test `Campaign_BjornQuestTest::testHomeSewnCapeQuestBlockedWhenMonsterAdjacent`. [Op_check](../../modules/php/Operations/Op_check.php) always returns `confirm`. FIX: ERR_PREREQ when false; rewrite the test end then.
- [x] P1 **#251077 Undo after a quest rewinds the revealed equipment.** FIXED on main 2026-11-07 (f35c946), not deployed -> flip with the next tag. [Op_gainEquip](../../modules/php/Operations/Op_gainEquip.php) no longer auto-resolves: "Complete quest" confirm, then gain + reveal + undo barrier. Player-visible: optional quests now ask yes/no then confirm (release-note line). Test `Campaign_UndoTest::testUndoAfterCompletingAQuestKeepsTheRevealedEquipment`. Upgrade gain barrier fixed separately (see #236178).
- [ ] P2 **#252286 Tiara pass-through pickup.** Test `Campaign_AlvaQuestTest::testTiaraPassingThroughDarkForestMidMove`. FORUM.md "## Alva - Tiara in the Dark Forest": "if you are in the Dark Forest at any time, you may find it". FIX: `quest_on=TStep` + genmat. Edge: starting in the forest without moving.
- [ ] P2 **#254830 Seek Shelter playable with no location in reach, then fizzles.** Test `Campaign_Bug254830Test::testSeekShelterWithNoLocationInReachIsPlayedForNothing`. [FORUM.md:3326](FORUM.md#L3326) "you must end in the location". `mcount==0` makes the filtered move skippable so it is never void. FIX: filtered Op_move is void with no target (Treetreader same). Open: may a card with no legal effect be played.

- [x] P1 **#255238 Boneyard / Marshing spawn on shifted Marsh of Sorrow hexes.** FIXED on main 2026-11-07 (697f92c, 2f7f997, e4cfeb1), not deployed -> flip to Waiting for deploy / Fixed with the next tag. Data only in [misc/monstercard_material.csv](../monstercard_material.csv): Boneyard `----S-SI-S-S`, Marshing `DS--II-I---I`; also Forest Patrol (35, Dark Forest) was shifted - now brute on hex_6_4, goblin on hex_7_4 (player-visible, release-note line). 13 other short strings padded only (no behavior change; Hrungbald 5 least certain). Tests in `Op_reinforcementTest` (placement + `testSpawnStringLengthMatchesLocationHexCount`). Table 944060149.

### Open / Info needed

- [ ] P3 **#255225 Share Gold not available.** Not implemented: `#shareGold` commented out in [misc/op_material.csv](../op_material.csv), [TODO.md](TODO.md) "Share Gold - non-interactive". [RULES.md:189](RULES.md#L189) "You may give or receive gold (yellow) from any adjacent hero." Known, on [TODO.md](TODO.md) Low priority; Victoria: not implemented yet. **Confirmed** 2026-11-07 (known gap, no test). Table 943996516.
- [ ] P3 **#255224 Cannot choose hero in multiplayer.** Heroes shuffled in [Game.php](../../modules/php/Game.php) setup (`bgaShuffle(range(1, 4))`); only the solo option 100 picks a hero. [RULES.md:143](RULES.md#L143) "Each player chooses one of the hero decks". Victoria: random for now, added to [TODO.md](TODO.md) Low priority. **Confirmed** 2026-11-07 (known gap, no test). Table 943996516.

- [ ] **#252119 Tiara same-turn gold.** No ruling, Open, no comment.
- [ ] **#252103 Bonus crystals from the exact area (Portuguese).** Info needed since 2026-11-04 (EN + PT comment). Claim 1 contradicts the designer (FORUM.md: resources sit on a specific area); claim 2 too vague.
- [ ] P3 **#251306 Equipment pile shows old top card after demote (client).** Info needed. Lead: `slideAndPlace` returns early when the parent is unchanged, so a cycled pile does not reorder. Reporter: happens after cycling the whole deck, F5 fixes it. No JS test yet.
- [ ] **#236791 Sweeping Strike re-kills the same monsters.** Re-entry hypothesis DISPROVED (`Campaign_BoldurSweepTest::testSweepMultiKillScoresEachMonsterOnce`, `spendsExcess` survives the queue). Untested: 4-player, Sweeping II + Rapid Strike II. Ask: exact log lines, did XP/gold really rise, Smiterbiter in play. Table 893045130.
- [ ] **#236519 / #235314 No way to back out of a targeting prompt (event card, Move without stepping).** Likely nothing to undo (turn 1, draw barrier). Victoria 2026-11-07: not enough information to decide on cancel/back - needs reporter detail (which card / prompt, what they wanted to back out to).
- [ ] **#236178 Undo refused after an Upgrade.** Cannot reproduce on current code: undo rewinds an upgrade up to the drawEvent confirm ("draw" barrier), refused only after it - by design. Likely predates #238872 / #240644 or was a post-draw undo. Test `Campaign_UndoTest::testUndoAfterImprovingACardRewindsIt`. TODO on BGA (needs browser): set **Cannot reproduce**. Table 893187904.
  - Rule (Victoria 2026-11-07): every reveal of hidden or random info (card reveal, draw, shuffle, dice) writes an undo barrier. Audit found one gap, fixed unreported on main 2026-11-07 ("fix: make gaining an ability an undo barrier"), not deployed: [Op_upgrade](../../modules/php/Operations/Op_upgrade.php) `resolveGain` now writes an "upgrade" barrier after revealing the next ability. Test `Campaign_UndoTest::testUndoAfterGainingAnAbilityKeepsTheRevealedCard`. Player-visible: upgrade prompt adds "(this cannot be undone)" (re-translation needed; slightly wrong when the last ability is taken) and a new "reveals new" log line. Not fixed: multiplayer reinforcement with no seat switch (only one seat left) gets no barrier before input.
- [ ] **#235051 Wrecking Ball on all movement.** FORUM.md designer: "Yes, it is always active". Unfiltered moves done; filtered (Seek Shelter, Treetreader) still open, see [PLAN_WRECKING_MOVE.md](PLAN_WRECKING_MOVE.md).

### Unreported / follow-ups

- [ ] **Treetreader lets Alva walk out of Grimheim.** [FORUM.md:7036](FORUM.md#L7036) "may move into Grimheim but not out of it". Branch `move(forest)` ungated; needs a negated gate. No test.
- [ ] **`killed(<expr>)` quest predicates score the wrong monster in a multi-kill.** [Op_killed](../../modules/php/Operations/Op_killed.php) reads `marker_attack` after it moved. FIX: carry the dying monster id in the trigger data; assert `$expectedTracker` in `Campaign_BjornQuestTest`.
- [ ] **Legend card returns to the deck while its Legend is alive.** [RULES.md:383](RULES.md#L383). `Op_turnMonster::cleanupMonsterDisplay` moves Legend cards too. No test.
- [ ] **"Multiple conceder team found" in `Table::completeGameResult` (studio log).** Framework error, ~25 tables/week, no report. Hypothesis: all-0 scores on a coop loss.
- [ ] P2 **Hero on the exact Grimheim hex blocks an entering monster.** RULED 2026-11-07 (Victoria): heroes in Grimheim never block, the monster enters. [Op_monsterMoveAll](../../modules/php/Operations/Op_monsterMoveAll.php) `moveMonsterOneStep` checks `isOccupied` before Grimheim entry. FIX: Grimheim entry check before the occupied check. No test.
- [ ] **Undo hygiene.** [DbMultiUndo.php](../../modules/php/Db/DbMultiUndo.php) `doSaveUndoSnapshot` clears all players' rows at `player_id == 0` (two monster-turn callers pass `getActivePlayerId()` unchecked) - assert instead; `if ($hasmove)` falsy for "0"; [Base.php](../../modules/php/Base.php) `doUndoSavePoint` swallows a failed write; one pending `undoSavepointMeta` per request. NOT a diagnosis for any report. Back-port the immediate-savepoint pattern to euro / scholars.
- [ ] **Misc leftovers.** Smiterbiter CSV text differs from the printed card. Knock-out return fires no TStep; push queues no `encounter`. Quest revealed mid-move: same move usable? Stub `TableStats::set` accepts bool. Queen II leaving through Grimheim leaves boosted monsters at 0 health. Cascaded kills fire no TMonsterKilled. `debug_reinforcement` Legend skip untested. Solo barrier at the start of `Op_turnMonster`. Black Arrows once-per-turn like Quiver; recheck Fire Spark / Elementary Student with hero 5. Back-port the genmat `"` escape to bga-euro.

## Triage run log

Short log of each run: date, reports touched, outcome.

- **2026-11-07** - 5 new reports, no Waiting-for-deploy rows. #255176 -> **Duplicate** of #253990. #255238 -> **Confirmed** (agent, Boneyard + Marshing spawn strings shifted). #255184 -> **Not a bug** (Leather Purse was the active quest; its quest spawns 2 brutes on a trollkin kill). #255225 (Share Gold), #255224 (hero choice): not implemented, on TODO.md, "on our list" comment posted, then **Confirmed** (Victoria's call, known gap, no test). #251068 Open -> **Confirmed** (ruled 10-19, matches #251630). File compacted. Marker bumped.

- **2026-11-05** - #254830 English translation + **Confirmed**. #254408 reopened, then closed Fixed by Victoria (card art is English, only the tooltip is translatable). Marker bumped.
- **2026-11-04** - Deployed **v261105-0350**: #251284, #251620, #254408 -> Fixed. 8 reports Confirmed with pinning tests (#254509, #252904, #253990, #253727, #253962, #253550, #253223, #251077 reopened, #252286). #252103 -> Info needed.
- **2026-10-24** - v261020-0432 sweep: #236814, #245127, #251077 -> Fixed. 9 Confirmed. Not a bug: #236916, #251412. Works for me: #234817. #251273 Duplicate of #240269.
- Older runs (2026-08-16 .. 2026-10-19): in git history of this file.
