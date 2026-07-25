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

**2026-07-24 19:05 EDT** - only triage BGA reports created/updated after this time; bump this line to `date` at run start after each run.

## Tracked bugs

Internal record of triaged reports (root cause, fix, test) - never put this detail in a public bug comment.


- [x] **Home Sewn Tunic: one mend action removes only 1 damage, not all (BGA #234119).** FIXED, waiting for deploy. Card text ([Material.php:4746](../../modules/php/Material.php#L4746), `card_equip_1_23`): "Spend 1 mend action to remove all damage from this card." Wired by hardcoding the card id (Orebiter precedent, no DSL slot free - `r` is taken by `spendDurab:preventDamage`): [Op_removeDamage](../../modules/php/Operations/Op_removeDamage.php) strips all damage on a Tunic pick, capped by the remaining budget so the rest of Grimheim's 5 stays spendable; [Op_actionMend](../../modules/php/Operations/Op_actionMend.php) also offers the Tunic outside Grimheim (the ability has no location clause), where picking it replaces the normal 2 heal per RULES.md:213. Tests in `Op_actionMendTunicBug234119Test`.
- [ ] **Sweeping Strike "1 cleave hit per attack" cap not enforced in code (follow-up from BGA #233927, pre-existing).** NOT a reported bug yet - flagged during the #233927 fix. The [Op_c_sweep](../../modules/php/Operations/Op_c_sweep.php) docblock claims at most 1 cleave hit per attack, but nothing enforces it; it relies on overkill running out. A cleave that leaves LEFTOVER overkill (e.g. target health 1, overkill 2) with a third live monster on the hero's ring could chain a second cleave. No test yet. Consider enforcing the cap explicitly (dedicated marker/flag) rather than depending on overkill arithmetic.

## Triage run log

Short log of each run: date, reports touched, outcome.

- **2026-07-23** - New Boldur reports from Vigilante8 (table 887519986): #233796 Dwarf Mail `${count}` -> Confirmed (green test `Campaign_DwarfMailLogTest`); #233793 Mining Equipment unusable -> Confirmed (green test `Campaign_MiningEquipmentTest`); #233794 health count not refreshing -> left Open (client-side lead, not server-testable). No deploy sweep needed (nothing Waiting for deploy). Marker bumped to 08:32.
- **2026-07-24** - Deploy sweep: tag v260724-0836 shipped fixes for #233794 (health refresh), #233793 (Mining Equipment), #233796 (Dwarf Mail `${count}`) -> all flipped to Fixed and removed from Tracked bugs above. New Open reports triaged and Confirmed via background repro tests (no production code edited): #233970 Sure Shot II overkill softlock (`Op_c_sureshotIITest::testStep2OneHpMonsterHasNoValidChoiceBug`); #233845 Riposte vs ranged/non-adjacent attacker (`Campaign_EmblaAbilityTest::testRiposteIWronglyOfferedAgainstNonAdjacentRangedAttacker`); #233927 Sweeping Strike re-offer loop (`Campaign_BoldurSweepTest::testSweepingStrikeReoffersAfterBothMonstersDead`). #233815 (Undo persists, references fixed #233685) left untouched - Victoria had already replied to the reporter. Marker bumped to 08:39.
- **2026-07-24 (2nd run)** - Deploy sweep: Victoria fixed the 3 Confirmed bugs and tag v260724-1039 shipped them -> #233970 (Sure Shot II overkill), #233845 (Riposte adjacency), #233927 (Sweeping Strike re-offer) all flipped Confirmed -> Fixed, removed from Tracked bugs. Kept a new follow-up entry: Sweeping Strike cleave-cap not enforced in code (out of scope of #233927). No new reports since marker; #233815 (Undo) still Open, only prior dev reply, left as-is. Marker bumped to 10:42.
- **2026-07-24 (3rd run)** - One new report: #234119 Home Sewn Tunic mend removes only 1 damage instead of all -> investigated (background agent), CONFIRMED with green test `Op_actionMendTunicBug234119Test::testMendOnTunicRemovesOnlyOneDamageInsteadOfAll`; the "remove all with one mend" ability is entirely unwired. Fix approach chosen by Victoria: hardcode the Tunic case in the Mend action. #233815 (Undo) now Info needed (Victoria's action), no new reporter activity. Marker bumped to 19:05.
