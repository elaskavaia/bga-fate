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
  [ ] Visual display for stunmarker — currently the stun token sits on the monster but has no distinct UI (looks like any other token on the monster). Need a clear indicator (e.g. a halo, icon overlay, or position offset) so the player can see at a glance which monsters are stunned this turn.
  [ ] Flip animation for upgrades
  [ ] **Manually test: double-confirm on comma-chained event card rules.** Multi-Shot (`r=2roll(inRange),2roll(inRange)`) creates a `seq` op for the comma-chain. Test via `Campaign_AlvaEventTest::testMultiShotRollsAgainstTwoDifferentMonsters` shows an extra `confirm` step is required after the card pick, before the first sub-op prompts. The root paygain already has `confirm=true` from `Card::useCard`; seq's expandOperation correctly strips confirm from children. Expected UX: click card → prompt for first monster hex (no intermediate confirm). Actual: click card → confirm button → prompt for first monster hex. Verify in the harness whether this is a UX bug (double-click) or intentional. If UX bug, likely fix is in `Op_seq::expandOperation` or how useCard wraps the op.
  [ ] **Remove `Op_performAction` — useless wrapper.** `performAction(X)` does `$this->queue($X)` which the DSL already does for bare `X` (see Speedy Attack `discardEvent:actionAttack` vs Rapid Strike `performAction(actionAttack)` — equivalent). Migrate Rapid Strike I/II, Alva's Bracers, and any other callers to bare-action form, then delete `Op_performAction.php` and the `performAction` row from `op_material.csv`.
[ ] Add `tracker_armor` for consistency with other stats — move armor out of Material-only read path so it can be modified by cards
