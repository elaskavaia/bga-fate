---
name: game-verify-card
description: Verify a game card works correctly by checking its CSV definition, operation implementation, test coverage, and PLAN.md status. Use this skill when the user wants to verify, validate, or check a card (e.g., "/game-verify-card card_event_1_33"), or when they ask about a card's implementation status, test coverage, or readiness.
argument-hint: card ID
---

# Card Verification Skill

Verify that a Fate card is correctly implemented by cross-referencing its definition, implementation, and test coverage.

## Input

The user provides a card ID as argument (e.g., `card_event_1_33`, `card_equip_1_15`, `card_ability_1_5`, `card_hero_1_1`).

The card ID format is `card_{type}_{heroNumber}_{cardNumber}`. Extract the type from the second segment to find the right CSV file.

## Verification Steps

Perform all these steps. Use parallel tool calls where possible to speed things up.

### 1. Look up the card definition

Read the appropriate CSV file based on card type:

- `card_ability_*` -> `misc/card_ability_material.csv`
- `card_event_*` -> `misc/card_event_material.csv`
- `card_equip_*` -> `misc/card_equip_material.csv`
- `card_hero_*` -> `misc/card_hero_material.csv`

The CSV uses `|` as delimiter. The header row defines fields. Find the row where `num` matches the card number and `hno` matches the hero number. Extract all fields, especially:

- **name**: card name
- **r**: the rule/operation string (the machine-readable implementation)
- **on**: trigger condition (e.g., `roll`, `actionAttack`, `monsterMove`, `monsterKilled`)
- **effect**: human-readable card text describing what the card does
- **Other stats**: strength, health, durability, mana, attack_range (vary by card type)

### 2. Check PLAN.md status

Read `misc/docs/PLAN.md` and find the card's entry in the "Bjorn Card Validation" section (or other hero sections if they exist). Note:

- Whether it's checked `[x]` or unchecked `[ ]`
- Any notes after the card description (e.g., "has tests", "verify", "custom")

### 3. Find existing tests

Search `tests/Campaign/` for references to this card ID and for the card name in test method names (e.g., `testNailedTogetherIPiercesDamage` for `card_ability_1_13`).

### 4. Check operation implementation

Examine the `r` field:

- If `r` is exactly `custom` — the card is **not implemented at all**. The `r` field needs to be figured out first: either express it as a rule expression using existing operations, extend an existing operation, or create a new custom operation (`Op_c_*.php`). This is the primary blocker — flag it prominently.
- Otherwise — verify that the `r` expression semantically matches the card's effect text. For example, `2heal(self)` should match "Heal 2 damage from Bjorn", `rerollMisses` should match "Reroll all misses". Flag any mismatches.

### 5. Create missing integration tests

If the card is implemented (r is not `custom`) but integration tests are missing or incomplete, **delegate to the `game-create-itest` skill**, passing the card ID as argument. That skill owns the test patterns, scaffolding, debug workflow, and hex/flow cheat-sheets — don't duplicate that logic here.

After `game-create-itest` returns, include its test method name(s) in the Output Format's "Tests found" field.

### 6. When done check the card in the plan

## Output Format

Present a structured report AFTER creating the tests:

```
## Card Verification: {name} ({card_id})

### Definition
- **Type**: {ability/event/equip/hero}
- **Rule (r)**: {r field value, or "none/passive" if empty}
- **Trigger (on)**: {on field value, or "none" if empty}
- **Effect**: {effect text}
- **Stats**: {any non-empty stat fields}

### PLAN.md Status
- {[x] or [ ]} {description from PLAN.md}
- Notes: {any additional notes}

### Implementation
- **Operations used**: {list of Op_*.php files involved}
- **Custom operation**: {if r=custom: "NOT IMPLEMENTED — r field needs to be designed"; if r=c_*: whether Op file exists}

### Test Coverage
- **Tests found**: {list of test files and methods referencing this card}
- **Trigger positive test**: {Yes/No/N/A}
- **Trigger negative test**: {Yes/No/N/A}
- **Integration test**: {Yes/No}

### Validation Checklist
- [ ] r field matches effect text description
- [ ] r field is not `custom` (i.e., rule is designed — either expression, c_* op, or standard ops)
- [ ] Trigger positive conditions tested (if on != empty)
- [ ] Trigger negative conditions tested (if on != empty)
- [ ] Integration test exists and passes

### Recommendations
{Bulleted list of specific things that need to be done, or "Card is fully verified" if everything checks out}
```

## Important

- For the "r field matches effect text" check, compare the semantic meaning: e.g., `2heal(self)` should match "Heal 2 damage from Bjorn", `rerollMisses` should match "Reroll all misses". Flag mismatches but don't try to auto-fix.
- If the card has `r=custom`, it is not implemented at all. The first step is to design the rule: figure out a rule expression, extend an existing operation, or create a new custom `Op_c_*.php`. This is the primary blocker — flag it prominently and skip the remaining checks (tests, triggers) since there's nothing to test yet.
- When checking tests, look for both the card ID string and the card name in test method names (e.g., `testNailedTogetherIPiercesDamage` for `card_ability_1_13`).
