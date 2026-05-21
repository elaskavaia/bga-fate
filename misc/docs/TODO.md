## TODO


[ ] Fix missing animation when damage crystals are removed from cards (e.g. repairCard/Durability)
[ ] Quest UI polish: progress badge (`X / N`) on deck-top card, `completeQuest` button styling/icon, completion fanfare animation, enriched log lines (running progress + completion).
[ ] Ability pile and equipment pile browsing
[ ] Dice roll animation
[ ] Card draw animations
[ ] Show number of houses left in Grimheim
[x] Reshuffle event deck when exhausted


- [ ] Fix stacked tooltips
  [ ] Fix spawn locations in monster cards — current data is not correct
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
- [ ] [GameMachine.ts:484](../../src/GameMachine.ts#L484) — `const skippable = false; // XXX` hardcoded in `onMultiSelectionUpdate`. **Valid** — multi-select ops can't currently expose a skip button; revisit if any op needs it.




### Designer rulings to implement (from DESIGN.md Rule clarifications #7–#11)

- [ ] **Stitching cross-tableau repair (DESIGN.md #8)** — relax `Op_repairCard` so Stitching can target heroes and equipment of *other* players, as long as the owning hero is within range 1. Today it's scoped to acting player's own tableau.
- [x] **Windbite recursive chain (DESIGN.md #9)** — `Op_addRoll::resolve` now counts new runes on the just-rolled dice and re-queues itself with that delta when Windbite is on tableau. Dice stay on display_battle (no consumption), so other rune-readers (Wildfire, Bone Bane Bow) still see them.
- [x] **"Move X" is always "up to X" (DESIGN.md #11)** — Op_move no longer filters reachable hexes to exactly maxSteps; all distances 1..count are offered, so Agility II "Move 2" correctly allows stopping at 1. No other cards needed CSV changes.


### Low priority

[ ] Allow moveMonster to push into Grimheim (designer-confirmed)
[ ] Share Gold - non-interactive, like "e-mail" it or drop on the board, do not want to stop game to give other player control