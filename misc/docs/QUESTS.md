# Quest System Design

Plan document for adding quest tracking + completion to Fate.
Status: **draft, not implemented**.

> Scope: equipment quests only. Quests are the *only* way to gain new equipment
> in Fate (upgrades only buy abilities — see [Op_upgrade](../../modules/php/Operations/Op_upgrade.php)).

---

## 1. What's already in place

| Piece | Status | Notes |
|---|---|---|
| `quest` column in [card_equip_material.csv](../card_equip_material.csv) | ✅ data only | Human-readable italics text. No machine fields. |
| `Op_gainEquip` + `effect_gainEquipment` | ✅ done | Places a card on tableau, fires `Trigger::CardEnter`, displaces existing Main Weapon. Already handles starting equipment, upgrade-flow, and `gainEquip` from card effects ([Blade Decorations](../card_equip_material.csv) `in(Grimheim):2spendXp:gainEquip`). |
| Trigger system | ✅ done for combat & movement | Existing triggers: `ActionAttack`, `Move`, `Roll`, `MonsterKilled`, `TurnStart/End`, `MonsterMove`, etc. See [Trigger.php](../../modules/php/Model/Trigger.php). |
| Equipment pile (`deck_equip_{owner}`) | ✅ done | Shuffled at setup, top card visible. End-of-turn rule "demote top card to bottom" not implemented. |
| Client `quest` tooltip | ✅ done | [Game.ts:391](../../src/Game.ts#L391) shows quest text in card tooltip. |

What's missing: any **mechanical** representation of "this trigger advances this quest", any progress storage, any completion check, any UI affordance.

---

## 2. Quest taxonomy

Pulled from all 40 quest strings across Bjorn / Alva / Embla / Boldur. Grouped by mechanism so we can pick a uniform shape, not 40 bespoke handlers.

### A. Spend-action-at-location (one-shot)

Trigger fires on the chosen action, gated by hex location/terrain. Always single-shot — no progress, just instant on first match.

| Card | Quest | Trigger | Gate |
|---|---|---|---|
| Black Arrows | Spend 1 attack action in the Robber Camp | ActionAttack | `in(RobberCamp)` |
| Bone Bane Bow | Spend a mend action on Nailfare | ActionMend | `in(Nailfare)` |
| Throwing Axes / Darts / Knives / Precision Axes | Spend 1 practice action in a forest | ActionPractice | `in(forest)` |
| Healing Potion | Spend 1 mend action in the Witch Cabin | ActionMend | `in(WitchCabin)` |
| Wildfire Blade | Spend 1 mend action in the Spewing Mountain | ActionMend | `in(SpewingMountain)` |
| Home Sewn Cape | Spend 1 attack action when not adjacent to a monster | ActionAttack | `not_adj(monster)` |
| Leg Guards / Battle Boots | Spend 1 focus action | ActionFocus | (no gate) |
| Warrior Shield | Spend 1 attack action ("negotiating") | ActionAttack | (no gate; designer-flavor) |
| Bloodline Crystal | Discard 2 cards in Temple Ruins | Discard ×2 | `in(TempleRuins)` |
| Heels | Spend 1 mend action AND discard 2 cards in the Witch Cabin | ActionMend + Discard ×2 | `in(WitchCabin)` |
| Home Sewn Tunic | Spend 1 practice action and 1 experience [XP] | ActionPractice | `+1spendXp` cost |

### B. Pay-gold (one-shot, optionally gated)

Player elects to pay gold on a normal action; equip is the side-effect. **Already expressible** via existing DSL (`spendXp:gainEquip`) — Blade Decorations is the working example.

| Card | DSL today | Notes |
|---|---|---|
| Blade Decorations | `in(Grimheim):2spendXp:gainEquip` | ✅ shipped pattern. |
| Custom Armor | (would be) `4spendXp:gainEquip` | Anywhere. |
| Tailored Boots | `in(Grimheim):2spendXp:gainEquip` | "in town" = Grimheim. |
| Alva's Bracers | `in(road):5spendXp:gainEquip` | Needs `road` predicate (already a hex flag — see roads PR). |
| Dwarf Helm | `in(TempleRuins):2spendXp:gainEquip` | |
| Mining Equipment | `1spendXp:gainEquip(progress)` then `2spendXp:gainEquip` | **Two-stage** payment, only one example. Could be modeled as count=3 or as two-stage flag. |
| Orebiter | `2spendXp:gainEquip` | "Lose" 2 gold. |

For these we don't need any new infrastructure — just hand-author the `r` field. The "quest" remains in the `quest` column for human display, with a parallel `quest_r` (or similar) column carrying the DSL for the *paid quest* version.

> **Open**: should "pay gold" quests appear as a **free action** the player can trigger from the equipment card UI, or be queued as a `useCard`-style optional clickable on the equip pile? Today's Blade Decorations works because it sits on the tableau already; if the card sits in the deck, we need a UI hook to surface the "pay 2 gold" button.

### C. Kill-monster (one-shot, replaces gold reward)

Quest fires on a monster kill; the player **chooses** to take the equipment instead of XP.

| Card | Quest | Replaced reward |
|---|---|---|
| Helmet (×2) | Kill a brute or skeleton | full XP from that kill |
| Quiver (×2) | Kill a rank 3 monster | full XP from that kill |
| Leather Purse | Kill a trollkin (bonus: spawn 2 brutes adjacent) | none replaced; side-effect |

Mechanically this is a `MonsterKilled` trigger with a filter on monster type/rank, gated by player's choice ("take equip *instead of* gold"). The player gets a yes/no prompt.

### D. Counter / accumulating

Multi-turn progress. Each tick = trigger match.

| Card | Quest | Trigger | Filter | Target |
|---|---|---|---|---|
| Belt of Youth | Enter 8 forest areas | Step | `terrain=forest` | 8 |
| Raven's Claw | Enter 10 forest areas | Step | `terrain=forest` | 10 |
| Dwarf Mail | Enter 7 areas adjacent to mountains | Step | `adj(mountain)` | 7 |
| Trollbane | Collect 5 gold from killing trollkin | MonsterKilled | `trollkin` | 5 (gold gained, not kills) |
| Elven Blade | Kill 3 adjacent monsters | MonsterKilled | `adj` (monster was adjacent at moment of kill) | 3 |
| Dwarf Pick | Kill 3 monsters | MonsterKilled | (any) | 3 |
| Windbite | Kill 4 monsters at range 2 or more | MonsterKilled | `range>=2` | 4 |
| Singing Bow | Roll 10 [DIE_ATTACK] in forests | Roll | `in(forest)` | 10 (count of dice rolled) |

Progress storage: damage/XP crystals on the equipment card, mirroring the rulebook ("Track any progress with crystals or damage dice on the equipment card").

### E. End-of-movement positional

Quest checks the hero's hex *after* a move action completes.

| Card | Quest | Predicate |
|---|---|---|
| Dvalin's Pick | End movement adjacent to 3 mountain areas | `countAdjMountains >= 3` after `actionMove` |
| Eitri's Pick | End movement adjacent to 4 monsters or 1 legend | `countAdjMonsters >= 4 or countAdjLegends >= 1` |
| Smiterbiter | End movement in the Marsh of Sorrow | `in(MarshOfSorrow)` |

Trigger: `TurnEnd` is too late (other things happen). Cleanest hook is end of `Op_actionMove` — add a `Trigger::AfterActionMove` (or piggyback on `ActionMove` with an `endOfMove` data field).

### F. Special / one-off

Three weirdos. Each merits a bespoke `Card<Type>_<Name>` class.

| Card | Quest | Why bespoke |
|---|---|---|
| Tiara | "Find it in the Dark Forest" | No formal trigger; treat as "enter Dark Forest hex" → instant. Just an `in(DarkForest)` step trigger, but the hex name needs to exist on the map. |
| Elven Arrows | Stand in Troll Caves and spawn an adjacent troll | Spawn isn't a hero-driven event. Watch monster placements during reinforcement; if hero is in TrollCaves and a troll spawns adjacent, fire. |
| Shield (Boldur) | Enter Ogre Valley OR skip XP from killing a troll | Quest is itself an OR. Two independent matchers, either completes. |

---

## 3. Proposed data model

### 3.1 New CSV columns on `card_equip_material.csv`

Only **two** new columns. The bulk of the work happens in `quest_r`, which is a regular Op DSL expression — same parser, same combinators, same primitives as the existing `r` column on tableau cards (see [EFFECTS_DSL.md](EFFECTS_DSL.md)).

| Column | Meaning | Example |
|---|---|---|
| `quest_on` | Trigger that runs `quest_r`. One of the published `Trigger::*` values, or empty. Empty = player-initiated quest — surfaced as a top-bar free action (`completeQuest`), analogous to `useCard`. | `TStep` |
| `quest_r` | Op DSL chain evaluated when the trigger fires (or when the player clicks the button). Encodes gates, costs, intermediate ops, and the final reward — all as one expression. | `gainTracker:counter('countTracker>10'):gainEquip` |

Worked examples (sketches — exact predicates and primitives are refined per-card at implementation time):

```
Black Arrows     quest_on=          quest_r= in(RobberCamp):spendAction(actionAttack):gainEquip
Belt of Youth    quest_on=TStep     quest_r= in(forest):gainTracker:counter('countTracker>=8'):gainEquip
Helmet           quest_on=TMonsterKilled  quest_r= ?'brute or skeleton':gainEquip:blockXp
Trollbane        quest_on=TGoldGained     quest_r= 'source=trollkin':gainTracker(amount):counter('countTracker>=5'):gainEquip
Mining Equipment quest_on=          quest_r= 3spendXp:gainEquip
```

For bespoke quests (Shield-Boldur, Elven Arrows) we set `quest_on=custom` and ship a `CardEquip_<Name>` class — same pattern as existing custom cards. The `quest_r` column is left empty for those.

The full per-card mapping is in §4 below.

### 3.2 Triggers reused

No new triggers needed. Existing entries in [Trigger.php](../../modules/php/Model/Trigger.php) cover every non-bespoke quest:

- **Player-initiated paid quests** (the "spend X action" cards) don't use a trigger at all — they fire when the player invokes `completeQuest` from the top bar. The action-burning is encoded as `spendAction(actionXxx)` *inside* `quest_r`, not as a trigger listening for the action.
- **Counter / one-shot trigger-driven** — `TStep`, `TActionMove`, `TMonsterKilled`, `TRoll` all exist already.
- **Bespoke** (Shield-Boldur, Elven Arrows) — the custom class hooks whatever it needs locally; no new public trigger.

The only dispatch change is in `Op_trigger`: today it walks tableau + hand for matching cards; we extend it to also walk `deck_equip_{owner}` top card.

### 3.3 Progress storage

Rulebook ([RULES.md:209](RULES.md#L209)): *"Track any progress with crystals or damage dice on the equipment card."* — both are sanctioned. We pick **red crystals**, because [Op_spendDurab](../../modules/php/Operations/Op_spendDurab.php) already adds a red crystal to an equipment card; `gainTracker` is a thin shim that reuses that mechanism.

```
deck_equip_alva
  └── card_equip_2_22  (Belt of Youth, top card)
        └── crystal_red_77   ← progress = 1
        └── crystal_red_78   ← progress = 2
        ...
```

The counter check is part of the DSL chain itself — `counter('countTracker>=8'):gainEquip`. After each `gainTracker`, the next combinator evaluates the crystal count on the deck-top card and gates the `gainEquip`.

Implementation: `Op_gainTracker extends Op_spendDurab`. The base op already places 1 red crystal on a card. Differences:
- Target is locked to the deck-top equipment card (not the `useCard`-set card).
- No durability cap (quest progress accumulates beyond `durability`).
- Optional `amount` argument (Trollbane adds `gold`, Singing Bow adds `numDice`) — base is 1.

Why this works:
- Already animatable / visible to the player — same DOM hooks as durability damage.
- Survives the persistence layer for free (it's just a token parented to the card).
- When the card moves to the tableau, we sweep the progress crystals back to supply.
- When the card is *demoted* to the bottom of the deck (end-of-turn rule), the crystals are swept back to supply — progress resets, see §6.1.

Naming: kept as `gainTracker` rather than reusing `spendDurab` so the *intent* reads clearly in the DSL — quest progress is conceptually different from durability damage even when the underlying token is the same.

### 3.4 Completion flow

1. An action / movement / kill resolves, calls `queueTrigger(<Trigger>)`.
2. `Op_trigger` walks active player's tableau + hand + **top of `deck_equip`** (new).
3. For the deck-top equipment card with matching `quest_on`, we parse `quest_r` and queue it onto the OpMachine — same path as the existing `r` field on tableau cards.
4. The DSL itself encodes gates (`in(...)`, `adj(...)`, `not_adj(...)`), tracker increments (`gainTracker`), counter checks (`counter('countTracker>=N')`), and the final `gainEquip`. No bespoke `Op_questTick` needed — `gainTracker` is generic and the rest reuses existing combinators.
5. `Op_gainEquip::resolve()` already does the right thing — moves card to tableau, fires `CardEnter`, recalcs trackers. We additionally sweep tracker crystals back to supply at that point.

For player-initiated quests (`quest_on` empty), the player gets a top-bar free action **`completeQuest`**, analogous to `useCard`. Selecting it runs `quest_r` for the deck-top card through the OpMachine. Costs in the chain (`spendAction`, `spendXp`, `discardEvent`) pop their normal prompts, exactly as they would inside a `useCard` flow.

This keeps the dispatch loop in one place (`Op_trigger`) and reuses every existing piece.

### 3.5 Replacement-reward quests (Helmet, Quiver, Leather Purse) — a sub-pattern of trigger-driven claims

Mechanically these are not a separate category. They're **trigger-driven paid claims** — same `useCard`-style yes/no prompt as the player-initiated `completeQuest` flow, just kicked off by `TMonsterKilled` instead of a top-bar free-action click. The "payment" is forgoing the normal kill reward (XP/gold), and the chain has extra effects on top of `gainEquip` (e.g. `blockXp`, or Leather Purse's bonus brute spawns).

Sketch:
```
quest_on = TMonsterKilled
quest_r  = ?'<predicate on monster>':gainEquip:blockXp
```

The `?` makes the branch optional → player gets a yes/no prompt at trigger time. On accept, run `gainEquip` and `blockXp` (or `refundGold`, depending on per-card semantics). On decline, the normal reward stands.

Exact predicate and exact reward-suppression op (take back gold? block XP? both?) are settled per-card during implementation.

This is a small extension to `Op_trigger`: today it walks tableau+hand; we extend it to also walk `deck_equip_{owner}` top card.

---

## 4. Per-card mapping table

redacted
---

## 5. Client display

- The deck_equip card on each tableau already shows the top card image. Add a **progress badge** overlaid on it: red crystal count vs. target (e.g. "3 / 8").
- Render parented crystals on the card the same way they're rendered on tableau equipment (`crystal_red on cardId`). The DOM mapping already works because the card div has the right id.
- For player-initiated quests (`quest_on` empty), expose a **`completeQuest`** free action on the top bar — the same surface as `useCard`. Selecting it runs `quest_r` for the deck-top card through the OpMachine; costs (`spendAction`, `spendXp`, `discardEvent`) raise their normal prompts mid-resolve. **No buttons rendered on the equipment card itself.**
- The `completeQuest` action is enabled iff the deck-top card has a non-empty `quest_r` AND its leading gate predicates pass (location etc.) — so it appears greyed-out / hidden when the quest cannot currently be claimed.
- For trigger-driven optional claims (Helmet, Quiver, Leather Purse — the §3.5 sub-pattern), the `?` in the chain pops the same yes/no prompt automatically when the trigger fires. No new client-side surface needed — it's `useCard`'s existing prompt machinery.

---

## 6. Edge cases and open questions

> Per Victoria's instruction: questions stay in this doc, they don't get bounced back. If Victoria reads this and answers any of them, we update the doc and proceed.

1. **Demoting the top card mid-quest.** End-of-turn rule lets a player put the top equipment to the bottom of the pile. **Resolved: progress is cleared on demote** — sweep parented red crystals back to supply at the moment the card moves to the bottom of `deck_equip`. Implementation hook: whatever op handles the demote calls a helper that clears `crystal_red on cardId`.

2. **Range / adjacency at the time of kill.** **Resolved: "kill 3 adjacent monsters" = 3 melee kills (Elven Blade).** Each `TMonsterKilled` ticks the counter only if the kill was a melee attack — i.e. monster's hex was adjacent to hero's hex at the moment `Op_dealDamage` fires the kill trigger. Ranged kills don't count. Predicate: `melee` (or equivalently `adj` evaluated against the *attacking* hero's hex pre-removal).

4. **"Range 2 or more" for Windbite.** **Resolved: hero attack range.** Predicate checks the hero's *attack range stat* at the moment of kill (i.e. the kill came from a ranged weapon with range ≥ 2), not the actual hex distance to the monster. So killing an adjacent monster with a bow still counts; killing a 2-hex-away monster with a melee weapon is impossible anyway, but for clarity the rule is about the weapon, not the geometry.

5. **Singing Bow's "10 dice".** **Resolved: count of attack dice rolled while in a forest, for any reason.** Not hits, not runes — physical dice rolled. Includes attack rolls, card-effect rolls, defensive rolls, anything that rolls attack dice. Each `TRoll` (attack-dice variant) contributes `numDice` to the counter if the hero was on a forest hex at the moment of the roll. Target = 10 cumulative.

6. **Mining Equipment two-stage payment.** **Resolved: it's a flavor joke — just pay 3 gold.** "Pay 1 for the equipment, then 2 for insurance and taxes" is fiction; mechanically it's a single category-B paid quest. DSL: `3spendXp:gainEquip`. One button, three gold up front. No two-stage state.

7. **Heels** (mend + discard 2 cards in Witch Cabin). **Resolved: it's a single paid claim, not a multi-step quest.** At the moment of a mend action in the Witch Cabin, the player is offered a yes/no prompt: "Discard 2 cards to claim Heels?" On accept, discard 2 cards from hand and `gainEquip(Heels)`. No progress storage, no `quest_atomic` flag, no cumulative state — one transaction.

7. **Replacement-reward UX.** When a kill triggers Helmet's "take instead of XP" prompt, what if the player has already auto-collected the XP in the same op step? Proposed: queue the prompt **before** the XP grant fires; on accept, void the XP grant. Implementation belongs in `Op_dealDamage` → `Op_killMonster`.

9. **Counter overshoots.** Trollbane (5 gold from trollkin) — if you kill a draugr-rank trollkin worth 3 gold and you're at 4/5, do you complete with 1 wasted? Proposed: yes; cap at target, ignore overshoot.

10. **Custom-class fallback for Tiara.** "Find it in the Dark Forest" assumes a Dark Forest hex exists in `map_material.csv`. Need to verify the hex's `loc` field is set to "DarkForest". If not, this is a map data fix, not a quest engine fix.

11. **Shield (Boldur)'s OR.** Two completion conditions — "enter Ogre Valley" or "skip troll XP". Bespoke class is cleanest; not worth extending the DSL for one card.

12. **Reinforcement spawns and Elven Arrows.** No existing `Trigger::MonsterSpawn`. We'd add it in the reinforcement op. Probably worth doing anyway (other future cards may want it). Bespoke `CardEquip_ElvenArrows` watches for `(hero in TrollCaves) AND (spawned monster is troll AND adjacent)`.

13. **Starting equipment already has no quest.** Fine — `card_equip_<n>_15` rows have `quest=""`. They're placed by setup directly, no quest engine involvement.

14. **Skip-card to bottom.** End-of-turn UI for "demote top equipment to bottom" is **not yet implemented anywhere**. Track separately from quest engine — quest engine tolerates this either way.

---

## 7. Implementation

The engine and infrastructure are landed. What remains is per-card authoring + tests.

### Per-card workflow

For each card not yet checked off below:

1. Read the card's `quest` text (the italicised line in the `quest` column of [card_equip_material.csv](../card_equip_material.csv)) and the §4 mapping sketch.
2. Pick the trigger (`quest_on`) and write `quest_r` as an Op DSL chain. Author both columns into the card's row. Run `npm run genmat`.
3. **If anything new is needed — a new op, an extension to an existing op, or a new Math DSL term — pause and consult the user before implementing.** These are engine-shaping changes; the user wants to weigh in on naming and shape before code lands. Once approved:
   - Prefer extending an existing op (new param, optional data field, branch on context) over creating a near-duplicate.
   - Only add a new op via the [game-create-operation](../../.claude/skills/game-create-operation/SKILL.md) skill when the existing surface genuinely doesn't generalise.
   - New Math DSL terms (e.g. `numDice`, `monster_gold`) go in `Game::evaluateTerm` (or as a `count*` method).
   - Either way, unit tests live in [tests/Operations/](../../tests/Operations/).
4. Write a campaign test in `tests/Campaign/Campaign_<Hero>QuestTest.php` via the [game-create-itest](../../.claude/skills/game-create-itest/SKILL.md) skill — one test per quest, exercising the trigger or the `completeQuest` flow end-to-end.
5. Run full suite (`npm run tests`). Green = tick the box.

> **Per-card resolution.** Predicate ops, Math DSL terms, and op-call argument names in §4 are intentionally not pre-cataloged — resolved at implementation time per card. Naming (`terrain` vs `in`, `adj_monster` vs `adj(monster)`, etc.) is also settled then.

### Engine + infrastructure (done)

- [x] `Op_gainTracker`, `Op_completeQuest`, `Op_demote`, `effect_clearCrystals`
- [x] `Card::canResolveQuest` / `Card::triggerQuest` (mirror of `canBePlayed`/`useCard`)
- [x] `Op_trigger` walks deck-top equip
- [x] `Op_turnEnd` queues `demote` (RULES.md §End-of-Turn step 5)

### Cards

Grouped by §2 mechanism. Tick on per-card test green.

> **Parallel dispatch:** the hero name in `[brackets]` after each card name is the test-file owner. Two agents touching the same hero will clobber `Campaign_<Hero>QuestTest.php` — when picking cards for parallel runs, **pick one card per hero** (Bjorn / Alva / Embla / Boldur).

**A — Spend-action-at-location (player-initiated, optionally gated)**
- [x] Leg Guards [Embla] (`spendAction(actionFocus):gainEquip`) — Q2 canary
- [x] Battle Boots [Boldur] (`spendAction(actionFocus):gainEquip`)
- [x] Warrior Shield [Embla] (`spendAction(actionAttack):gainEquip`)
- [x] Black Arrows [Bjorn] (`in(RobberCamp):spendAction(actionAttack):gainEquip`)
- [x] Bone Bane Bow [Bjorn] (`in(Nailfare):spendAction(actionMend):gainEquip`)
- [x] Healing Potion [Embla] (`in(WitchCabin):spendAction(actionMend):gainEquip`)
- [x] Wildfire Blade [Embla] (`in(SpewingMountain):spendAction(actionMend):gainEquip`)
- [x] Throwing Axes [Bjorn] (`in(forest):spendAction(actionPractice):gainEquip`) — covered by Throwing Darts canonical test
- [x] Throwing Darts [Alva] (`in(forest):spendAction(actionPractice):gainEquip`)
- [x] Throwing Knives [Embla] (`in(forest):spendAction(actionPractice):gainEquip`) — covered by Throwing Darts canonical test
- [x] Precision Axes [Boldur] (`in(forest):spendAction(actionPractice):gainEquip`) — covered by Throwing Darts canonical test
- [x] Home Sewn Cape [Bjorn] (`check('countAdjMonsters==0'):(spendAction(actionAttack):gainEquip)`)
- [x] Home Sewn Tunic [Bjorn] (`spendAction(actionPractice):spendXp:gainEquip`)
- [x] Bloodline Crystal [Alva] (`in(TempleRuins):2discardEvent:gainEquip`)
- [x] Heels [Embla] (`in(WitchCabin):spendAction(actionMend):2discardEvent:gainEquip`)

**B — Pay-gold (player-initiated)**
- [x] Blade Decorations [Embla] (`in(Grimheim):2spendXp:gainEquip`)
- [x] Custom Armor [Embla] (`4spendXp:gainEquip`)
- [x] Tailored Boots [Embla] (`in(Grimheim):2spendXp:gainEquip`)
- [x] Alva's Bracers [Alva] (`in(road):5spendXp:gainEquip`)
- [x] Dwarf Helm [Boldur] (`in(TempleRuins):2spendXp:gainEquip`)
- [x] Mining Equipment [Boldur] (`3spendXp:gainEquip`) — flavor joke, single payment
- [x] Orebiter [Boldur] (`2spendXp:gainEquip`)

**C — Kill-monster, replaces XP (trigger-driven optional claim)**



- [x] Helmet [Bjorn] (`killed('brute or skeleton'):?(blockXp:gainEquip)` on `TMonsterKilled`)
- [x] Helmet [Embla] (same shape as Bjorn's)
- [x] Quiver [Bjorn] (`killed('rank>=3'):blockXp:gainEquip`)
- [x] Quiver [Alva] (same shape as Bjorn's)


**D — Counter / accumulating**
- [x] Belt of Youth [Alva] (`in(forest):gainTracker,check('countTracker>=8'):gainEquip` on `TStep`) — Q1 canary
- [x] Raven's Claw [Embla] (`in(forest):gainTracker,check('countTracker>=10'):gainEquip` on `TStep`)
- [x] Dwarf Mail [Boldur] (`adj(mountain):gainTracker,check('countTracker>=7'):gainEquip` on `TStep`)
- [x] Dwarf Pick [Boldur] (`gainTracker,check('countTracker>=3'):gainEquip` on `TMonsterKilled`)
- [ ] Elven Blade [Alva] (`melee:gainTracker,check('countTracker>=3'):gainEquip` on `TMonsterKilled`) — needs `melee` predicate
- [ ] Windbite [Alva] (`'hero_range>=2':gainTracker,check('countTracker>=4'):gainEquip` on `TMonsterKilled`) — needs `hero_range` Math term
- [ ] Trollbane [Bjorn] (`'killed(trollkin):gainTracker(monster_gold),check('countTracker>=5'):gainEquip` on `TMonsterKilled`) — needs `monster_gold` Math term
- [ ] Singing Bow [Alva] (`in(forest):gainTracker(numDice),check('countTracker>=10'):gainEquip` on `TRoll(attackDice)`) — needs `numDice` Math term

**E — End-of-movement positional (`TMove`)**
- [x] Smiterbiter [Boldur] (`in(MarshOfSorrow):gainEquip`)
- [x] Dvalin's Pick [Boldur] (`check('countAdjMountains>=3'):gainEquip`) 
- [x] Eitri's Pick [Boldur] (`check('countAdjMonsters>=4 or countAdjLegends>=1'):gainEquip`) 

**F — Bespoke (custom Card class)**


- [ ] Shield [Boldur] (`CardEquip_ShieldBoldur` new) — OR of two predicates


Other
- [ ] Tiara [Alva]
- [ ] Elven Arrows [Alva] - needs new spawn operation
- [ ] Leather Purse [Bjorn] (`killed(trollkin):gainEquip`) — needs new spawn operation

### Client polishgainTracker,check

Tracked in [PLAN.md](PLAN.md) under Quests → Client. Not required for the engine to function — defer until card coverage is broad enough that polish doesn't get redone.

---

## 8. Files that will change

- [misc/card_equip_material.csv](../card_equip_material.csv) — new `quest_on` and `quest_r` columns + per-card data
- [misc/op_material.csv](../op_material.csv) — add `gainTracker`, `blockXp`, `completeQuest` rows
- [modules/php/Operations/Op_trigger.php](../../modules/php/Operations/Op_trigger.php) — also walk `deck_equip_{owner}` top card
- [modules/php/Operations/Op_gainTracker.php](../../modules/php/Operations/) — **new**, extends `Op_spendDurab` (no durability cap, target locked to deck-top)
- [modules/php/Operations/Op_blockXp.php](../../modules/php/Operations/) — **new**, suppresses XP grant from current kill event
- [modules/php/Operations/Op_completeQuest.php](../../modules/php/Operations/) — **new**, free-action sibling of `Op_useCard`
- [modules/php/Operations/Op_gainEquip.php](../../modules/php/Operations/Op_gainEquip.php) — sweep progress crystals to supply on completion
- [modules/php/Cards/CardEquip_Tiara.php](../../modules/php/Cards/CardEquip_Tiara.php), `CardEquip_ElvenArrows.php` (new), `CardEquip_ShieldBoldur.php` (new) — bespoke
- [src/Game.ts](../../src/Game.ts) — progress badge on deck-top equip card; `completeQuest` top-bar action
- [src/css/Cards.scss](../../src/css/Cards.scss) — badge styling
- `tests/Operations/Op_questTickTest.php` — counter logic
- `tests/Campaign/QuestCompletionTest.php` — full flow per category

---

## 9. Out of scope for this design

- **Multiple equipment piles per player.** One pile, one quest. Rules don't introduce this.
- **Quest skip / reroll.** Beyond the rulebook's once-per-turn demote.
- **Hero-card quests / ability-card quests.** Quests are an equipment-card concept only.
- **Phase 5+ heroes (Finkel, Sindra).** Their cards are commented out in the CSV and out of scope for the alpha.

---

## 10. Open concerns

Items still unresolved, deferred to later phases or pending answers. Resolved items are removed.

### Real risks

4. **`blockXp` semantics in the trigger graph.** When `TMonsterKilled` fires Helmet's prompt, the XP grant from `Op_killMonster` may have already been queued onto the OpMachine. `blockXp` would need to either (a) intercept a queued op (fragile) or (b) be honored by `Op_killMonster` when reading a flag set on the machine context. Option (b) is cleaner but requires `Op_killMonster` to *check* for a block before granting — i.e. the trigger has to fire **before** the grant is queued. Confirm the existing kill→grant ordering in [Op_killMonster.php](../../modules/php/Operations/Op_killMonster.php).

5. **Singing Bow's `gainTracker(numDice)` — where does `numDice` come from?** `TRoll` carries the roll context, but the Math expression in `gainTracker(numDice)` evaluates via the [Base mapper](../../modules/php/Base.php). The mapper needs a `numDice` resolver scoped to the current trigger event. Same concern for `monster_gold` (Trollbane), `killed=trollkin` (predicate). These are all event-context lookups — the design assumes the trigger event is reachable from the Math evaluator, which I haven't verified.

6. **Demote-on-end-of-turn is a future feature.** §6 question 14 says it's not implemented. That means quest progress on a non-shipped action — okay. But the **sweep-on-demote** logic in §3.3 ("crystals back to supply") needs to live wherever the demote op eventually lives. Risk: when demote ships, whoever writes it forgets to call the crystal-clearing helper. **Mitigation**: write the helper now (`effect_clearQuestProgress($cardId)`) and document it in the demote op's TODO so future-you trips over it.

### Still hand-wavy

- **Bespoke cards (Tiara, Elven Arrows, Shield-Boldur).** §6 questions 10–12 still open. Tiara needs map data verified; Elven Arrows needs a `MonsterSpawn` trigger or a polling hook; Shield-Boldur needs a custom class. None of these are blockers but each has unresolved scope. Addressed in Phase Q5.
- **Section §6 numbering.** Items go 1, 2, 4, 5, 6, 7, 7, 9, 10, 11, 12, 13, 14 — duplicated 7 and missing 3 / 8 (artifacts from earlier removals). Cosmetic. Renumber once all §6 questions are answered.

---
