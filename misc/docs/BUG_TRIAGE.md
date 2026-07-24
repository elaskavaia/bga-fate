# Bug Triage - project config and state

Game-specific configuration and running state for the `game-bug-triage` skill.
The skill itself is game-independent; everything project-specific lives here.
Read this file at the start of every triage run.

## Game reference

- **BGA game id:** 2758 (Fate: Defenders of Grimheim)
- **Bug list:** https://boardgamearena.com/bugs?game=2758
- **Single report:** https://boardgamearena.com/bug?id=<REPORT_ID>

## Rules sources (for `rules`-type reports)

Check a reporter's claim against these, in order, before assuming the code is wrong:

1. [RULES.md](RULES.md) - the rulebook.
2. [FORUM.md](FORUM.md) - designer clarifications and rulings saved from the forum.

## Deploy model

Process: **creating a git tag = deployed.** To decide if a fix is live:

- Find the fix commit: `git log --oneline -i --grep='#<reportid>'` (commit messages reference `BGA #<id>`).
- Is it in the latest tag: `git merge-base --is-ancestor <commit> <latest-tag> && echo LIVE`.
- List tags containing it: `git tag --contains <commit> --sort=creatordate | head`.

In the latest deployed tag -> **Fixed**. Committed/tagged but that tag not deployed -> **Waiting for deploy**. When unsure whether a tag is deployed, ask.

## Investigation and tests

- Integration tests live under `tests/Campaign/`. Targeted unit tests mirror source layout.
- A reproducing test must be **green**, asserting the current buggy behavior, with a comment pinning it for `BGA #<id>` (flip the assertion when the fix lands) - see the skill's CONFIRMED note.

## Bug triage last checked

**2026-07-24 08:39 EDT** - only triage BGA reports created/updated after this time; bump this line to `date` at run start after each run.

## Tracked bugs

Internal record of triaged reports (root cause, fix, test) - never put this detail in a public bug comment.


- [x] **Sure Shot II softlock on overkill target (BGA #233970).** FIXED. Removed the step-2 remaining-health cap in [Op_c_sureshotII::getMaxMana](../../modules/php/Operations/Op_c_sureshotII.php#L51) so max = `min(manaOnCard, 4)` (overkill allowed, rules-consistent). Step 2 now always offers `choice_2..max`, no empty non-skippable target set. Tests: `Op_c_sureshotIITest::testStep2OneHpMonsterStillOffersChoices` (end-to-end step1->step2) and `testStep2AllowsOverkillOnLowHealthMonster`; obsolete cap tests removed; `Campaign_BjornSoloTest::testSureShotIISelectMonsterThenMana` updated (choice_4 now valid).
- [x] **Riposte usable vs non-adjacent (ranged) attacker (BGA #233845).** FIXED. Added an `(adj)` param to Riposte's preventDamage sub-op in the CSV (`r=2spendMana:(2preventDamage(adj):2dealDamage)`, rows 45-46), regenerated Material.php. [Op_preventDamage::getPossibleMoves](../../modules/php/Operations/Op_preventDamage.php#L55) now rejects (ERR_NOT_APPLICABLE) when `param(0)==="adj"` and the attacker hex is not adjacent to the incoming dealDamage's defender hex - which voids the op so `Op_useCard`/`canBePlayed` does not even offer the card. Scoped to Riposte only; Dodge/Stoneskin/Dreadnought carry no param and are unchanged. Tests: `Op_preventDamageTest::testRiposteAdjOfferedWhenAttackerAdjacent`, `testRiposteAdjNotOfferedWhenAttackerNotAdjacent`, `testPreventWithoutAdjParamAppliesRegardlessOfDistance`. NOTE (Victoria): the triage pinning test `Campaign_EmblaAbilityTest::testRiposteIWronglyOfferedAgainstNonAdjacentRangedAttacker` did NOT actually reproduce the bug (the ranged monster closed to melee on its move, attacking from adjacent), so it was replaced by the op-level tests above; a reliable full-turn ranged repro needs the monster's Grimheim-ward move to end at range 2.
- [x] **Sweeping Strike re-offers after all adjacent monsters dead (BGA #233927).** FIXED. Added a `canBePlayed($event)` guard to [CardAbility_SweepingStrikeI::onMonsterKilled](../../modules/php/Cards/CardAbility_SweepingStrikeI.php#L27) (mirrors `CardGeneric::onTriggerDefault`). On a cleave kill `Op_applyDamage` sets marker_attack overkill to 0, so `Op_c_sweep` is void; the guard suppresses the dead-end useCard re-prompt. `CardAbility_SweepingStrikeII` inherits the fix. Test `Campaign_BoldurSweepTest::testSweepingStrikeDoesNotReofferAfterBothMonstersDead` (asserts no useCard re-offer + both monsters swept to supply). NOTE (Victoria - follow-up, pre-existing, out of scope): the "at most 1 cleave hit per attack" hard cap (Op_c_sweep docblock) is enforced NOWHERE in code - it relies on overkill running out. A cleave that leaves LEFTOVER overkill (e.g. target health 1, overkill 2) plus a third live monster on the hero's ring could chain a second cleave. Worth a separate bug to enforce the cap explicitly.

## Triage run log

Short log of each run: date, reports touched, outcome.

- **2026-07-23** - New Boldur reports from Vigilante8 (table 887519986): #233796 Dwarf Mail `${count}` -> Confirmed (green test `Campaign_DwarfMailLogTest`); #233793 Mining Equipment unusable -> Confirmed (green test `Campaign_MiningEquipmentTest`); #233794 health count not refreshing -> left Open (client-side lead, not server-testable). No deploy sweep needed (nothing Waiting for deploy). Marker bumped to 08:32.
- **2026-07-24** - Deploy sweep: tag v260724-0836 shipped fixes for #233794 (health refresh), #233793 (Mining Equipment), #233796 (Dwarf Mail `${count}`) -> all flipped to Fixed and removed from Tracked bugs above. New Open reports triaged and Confirmed via background repro tests (no production code edited): #233970 Sure Shot II overkill softlock (`Op_c_sureshotIITest::testStep2OneHpMonsterHasNoValidChoiceBug`); #233845 Riposte vs ranged/non-adjacent attacker (`Campaign_EmblaAbilityTest::testRiposteIWronglyOfferedAgainstNonAdjacentRangedAttacker`); #233927 Sweeping Strike re-offer loop (`Campaign_BoldurSweepTest::testSweepingStrikeReoffersAfterBothMonstersDead`). #233815 (Undo persists, references fixed #233685) left untouched - Victoria had already replied to the reporter. Marker bumped to 08:39.
