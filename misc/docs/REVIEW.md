# Design Review: Machine/Operation/Card/Trigger

High-level review of the architectural shape. Not a code-nitpick pass — this is about whether the abstractions earn their keep and what shape changes would pay off most.

## 1. "Everything is an Operation" is mostly right — but passive effects don't belong here

The machine pays off for user-driven decisions: undo, uniform dispatch, deterministic replay. But passive stat modifiers (Eagle Eye's +1 range, Cape's damage reduction) are read via `getRulesFor()` at query time and never enter the queue. That's two sources of truth — the machine for active decisions, Material for static ones — and they don't compose cleanly when a single attack wants to combine both.

**Opinion:** Keep Operations for revertible user decisions. Build a thin passive-effects layer (attribute modifiers registered by cards on enter) that Characters consult when computing stats. Don't force passive effects into the queue just because they're card behavior.

## 2. The dual card-effect language (CSV `r` DSL + bespoke PHP classes) — the split is correct, just keep resisting DSL creep

Simple cards → CSV operator string. Complex cards (multiple triggers, branching, lookahead) → PHP subclass. The rule is already in practice: once a card needs more than a linear operator expression, it graduates to `on=custom`. Extending the DSL to handle those cases would turn it into JSON-in-a-string, which is worse than just writing PHP.

**Opinion:** Keep the current split. The risk isn't that the boundary is unclear — it's that the DSL will be *tempted* to grow (conditionals, variables, multi-trigger dispatch) to avoid writing PHP classes, and every such addition makes CSV harder to read for the designers it was meant to serve. Document the rule ("DSL = single linear effect; anything else = PHP class") in DESIGN.md so future-you doesn't relitigate it. The real maintenance cost here is small.

## 3. Trigger dispatch as "walk all cards and ask" should become subscription

Op_trigger iterates every tableau+hand card for every trigger and relies on `canTrigger()` to opt out. That's fine at 4 cards per player, but it also means a card's "I care about roll" lives in three places: Material `on` field, `canTrigger()`, and the `onRoll()` hook. Forget one, silent bug.

**Opinion:** Flip it. When a card enters the tableau, it *subscribes* to the triggers it cares about. `queueTrigger("roll")` then emits to subscribers only. Single source of truth per card (the subscribe call), O(subscribers) not O(cards), and bespoke-class behavior stops needing three coordinated pieces.

## 4. The Operation → Countable / Complex inheritance is an abstraction tax

Operations that want to be *both* countable and composite can't cleanly inherit both, so ComplexOperation ends up extending CountableOperation "just in case," and simple ops that want a count have to buy the whole Countable base. Classic single-inheritance pain.

**Opinion:** Flatten to traits/mixins: `Operation` base + `Countable` trait + `Complex` trait. Mix in what you need per op. PHP traits fit this exactly — each axis is independent and shouldn't force a linearization.

## 5. CSV-as-source-of-truth is good; code-gen-to-PHP is the part to reconsider

CSV as the design-facing schema is correct — designers edit one file, everything stays consistent. But generating PHP *classes* from CSV blurs the line: are the generated sections data or code? If you need to change a card's cost, do you edit CSV (yes), regen, review a PHP diff (awkward), and commit generated output? That's code-review overhead for what is really a config change.

**Opinion:** Shift from "generate PHP from CSV" toward "load CSV (or JSON) at runtime as a Material database." Keep code-gen only for things that genuinely need static types (operator registry, trigger enum, token type constants). Material facts (cost, damage, `r` expression, `on` field) should just be data. Faster iteration, smaller diffs, clearer ownership.

---

## The two changes that would matter most

If only two things happened in the next architectural pass:

**(a) Split passive effects out of the machine** — solves the two-sources-of-truth problem and makes stat trackers, card rule changes, and trigger reactions all explainable with one mental model.

**(b) Move trigger dispatch to subscription** — collapses three scattered concepts (`on` field, `canTrigger`, `onXxx` hook) into one registration point per card and makes trigger behavior obvious at a glance.

The trait refactor and runtime material loading are cleanup that becomes easier after those two.
