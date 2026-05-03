---
name: game-create-quest
description: Wire a quest for a Fate equipment card — author `quest_on` / `quest_r` in `card_equip_material.csv`, add any required ops or Math DSL terms (with user approval), and create the campaign test. Use this skill whenever the user asks to create, implement, or wire a quest for a specific equipment card (e.g. "create quest for card_equip_3_22", "wire Bone Bane Bow's quest", "implement quest_r for Helmet"). The full quest-system design lives in [misc/docs/QUESTS.md](../../../misc/docs/QUESTS.md); the per-card checklist (which cards remain) is the "Cards" section there.
argument-hint: card ID (e.g. card_equip_3_22)
---

# Quest Creation Skill

Wire one equipment card's quest end-to-end: CSV authoring → any new ops/terms (with user approval) → campaign test → tick the box.

The quest system is documented in [misc/docs/QUESTS.md](../../../misc/docs/QUESTS.md). Read §2 (taxonomy), §3 (data model), §4 (per-card mapping sketches), and §7 (the per-card workflow this skill implements) before starting if you haven't already.

## Input

A card ID like `card_equip_3_22`. Format: `card_equip_{heroNumber}_{cardNumber}`. `heroNumber`: 1=Bjorn, 2=Alva, 3=Embla, 4=Boldur.

If the user passes a card name instead of an ID, look it up in `misc/card_equip_material.csv` first.

## Steps

### 1. Look up the card

Search [modules/php/Material.php](../../../modules/php/Material.php) for the card ID (+20 lines of context). The generated PHP has named keys (`"name" => ...`, `"quest" => ...`, etc.) so each field is unambiguous — much easier than grepping the pipe-separated CSV. Note:

- **name** — for the test method name and notifications.
- **quest** — the italicised rules text (the human description of the quest).
- **quest_on** / **quest_r** — current values. If both already set and tested, the card is done — flag and stop.
- **r** / **on** — existing rule string for the card's _active_ effect (after it's claimed). Don't conflate with quest fields.

Edits in step 4 still go to the CSV (the source of truth); Material.php is regenerated from it.

Then in [misc/docs/QUESTS.md](../../../misc/docs/QUESTS.md):

- **§2 category** (A–F) — tells you what shape the quest is (player-initiated vs trigger-driven, simple vs counter, etc.).
- **§4 sketch** — the suggested `quest_on` + `quest_r` for this card. Treat as a starting point; revise if your reading of the rule disagrees.

### 2. Pick the trigger and write `quest_r`

`quest_r` is a regular Op DSL chain (see [misc/docs/EFFECTS_DSL.md](../../../misc/docs/EFFECTS_DSL.md)). Common shapes:

- **Player-initiated paid quest** (`quest_on=` empty): `[gate:]spendAction(actionXxx):gainEquip` or `[gate:]NspendXp:gainEquip` — surfaces via `Op_completeQuest` top-bar action.
- **Trigger-driven counter**: `quest_on=TStep|TMonsterKilled|TRoll`, `quest_r=[gate:]gainTracker[(amount)]:check('countTracker>=N'):gainEquip`.
- **Trigger-driven replacement reward** (Helmet/Quiver): `quest_on=TMonsterKilled`, `quest_r=?'<predicate>':gainEquip:blockXp` — `?` makes the chain optional → player gets a yes/no prompt.

### 3. **Consult the user before adding any new op, extending an existing op, or adding a new Math DSL term**

These are engine-shaping changes. The user wants to weigh in on shape and naming before code lands. Pause and present:

- What's needed (e.g. "Need a `melee` predicate that's true when the killed monster's hex was adjacent to the hero's hex at the moment of kill").
- Whether you'd extend an existing op or add a new one (default: prefer extending — new param, optional data field, branch on context — over a near-duplicate).
- Suggested name and signature.

Once approved:

- New ops follow the [game-create-operation](../game-create-operation/SKILL.md) skill (stub → CSV → genmat → design review → impl → unit tests).
- New Math DSL terms go in `Game::evaluateTerm` (or as a `count*` method) with a unit test.

### 4. Author the CSV row + run genmat

Use `awk` (per CLAUDE.md guidance) to set `quest_on` and `quest_r` on the card's row in [misc/card_equip_material.csv](../../../misc/card_equip_material.csv). Confirm the diff is one line. Then:

```bash
npm run genmat
```

Verify the new fields land in [modules/php/Material.php](../../../modules/php/Material.php) — `grep card_equip_<hno>_<num>` and check `quest_on` / `quest_r` lines are present.

### 5. Write a campaign test

**Delegate to the [game-create-itest](../game-create-itest/SKILL.md) skill**, passing the card ID. Target file: `tests/Campaign/Campaign_<Hero>QuestTest.php` — the per-hero quest catch-all (e.g. `Campaign_AlvaQuestTest.php` already exists for Alva). One test per quest branch.

The test should:

- Drive the entry point from the player's choice surface, not by pushing onto the machine:
  - **Player-initiated quests** (`quest_on=` empty): `$this->respond("completeQuest")` — picks the free action from the turn op's choices. **Never** `$this->game->machine->push("completeQuest", ...)`; that bypasses the choice-listing pathway the real player uses.
  - **Trigger-driven quests** (`TStep`, `TMonsterKilled`, …): push the underlying trigger source (`move`, `dealDamage`, …) — see existing tests for the `machine->push(...)` + `dispatchAll()` pattern.
- Drive any sub-prompts (`spendAction`, choice, etc.).
- Assert the equip card lands on `tableau_$color`, the new deck-top is revealed, and any quest-progress crystals are swept.

### 6. Run the full suite

```bash
npm run tests > /tmp/quest_tests.txt 2>&1; tail -10 /tmp/quest_tests.txt
```

Green = done. Failure = diagnose with `dumpState()` per the game-create-itest skill's debug workflow.

### 7. Tick the box

In [misc/docs/QUESTS.md](../../../misc/docs/QUESTS.md) §7 → "Cards" section, find the card under its category and flip `[ ]` → `[x]`.

## Output Format

```
## Quest Created: {card_name} ({card_id})

### Definition
- **Category**: {A-F from QUESTS.md §2}
- **Quest text**: {italicised quest column}
- **quest_on**: {value or "(empty — player-initiated)"}
- **quest_r**: {value or "(empty — bespoke)"}

### Engine changes
- {bullet for each new/extended op or Math term, or "none — pure CSV authoring"}

### Test
- [test{Card}{Effect}](tests/Campaign/Campaign_<Hero>QuestTest.php#L<line>)

### Result
- N tests, M assertions, passed in Xs
- Full suite: <count> tests, all green
- QUESTS.md ticked
```

## Important

- **Always consult the user before any new op / extension / Math term.** No exceptions. The user wants to weigh in on engine-shape decisions.
- The card list lives in [QUESTS.md §7](../../../misc/docs/QUESTS.md) — don't duplicate it here. When the list changes (cards done, cards removed), update QUESTS.md.
- Don't write speculative code for cards the user hasn't asked for — one card per skill invocation.
- If the §4 sketch turns out wrong (e.g. predicate doesn't exist, gate is misread), surface the disagreement to the user before writing the CSV row.
- Bespoke cards (Tiara, Elven Arrows, Shield-Boldur) need a `CardEquip_<Name>.php` class — that's not just CSV authoring. Pause and walk the user through the class design before scaffolding.
