## TODO



**Bug triage last checked:** 2026-07-22 23:40 EDT - only triage BGA reports created/updated after this time; bump this line (to `date` at run start) after each triage run.

[ ] Quest UI polish: progress badge (`X / N`) on deck-top card, `completeQuest` button styling/icon, completion fanfare animation, enriched log lines (running progress + completion).
[ ] Dice roll animation
[ ] **Card "Sophisticated": Mend throws a SERVER ERROR on click (hero HAD damage).** STILL OPEN. Repro details from Victoria (2026-07-09): hero had 4 damage, had JUST MOVED into Grimheim that same turn, then played Sophisticated; the choice dialog OFFERED Mend, clicking it threw a server error "like it was invalid choice" (checkUserTargetSelection rejection). So the choice was built as valid but re-validated as void on resolve - suspect stale state between args build and resolve (delegate switch `2heal` vs `5removeDamage` depends on hero hex; occupancy/args caching). Path to trace: `Op_or -> Op_actionMend -> 5removeDamage` (`Op_actionMend.php`, `Op_removeDamage.php`). Harness STILL cannot reproduce even with the exact scenario (real move into Grimheim + 4 damage + Sophisticated -> Mend): `Op_actionMendGrimheimMoveTest` is green. `checkUserTargetSelection:01` ("Internal Error") means the picked choice key was MISSING from freshly recomputed moves - `Op_or::getPossibleMoves` returns bare `ERR_COST` when ALL choices are void at resolve time. Remaining suspects: multiplayer state, the real card-play path (costs), playing the card mid step-move, undo savepoint interaction. NEED: exact error text from BGA log / table id.
[x] **Speedy Attack softlock: offered when it's the only card in hand (BGA #233685).** FIXED + deployed in commit d8fe429 ("exclude played card from discard count"). Speedy Attack (`card_event_2_33` / `_3_33`, r=`discardEvent:actionAttack`) over-counted affordability: [Op_discardEvent::getPossibleMoves](../../modules/php/Operations/Op_discardEvent.php#L40) counted the event card itself, so with it as the only hand card the probe saw 1 >= 1 and offered it; at execution [Card::useCard](../../modules/php/Model/Card.php#L281) discards the event card first, leaving `discardEvent` with no target, non-skippable -> softlock. Same class as Inspire Defense #233418 but a different op. Regression test `Campaign_SpeedyAttackSoftlockTest::testSpeedyAttackNotOfferedAsOnlyCardInHand`.



### Rules-sweep gaps (RLIST 2026-06-02)

Surfaced by the 7-agent code-review pass against [RLIST.md](RLIST.md). Cross-refs back to RLIST rule IDs.

#### Missing rules / unimplemented options

- [ ] **R.14.1 — No player choice on monster attack resolution order.** [Op_monsterAttackAll.php:24–44](../../modules/php/Operations/Op_monsterAttackAll.php#L24) auto-buckets by hero hex with no prompt. Rule (RULES.md:284): "In whichever order the players decide". Matters when ≥2 monsters attack the same hero and chain effects (Trollkin adjacency, KO timing) depend on order. Distinct from R.14.2 (which monster picks which hero); this one is which monster goes first.
- [ ] **R.21.3 — No gameoption for "skip starting kit"** (start with no gold, mana, or hand). [gameoptions.json](../../gameoptions.json) has only options 100/101/102.
- [ ] **R.21.4 — No gameoption for "start one step up on upgrade-cost track"** to make upgrades more expensive. Same file.


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
- [ ] **R.13.15** — Suppressive Fire `stunmarker` is only checked in the normal-move loop (correct per rule); no test confirms Monster-Dice rotation / Legend push *bypass* it.
- [ ] **R.15.4** — Legend yellow/red side selection (correct side per turn track) not asserted.

#### Open per-card sweeps (not a code search — needs reading every card's `r` declaration)

- [ ] **R.7.9** — Always-on / on-trigger abilities skipping `spendUse` — correctness depends on each card's `r` declaration.



### Code TODOs / XXX (swept from source on 2026-05-13)

- [ ] [Op_monsterAttack.php:60](../../modules/php/Operations/Op_monsterAttack.php#L60) — Hero selection picks first hex; rules may want closest / random / player choice. **Valid, gameplay-affecting.** *(matches RLIST **R.14.2**)*
- [ ] [Operation.php:790](../../modules/php/OpCommon/Operation.php#L790) — AI/auto-resolve picks one target at random when op expects multi-select. **Valid** — only matters once multi-select ops are auto-resolved (e.g. NPC turns), low impact today.
- [ ] [Game.php:331](../../modules/php/Game.php#L331) — Crystal supply assumes infinite; `pickTokensForLocation` will return fewer than requested if the supply runs out. **Probably valid** — confirm rules cap (do we ever exhaust the supply?) and either log or auto-create.
- [ ] [GameMachine.ts:484](../../src/GameMachine.ts#L484) — `const skippable = false; // XXX` hardcoded in `onMultiSelectionUpdate`. **Valid** — multi-select ops can't currently expose a skip button; revisit if any op needs it.




### Low priority

[ ] Allow moveMonster to push into Grimheim (designer-confirmed) *(matches RLIST **R.22.6**)*
[ ] Share Gold - non-interactive, like "e-mail" it or drop on the board, do not want to stop game to give other player control *(matches RLIST **R.7.7**)*
  [ ] **Remove `Op_performAction` — useless wrapper.** `performAction(X)` does `$this->queue($X)` which the DSL already does for bare `X` (see Speedy Attack `discardEvent:actionAttack` vs Rapid Strike `performAction(actionAttack)` — equivalent). Migrate Rapid Strike I/II, Alva's Bracers, and any other callers to bare-action form, then delete `Op_performAction.php` and the `performAction` row from `op_material.csv`.
[ ] Add `tracker_armor` for consistency with other stats — move armor out of Material-only read path so it can be modified by cards
[ ] Turn signal in title bar — currently says "laskava1 performs an action" using player name; should say "Bjorn / laskava1" or just hero name so the active *hero* is identifiable.
[ ] [Game.php:427](../../modules/php/Game.php#L427) `effect_rollAttackDice` — thread through a "reason"/source (e.g. which card queued the attack) so the roll notification/log explains *why* this attack happened. Debugging Eitri's Pick showed a roll firing with no visible source; a reason field would make the flow self-explanatory.
