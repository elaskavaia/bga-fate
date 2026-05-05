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

For bespoke quests (Shield-Boldur) we set `quest_on=custom` and ship a `CardEquip_<Name>` class — same pattern as existing custom cards. The `quest_r` column is left empty for those.

The full per-card mapping is in §4 below.

### 3.2 Triggers reused

No new triggers needed. Existing entries in [Trigger.php](../../modules/php/Model/Trigger.php) cover every non-bespoke quest:

- **Player-initiated paid quests** (the "spend X action" cards) don't use a trigger at all — they fire when the player invokes `completeQuest` from the top bar. The action-burning is encoded as `spendAction(actionXxx)` *inside* `quest_r`, not as a trigger listening for the action.
- **Counter / one-shot trigger-driven** — `TStep`, `TActionMove`, `TMonsterKilled`, `TRoll` all exist already.
- **Bespoke** (Shield-Boldur) — the custom class hooks whatever it needs locally; no new public trigger.

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




11. **Shield (Boldur)'s OR.** Two completion conditions — "enter Ogre Valley" or "skip troll XP". Bespoke class is cleanest; not worth extending the DSL for one card.



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
- [x] Elven Blade [Alva]
- [x] Windbite [Alva]
- [x] Trollbane [Bjorn]
- [x] Singing Bow [Alva]

**E — End-of-movement positional (`TMove`)**
- [x] Smiterbiter [Boldur] (`in(MarshOfSorrow):gainEquip`)
- [x] Dvalin's Pick [Boldur] (`check('countAdjMountains>=3'):gainEquip`) 
- [x] Eitri's Pick [Boldur] (`check('countAdjMonsters>=4 or countAdjLegends>=1'):gainEquip`) 

**F — Bespoke (custom Card class)**


- [x] Shield [Boldur]


Other
- [x] Tiara [Alva]
- [x] Elven Arrows [Alva]
- [x] Leather Purse [Bjorn]

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
- `CardEquip_ShieldBoldur.php` (new) — bespoke
- [src/Game.ts](../../src/Game.ts) — progress badge on deck-top equip card; `completeQuest` top-bar action
- [src/css/Cards.scss](../../src/css/Cards.scss) — badge styling

---



---





