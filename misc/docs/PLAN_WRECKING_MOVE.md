# Plan: Fold Wrecking Ball into Move + turn-level disambiguation

Bugs addressed: #238475 (Wrecking Ball not discoverable), #235051 (Wrecking Ball must work on ALL movement),
plus removal of the Op_c_wrecking / Op_moveStep code duplication.

## Design decisions (agreed)

- Occupied hexes become directly clickable move targets when Boldur has a Wrecking Ball
  card. The card-id button offer (Orebiter pattern) in Op_move / Op_moveStep is removed.
- Wrecking Ball counts as a step incentive: Boldur with the card always moves via Op_moveStep.
- Op_c_wrecking shrinks to the push phase only ("choose where to push X"); its destination-phase
  loop (a clone of Op_moveStep) is deleted. Op name stays c_wrecking.
- Turn-level ambiguity (hex is both attack target and Wrecking Ball move target): server-side, via existing
  Op_or. Op_turn queues expression "actionAttack / actionMove" with data target + spend.
- Action marker: Op_turn no longer places it. Main actions self-spend when queued with data
  "spend" => 1 (explicit flag; do NOT rely on reason). Card-driven performs (Rapid Strike,
  Speedy Attack, Sophisticated, performAction) never pass spend, so they stay free.
- New abstract base AbsOp_action (naming as in bga-scholars) for the 6 Op_action* ops, in
  modules/php/Operations/AbsOp_action.php. NOT in OpCommon (shared layer), NOT in Game class,
  NO OpMachine changes.
- Wrecking Ball targets are occupied hexes within the move budget; a distant one auto-routes
  (walk adjacent via shortest path, then move in). Was adjacent-only at first; changed after playtest - the offer must be visible from the turn prompt.

## Phase A: AbsOp_action + spend flag (standalone refactor) - DONE

- [x] Create AbsOp_action extends Operation with spendTurnSlot(): if getDataField("spend")
      -> placeActionMarker(getType()). (getUiArgs not moved: base default [] differs per op.)
- [x] All 6 Op_action* extend it; each resolve() starts with $this->spendTurnSlot().
- [x] Op_turn::resolve: remove both placeActionMarker calls; add "spend" => 1 to the
      direct main-action queue and the delegate queue (main kind only).
- [x] Add getIconicName() to Op_actionMove.
- [x] Tests: Op_turnTest updated for spend-at-resolve timing;
      Campaign testAmbiguousHexChoosingAttackRollsDice covers marker correctness.
- Behavior change (accepted): slot is spent when the action resolves, not when it is picked.
  A skipped/void action no longer burns a slot.

## Phase B: fold Wrecking Ball into Op_moveStep - DONE

- [x] Op_actionMove::hasStepIncentive: true when hero->getWreckingCard() !== null.
- [x] Op_moveStep::getPossibleMoves: occupied hexes within budget (not Grimheim, not impassable)
      as targets when the card is present and budget > 0; card-id button dropped.
- [x] Op_moveStep::resolve on occupied hex: queue step(final=false), c_wrecking push phase,
      re-queue moveStep with budget-1 / moved+1.
- [x] Op_c_wrecking reduced to the push branch; destination phase / budget loop / endOfMove
      sentinel deleted. HexMap::hasReachableOccupiedHex (now unused) removed.
- [x] Op_move: wrecking wiring removed; unfiltered Op_move with the card delegates to
      moveStep. Preset-target (scripted) moves keep the direct path.
- [x] Tests: Op_c_wreckingTest reduced to push phase; move-into-occupied scenarios in Op_moveStepTest;
      campaign tests rewritten for clickable occupied hexes (pendulum, chain, Grimheim push,
      distant auto-route, plain move).

## Phase C: Op_turn collision -> Op_or - DONE

- [x] Op_turn::getPossibleMoves merge: on double-claim keep first entry, accumulate
      claimants in "actions".
- [x] Op_turn::resolve: 2+ claimants -> queue "actionMove/actionAttack" or-expression with
      ["target" => hex, "spend" => 1]; free branches ignore the flag.
- [x] Misclick recovery is undo (chooser not skippable).
- [x] Tests: campaign tests pick both branches (choice_0 move, choice_1 attack) and assert
      the correct marker is spent.
- [ ] Harness check: or-button labels (Attack / Move) and prompt rendering - visual pass
      still to do (server-side flow is test-covered).

## Follow-up: merge Op_move and Op_moveStep - DONE

One op ("move") instead of two; the split was historical.

- [x] budget became the count (CountableOperation native); "moved" data field stays for taken steps.
- [x] min-count semantics UNCHANGED: "1move" cannot be cancelled, mcount governs skippability as today.
- [x] hasStepIncentive moved out of Op_actionMove into Op_move (isStepMode = incentive AND no filter);
      getDelegateInfo died, actionMove always queues "[1,N]move", card moves get step mode for free.
- [x] Wrecking targets/approach ported; Op_moveStep deleted (op file, material row, client handler,
      test file merged into Op_moveTest).
- Deviations from the original sketch:
  - Op_step "final" branch is NOT dead code: Op_c_queen uses it, and the one-click path keeps a
    final last step so the arrival is still logged (step-mode hops stay silent as before).
  - endOfMove stays a pseudo-target; the skip/getSkipName("End Move") replacement was not taken
    (would churn UI and tests for no behavior gain).

## Open question (Victoria to decide)

Filtered moves (Seek Shelter [0,2]move(locationOnly), Treetreader move(forest)) cannot delegate
to moveStep as-is because the filter constrains the destination, not each step:

- (a) Filtered moves keep one-click Op_move without the Wrecking Ball move for now (CURRENT STATE),
  but regresses deployed behavior where Seek Shelter + wrecking offered the card-button entry.
- (b) Op_moveStep gains an optional destination filter: steps unrestricted, but End Move /
  budget exhaustion only valid on a filter-satisfying hex; dead ends recovered via undo.
  Truer to designer intent (FORUM: "it is always active").

## After completion

- Update DESIGN.md (Wrecking Ball section, Step-by-step Move section) and BUG_TRIAGE.md
  (#238475, #235051), then delete this file per CLAUDE.md planning rule.
