## TODO

[ ] Share Gold - non-interactive, like "e-mail" it or drop on the board, do not want to stop game to give other player control
[ ] Fix missing animation when damage crystals are removed from cards (e.g. repairCard/Durability)
[ ] Quest UI polish: progress badge (`X / N`) on deck-top card, `completeQuest` button styling/icon, completion fanfare animation, enriched log lines (running progress + completion).
[ ] Ability pile and equipment pile browsing
[ ] Dice roll animation
[ ] Card draw animations

- [ ] Fix stacked tooltips
- [x] Add hero stats to tooltip (health, attack strength from hero card on tableau)
  [ ] Fix spawn locations in monster cards — current data is not correct
  [ ] Add crystal sprite graphics and update CSS (currently using colored circle placeholders)
  [ ] Show win/loss end screen — BGA default end screen works, custom UI
  [ ] Range indicator for ranged monster attacks
  [ ] Legend monster special display
  [ ] Flip animation for upgrades
  [ ] **Manually test: double-confirm on comma-chained event card rules.** Multi-Shot (`r=2roll(inRange),2roll(inRange)`) creates a `seq` op for the comma-chain. Test via `Campaign_AlvaEventTest::testMultiShotRollsAgainstTwoDifferentMonsters` shows an extra `confirm` step is required after the card pick, before the first sub-op prompts. The root paygain already has `confirm=true` from `Card::useCard`; seq's expandOperation correctly strips confirm from children. Expected UX: click card → prompt for first monster hex (no intermediate confirm). Actual: click card → confirm button → prompt for first monster hex. Verify in the harness whether this is a UX bug (double-click) or intentional. If UX bug, likely fix is in `Op_seq::expandOperation` or how useCard wraps the op.
  [ ] **Remove `Op_performAction` — useless wrapper.** `performAction(X)` does `$this->queue($X)` which the DSL already does for bare `X` (see Speedy Attack `discardEvent:actionAttack` vs Rapid Strike `performAction(actionAttack)` — equivalent). Migrate Rapid Strike I/II, Alva's Bracers, and any other callers to bare-action form, then delete `Op_performAction.php` and the `performAction` row from `op_material.csv`.
[ ] Add `tracker_armor` for consistency with other stats — move armor out of Material-only read path so it can be modified by cards

### Code TODOs / XXX (swept from source on 2026-05-13)

- [ ] [Op_monsterAttack.php:60](../../modules/php/Operations/Op_monsterAttack.php#L60) — Hero selection picks first hex; rules may want closest / random / player choice. **Valid, gameplay-affecting.**
- [ ] [Operation.php:790](../../modules/php/OpCommon/Operation.php#L790) — AI/auto-resolve picks one target at random when op expects multi-select. **Valid** — only matters once multi-select ops are auto-resolved (e.g. NPC turns), low impact today.
- [ ] [Game.php:331](../../modules/php/Game.php#L331) — Crystal supply assumes infinite; `pickTokensForLocation` will return fewer than requested if the supply runs out. **Probably valid** — confirm rules cap (do we ever exhaust the supply?) and either log or auto-create.
- [ ] [Game.php:807](../../modules/php/Game.php#L807) — `restorePlayerTables` is a stub returning false (BGA undo hook). **Valid** — needed if/when full table-state undo is supported.

- [ ] [GameMachine.ts:124](../../src/GameMachine.ts#L124) — "skip, whatever TODO: anytime" inside secondary-button rendering for `paramInfo.sec`. **Unclear** — comment is cryptic, recheck whether the anytime/secondary button path needs work.
- [ ] [GameMachine.ts:484](../../src/GameMachine.ts#L484) — `const skippable = false; // XXX` hardcoded in `onMultiSelectionUpdate`. **Valid** — multi-select ops can't currently expose a skip button; revisit if any op needs it.
- [ ] [Cards.scss:106](../../src/css/Cards.scss#L106) — `.deck.deck_monster { background-image: none; // TODO }` then per-color decks set their own image. **Likely stale** — the `none` is intentional as a base reset; comment can probably just be deleted.
- [ ] [CampaignBase.php:146](../../tests/Campaign/CampaignBase.php#L146) — `getActivePlayerColor` uses current player. **Test-only, probably fine** — works for single-active tests; revisit if multi-active campaign tests need it.
- [ ] [types.d.ts:76](../../src/types.d.ts#L76) — Trailing `XXX` on `err?` field comment with no explanation. **Cosmetic** — drop the marker or explain the concern.
- [ ] [Game.ts:54](../../src/Game.ts#L54) — `onToken` always routes to `playerTurn.onToken`; should dispatch by current state. **Valid** — works today because only playerTurn handles token clicks, but fragile.
- [ ] [DbTokens.php:995](../../modules/php/Db/DbTokens.php#L995) — `isConsideredLocation` matches only exact `type=="location"`; XXX asks about `contains?`. **Valid question** — check whether composite types (e.g. `location,deck`) exist and need to match.

### Missing Campaign tests (swept on 2026-05-17)

34 of 145 cards had no reference in [tests/Campaign/](../../tests/Campaign/). Verifier sweep on 2026-05-17 added integration tests for 29; 5 are blocked on implementation work (see annotations).

Hero cards:
- [x] card_hero_3_1 — Embla Hero I — has tests
- [x] card_hero_3_2 — Embla Hero II — has tests
- [x] card_hero_4_1 — Boldur Hero I — has tests
- [x] card_hero_4_2 — Boldur Hero II — has tests

Ability cards:
- [x] card_ability_2_5 — Treetreader I — has tests
- [x] card_ability_3_7 — Fleetfoot I — has tests
- [ ] card_ability_3_8 — Fleetfoot II — **r mismatch**: missing mountain/occupied-hex passthrough (effect text "may always move into mountains and through occupied areas" not implemented; `r=spendUse:move` identical to Fleetfoot I)
- [x] card_ability_3_13 — Swift Kick I — has tests
- [x] card_ability_3_14 — Swift Kick II — has tests
- [x] card_ability_4_4 — Rapid Strike II — has tests
- [x] card_ability_4_10 — Beefy Berserker II — has tests
- [ ] card_ability_4_12 — Dreadnought II — **r partial**: `spendMana:(preventDamage,custom)` — reflect-damage half undesigned ("Each adjacent monster that attacks you is dealt 1 damage")
- [x] card_ability_4_13 — Fortified I — has tests
- [x] card_ability_4_14 — Fortified II — has tests (calcBaseHealth fixed to aggregate)

Equip cards:
- [x] card_equip_3_15 — Flimsy Blade — has tests
- [x] card_equip_3_26 — Throwing Knives — has tests
- [x] card_equip_4_15 — Boldur's First Pick — has tests
- [x] card_equip_4_26 — Precision Axes — has tests

Event cards:
- [x] card_event_1_28 — Burning Arrows — has tests
- [x] card_event_1_31 — Perfect Aim — has tests
- [x] card_event_3_28 — Kick (Embla) — has tests
- [x] card_event_3_30 — Courage — has tests
- [x] card_event_3_31 — Retaliation — has tests
- [x] card_event_3_32 — Vigilance — has tests
- [x] card_event_3_36 — Durability (Embla) — has tests
- [x] card_event_3_37 — Preparations — has tests
- [x] card_event_4_27 — Miner — has tests
- [x] card_event_4_28 — Short Temper — has tests
- [x] card_event_4_29 — Maneuver — has tests
- [x] card_event_4_30 — Rest — has tests
- [x] card_event_4_31 — Kick (Boldur) — has tests
- [x] card_event_4_33 — Focus — has tests
- [x] card_event_4_34 — Durability (Boldur) — has tests
- [x] card_event_4_37 — Seek Shelter — has tests

