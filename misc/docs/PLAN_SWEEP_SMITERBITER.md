# Plan: Sweeping Strike - passive bonus, and arbitrating leftover damage with Smiterbiter

Status: proposal, not started. Both came out of BGA #235866.

Two independent changes. Change 1 is small, safe, and addresses what the reporter actually
complained about - ship it first. Change 2 is a real rules violation found while
investigating, and needs more care.

- Change 1: stop asking whether to apply Sweeping Strike's "+1 damage" bullet. Just apply it.
- Change 2: stop Sweeping Strike and Smiterbiter both claiming the same leftover damage.

---

# Change 1 - make the "+1 damage" bullet passive

## What the reporter asked for

BGA #235866, ysubmarine: "I also think this ability is completely passive and should be coded
as such. My Suggestion: 1.: add+1 dmg to each Attack Action (passive) 2.: after target is
killed offer to Deal damage to second Monster in clockwise Order. Currently there is the
selection between +1 damage and damage to second Monster. This Window Pops Up before having
dealt damage."

## Why the rules allow it

Card text, [card_ability_material.csv:58](../card_ability_material.csv#L58): "Add 1 damage to
each attack action." There is no "may" on this bullet, unlike the cleave bullet. It is not
optional, so we are not required to offer a choice.

[RULES.md:420](RULES.md#L420): "An ability can only be used once per turn, but this does not
apply to abilities that give you permanent upgrades of attack strength, health, etc. or that
trigger on other actions."

## The change

[CardAbility_SweepingStrikeI](../../modules/php/Cards/CardAbility_SweepingStrikeI.php) already
exists and its `onActionAttack` hook does one thing - call `promptUseCard`. Queue the
`addDamage` effect directly from that hook instead of prompting. Leave `onMonsterKilled`
alone, so the cleave keeps its "may" prompt.

Apply the same change to
[CardAbility_SweepingStrikeII](../../modules/php/Cards/CardAbility_SweepingStrikeII.php),
whose bullet is the same effect scaled by adjacent monsters. Changing one and not the other
would make the pair behave inconsistently.

## What this also fixes for free

With no attack-time prompt, the confusing screen from the bug report disappears: today the
player is shown the whole card with the cleave bullet greyed out (correctly - nothing has
died yet), reads it as broken, and never goes looking for the second prompt. This is the
"Sweeping Strike shows a permanently-unreachable grayed choice" entry in
[BUG_TRIAGE.md](BUG_TRIAGE.md), resolved as a side effect.

## Risk

Is the +1 ever unwanted? No case found: it adds damage rather than dice, so it cannot change
a roll, and extra damage only feeds the cleave or Smiterbiter. Confirm before making it
unskippable, since a passive effect cannot be declined.

## Tests

- Attack with Sweeping Strike in play: assert the damage is applied with no prompt.
- Assert the cleave prompt still appears after a kill with leftover damage.
- Same pair for Sweeping Strike II, where the bonus scales with adjacent monsters.

---

# Change 2 - arbitrate leftover damage between the cleave and Smiterbiter

## The problem in one sentence

Three cards all want the same leftover damage after a kill, they all read one shared counter,
none of them reduces it, so the same damage gets spent twice.

## What the rules say

Card text, [card_ability_material.csv:58](../card_ability_material.csv#L58) - Sweeping Strike:
"If an adjacent monster is killed in your attack action, any remaining damage **may** be
dealt to a second monster in clockwise order."

Card text, [card_equip_material.csv:58](../card_equip_material.csv#L58) - Smiterbiter:
"If you kill a monster in an attack action, excess damage **is** stored here (max 3 stored)."
Victoria confirmed the printed card says "is", not "may".

Designer ruling, [FORUM.md:5232](FORUM.md#L5232), thread "Smiterbiter + Sweeping Strike, what
to do exactly with excess/remaining damage":

> "You played correctly. 'Excess damage' is basically damage beyond what could be dealt, but
> if your sweeping strike goes against a second monster, the damage is dealt to that monster,
> so it isn't 'excess damage'. If you deal 5 damage and cut through 2 monsters with 2 health
> each, you would have 1 'excess damage'."

So the intended model is a waterfall, not a race:

1. Damage is dealt to the first monster. It dies, some damage is left over.
2. Sweeping Strike (or Nailed Together) may spend that leftover on another monster. The
   player chooses, because the card says "may".
3. Whatever is still left when the attack is over is "excess". Smiterbiter banks it, with no
   choice, because the card says "is".

Step 3 is defined by what happened in step 2. That is the whole point: excess is the
remainder, not a parallel claim on the same pool.

## What the code does today

The leftover lives in the `marker_attack` token: location is the hex that was hit, state is
the overkill amount.

- Written only by [Op_applyDamage.php:90](../../modules/php/Operations/Op_applyDamage.php#L90),
  as `-$result["remaining"]`. On a kill that is positive (overkill); on a survivor it is
  negative (health still to go).
- Cleared by [Op_endOfAttack.php:16](../../modules/php/Operations/Op_endOfAttack.php#L16).

Three consumers read it and **none of them writes it back**:

- [Op_c_sweep.php:39](../../modules/php/Operations/Op_c_sweep.php#L39)
- [Op_c_nailed.php:37](../../modules/php/Operations/Op_c_nailed.php#L37)
- [CardEquip_Smiterbiter.php:39](../../modules/php/Cards/CardEquip_Smiterbiter.php#L39)

So on one kill with 4 left over, Smiterbiter banks 3 AND Sweeping Strike still sees 4 to
cleave with. Both effects happen. That contradicts the ruling above.

## Why we cannot fix this by ordering the two cards

Two separate obstacles, and this is the part that makes "just make sweep go first" not work:

1. `Op_trigger::resolve` walks the tableau in whatever order the token query returns and
   calls `onTrigger` on each card. There is no priority field and no guaranteed order
   between two cards listening to the same trigger.
2. Even with an order, the two cards are not the same *kind* of thing. Smiterbiter does its
   work inline, synchronously, inside that loop. Sweeping Strike only queues a prompt, which
   resolves after the loop has finished. Inline always beats queued regardless of iteration
   order.

Fixing this by introducing card priority would mean inventing a mechanism and making both
cards symmetric first. The plan below avoids needing either.

## Why `marker_attack` cannot answer the question on its own

`marker_attack` is a *last hit* register, not a running total. Every `Op_applyDamage`
overwrites it ([Op_applyDamage.php:90](../../modules/php/Operations/Op_applyDamage.php#L90)).
Excess, as the designer defines it, is a property of the whole attack action, and one attack
action can contain several damage applications:

- Sweeping Strike / Nailed Together cleave: a *waterfall*. The leftover from kill 1 becomes
  the damage of hit 2. The register is rewritten, and rewriting is exactly right here -
  reading only the last value gives the correct answer.
- Reaper Swing splits one attack into two independent hits
  ([Op_resolveHits.php:104-105](../../modules/php/Operations/Op_resolveHits.php#L104-L105)).
  Two separate kills, two separate leftovers. Reading only the last value *loses* the first.
- A card used mid-attack that deals damage (Precision Axes, `dealDamage(adj)`) also rewrites
  the register, and can zero out a leftover the attack had genuinely produced.

So "read `marker_attack` at end of attack" is only correct when the attack has exactly one
leftover pool. That happens to be true for Boldur today (Reaper Swing is Embla's ability, and
equipment decks are per-hero, so `card_equip_4_21` can only ever sit in Boldur's tableau), but
it is an unwritten invariant that any future card can break silently, in the direction of
quietly losing the player's damage.

Track the total instead - and track it on the card that owns the concept.

## The proposed change

Excess is what the attack produced and nothing spent. Accumulate it on Smiterbiter itself as
*pending* damage during the attack, then commit it at the end of the attack. Passive, no
trigger, no new token.

Pending damage is held as **yellow** crystals on `card_equip_4_21`; committed damage stays
**red**, exactly as today.

Yellow is safe there. XP is only ever counted in `tableau_$owner`
([Op_spendXp.php:29](../../modules/php/Operations/Op_spendXp.php#L29),
[Op_upgrade.php:78](../../modules/php/Operations/Op_upgrade.php#L78)), and `spendGold` is
card-scoped to the host card of the rule that invokes it
([Op_spendGold.php:29](../../modules/php/Operations/Op_spendGold.php#L29)), which for
Smiterbiter is `c_smiter`. Nothing can reach or spend yellow sitting on this card.

Green would not be safe: `Op_spendManaAny` sweeps every tableau card and offers any that holds
green ([Op_spendManaAny.php:41-48](../../modules/php/Operations/Op_spendManaAny.php#L41-L48)),
so pending excess parked as mana would become free mana. Tagging red crystals with a state
would also work - the token query supports a state filter
([DbTokens.php:452](../../modules/php/Db/DbTokens.php#L452)) - but it fails open: every
existing red count passes no state, meaning "any state", so one forgotten filter silently turns
pending damage into banked damage. Yellow fails safe: a call site nobody updated simply does
nothing.

### Step 1 - deposit pending damage on the kill

In [Op_applyDamage.php:90](../../modules/php/Operations/Op_applyDamage.php#L90), when a kill
leaves positive overkill, put that many yellow crystals on Smiterbiter if the hero has it. This
is a hardcoded card check in the pipeline, the same shape as Dreadnought II in
[Op_monsterAttack.php:81](../../modules/php/Operations/Op_monsterAttack.php#L81) and Wrecking
Ball in [Op_actionMove.php:82](../../modules/php/Operations/Op_actionMove.php#L82). Keep the
logic itself as methods on
[CardEquip_Smiterbiter](../../modules/php/Cards/CardEquip_Smiterbiter.php) so card behaviour
stays in the card file.

`marker_attack` keeps its current meaning and its current writers - the cleave ops still read
it to size their hit.

### Step 2 - withdraw what the cleave actually deals

[Op_c_sweep.php:78](../../modules/php/Operations/Op_c_sweep.php#L78) and
[Op_c_nailed.php](../../modules/php/Operations/Op_c_nailed.php) flag the damage they queue as
drawn from the pending pool. `Op_applyDamage` does the withdrawal itself when that damage
lands - after the Queen I guard
([Op_applyDamage.php:52](../../modules/php/Operations/Op_applyDamage.php#L52)), not before.
That damage is being dealt, so it stopped being excess. This is the write-back all three
consumers are missing today.

Withdrawing where the damage lands rather than where it is queued means a refused chain link
(Nailed Together reaching a Queen I two hexes from the hero) is never withdrawn, so it stays
pending and banks as excess. That is the right answer - it was never dealt - and it costs
nothing extra, since the deposit lives in the same file.

### Step 3 - commit at end of attack

`Op_endOfAttack` converts the pending yellow into red, up to 3 red on the card in total, and
returns the remainder to the supply. `onMonsterKilled` disappears from
`CardEquip_Smiterbiter` - nothing about this path is trigger-driven any more.

Committing is where the cap belongs. Capping at deposit time would corrupt the arithmetic: a
card already holding 2 red would clamp a 3-overkill deposit to 1, and the cleave's withdrawal
of 3 would then eat previously banked damage.

Do not suppress the intermediate notifications. Yellow appearing and disappearing on the card
mid-attack is visible in the log either way, and the commit message at the end is the one that
matters.

## Why this produces the right answer

Walk the designer's own example. Boldur deals 5, two monsters with 2 health each:

1. First monster takes 2 and dies. `marker_attack` = 3, card gains 3 pending.
2. Sweeping Strike cleaves the second monster for 3. Pending back to 0.
3. `Op_applyDamage` runs for that hit: it dies with 1 to spare. Pending = 1.
4. Attack ends. 1 pending commits to 1 red.

Matching the ruling exactly. The other cases:

- Cleave declined: nothing is withdrawn, 3 pending commits to 3 red. Correct - declining is
  what makes the damage excess.
- Cleave into a monster that survives: 3 withdrawn, no new kill, nothing pending. Correct -
  none of it was excess, it was all dealt.
- Nailed Together chains: each link withdraws what it spends and deposits what it wastes.
- Two independent kills in one action (Reaper-style split, or a future Boldur card): both
  leftovers are pending at once and commit together, instead of the second erasing the first.
- Card already holding 2 red, kill leaves 3, cleave spends 3, cleave kill leaves 1: pending
  ends at 1, commit takes the card to 3 red. The previously banked 2 are untouched, because
  pending and banked are physically different crystals.

## Risks to check

- Kills outside an attack action never reach `Op_endOfAttack` (Wrecking Ball during movement,
  Precision Axes, event damage), so a deposit there would strand yellow on the card forever.
  All of those deal 1 damage and so cannot overkill today, but gate the deposit on being inside
  an attack action rather than relying on that.
- Confirm pending yellow is cleared on any path that abandons an attack without reaching
  `Op_endOfAttack`.
- Gold veins are excluded from the kill trigger
  ([Op_applyDamage.php:95](../../modules/php/Operations/Op_applyDamage.php#L95)) but would still
  hit the deposit in step 1. Overkilling a vein should not feed Smiterbiter - use the same
  `GoldVein` guard.
- UI: yellow renders as gold/XP, so a card holding pending damage reads as holding XP. Cosmetic
  and separable from this change, but worth a look once the behaviour is right.

Worth stating explicitly, because it is not obvious from either card: monster attacks run the
same damage pipeline and also queue `endOfAttack`
([Op_monsterAttack.php:64](../../modules/php/Operations/Op_monsterAttack.php#L64)), and a hero
*can* be overkilled - `evaluateDamage` reports the excess before `Hero::finalizeDamage` clamps
damage to 5. So "monsters never produce overkill" is not the reason Smiterbiter stays out of
it. Under the old trigger-based design the protection was owner scoping: monster-turn
operations run as the automa colour ([Game.php:850](../../modules/php/Game.php#L850),
`getAutomaColor` = `ffffff`), so `Op_trigger` walked an empty `tableau_ffffff`. With the
deposit hardcoded in the pipeline that protection is gone, so gate it on the attacker being the
depositing hero. Keep a test on it.

## Tests

- Boldur with Sweeping Strike + Smiterbiter, kill with leftover, accept the cleave: assert
  the second monster takes the damage AND Smiterbiter banks only what remained after it.
  This is the double-dip regression guard and it fails today.
- Same setup, decline the cleave: assert Smiterbiter banks the full leftover (capped at 3).
- Cleave into a monster that survives: assert Smiterbiter banks nothing.
- Smiterbiter alone, no Sweeping Strike: unchanged behaviour, banks the leftover.
- Two kills with leftovers in a single attack action: assert both are banked (capped at 3).
  This is the case the simpler "read the marker at the end" version would have lost.
- Card already holding red, then a cleave: assert the previously banked damage survives.
- Nailed Together chaining into a Queen I two hexes from the hero: the damage is refused, so
  assert it is still banked as excess.
- Two consecutive attack actions (Rapid Strike): assert no pending yellow carries over.
- A monster overkilling Boldur: assert Smiterbiter banks nothing.
- After any of the above, assert no yellow crystals are left on the card.

---

# Open question, not addressed by either change

Why the reporter's cleave option was greyed out in BGA #235866 is still unexplained. The
"Smiterbiter consumed the overkill" theory does not survive reading the code, because nothing
decrements the counter. These changes fix real problems that the report led us to, but
neither should be assumed to fix the reported symptom. See the #235866 entries in
[BUG_TRIAGE.md](BUG_TRIAGE.md).
