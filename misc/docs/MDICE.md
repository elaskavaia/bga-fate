# Monster Dice Plan

Plan document for adding the **Monster Dice** optional variant to Fate.
Status: **draft, not implemented**.

> Scope: a single d6 rolled at the start of every Monster Turn, gated behind a game option. Visual/material layer is already shipped; this plan covers the gameplay wiring.

---

## 1. What's already in place

| Piece | Status | Notes |
|---|---|---|
| `die_monster` token | ✅ | [token_material.csv:45](../token_material.csv) — `count=1`, `location=supply_die_monster` |
| 6-side definitions | ✅ | [dice_material.csv:10–16](../dice_material.csv) — `maneuver_1`, `maneuver_2`, `attack`, `push`, `charge`, `ambush` |
| Sprite + SCSS | ✅ | [Tokens.scss:92–97](../../src/css/Tokens.scss#L92) — `data-state` → bottom row of `dice.png`, mirrors attack-die pattern |
| Display location | ✅ | `display_monsterturn` div + `supply_die_monster` container in board layout ([Game.ts:82, 92](../../src/Game.ts#L82)) |
| Debug helper | ✅ | `Game::debug_dice()` ([Game.php:1018](../../modules/php/Game.php#L1018)) places a sample die for visual verification |

What's missing: any **mechanical** behavior. No game option, no roll op, no per-side effect handlers, no turn-flow integration.

---

## 2. The 6 sides

Per [RULES.md:250–259](RULES.md). Effects are **per-turn**, applied to **all monsters** for that one Monster Turn.

| Side | Name | Effect |
|---|---|---|
| 1 | `maneuver_1` | All monsters rotate **clockwise** one hex around their nearest adjacent hero (no rotation if not adjacent to any hero). |
| 2 | `maneuver_2` | Same as side 1, but **counter-clockwise**. |
| 3 | `attack` | Every monster gets **+1 strength** for this turn's attack step. |
| 4 | `push` | Every hero adjacent to a monster is pushed **1 hex toward Grimheim**. |
| 5 | `charge` | All **rank 1** monsters get **+1 move** for this turn (compounds with normal move). |
| 6 | `ambush` | Each hero must place **1 new goblin** on an adjacent hex. |

---

## 3. Proposed data model

### 3.1 Game option

Add monster-die toggle to [gameoptions.json](../../gameoptions.json), mirror time-track shape (option `101`):

```json
{ "id": 102, "name": "Monster Die", "values": {
    "0": { "name": "Off", "description": "Recommended for first game" },
    "1": { "name": "On" }
}, "default": 0 }
```

Wire as game-state label `var_monster_die` in `Game::__construct` ([Game.php:48](../../modules/php/Game.php#L48) sets `var_long_track` for option 101 the same way). Access via `getGameStateValue("var_monster_die") === 1`. Helper: `Game::isMonsterDieOn(): bool`.

### 3.2 Per-turn modifier storage

Two effects (`attack`, `charge`) modify monster behavior **for the rest of the current Monster Turn**. They need to survive between ops in the queue but reset at end of turn.

Use a transient game-state value `monster_die_side` (set by `Op_rollMonsterDie`, cleared by `Op_turnMonster` end). Readers:
- `Game::getMonsterStrength($hex)` adds +1 when `monster_die_side === "attack"`.
- `Op_monsterMoveAll` adds +1 step-budget to rank-1 monsters when `monster_die_side === "charge"`.

The other four effects (`maneuver_*`, `push`, `ambush`) execute as one-shot ops queued by `Op_rollMonsterDie::resolve` and don't need to persist.

### 3.3 New operations

- `Op_rollMonsterDie` — picks `die_monster_1` from supply, rolls 1–6, parks it on `display_monsterturn`, reads the side from material, then queues the per-side handler.
- `Op_monsterDieManeuver` (param: `cw` or `ccw`) — for each monster on the map, if adjacent to a hero, rotate one hex around that hero in the given direction. Skip if not adjacent.
- `Op_monsterDiePush` — for each hero adjacent to a monster, push 1 hex toward Grimheim. Reuses `HexMap::getDistanceMapToGrimheim()` (already exists, used by `closerToGrimheim` Math term).
- `Op_monsterDieAmbush` — for each hero, prompt the **active player on whose turn this fires** (or the hero's owner — see §6) to pick an adjacent hex, then `effect_spawnMonster("goblin", $hex)`. Reuse [`Op_spawn`](../../modules/php/Operations/Op_spawn.php) (landed in commit `1a54160`).

`attack` and `charge` don't get their own op — they just stash `monster_die_side` and let the existing `Op_monsterAttack` / `Op_monsterMoveAll` read it.

### 3.4 Turn-flow integration

Today `Op_turnMonster` queues something like `monsterMoveAll, monsterAttackAll, …`. Insert `rollMonsterDie` **before** `monsterMoveAll` when the option is on (RULES.md step 2 — roll, then move).

```
Op_turnMonster::resolve():
  if isMonsterDieOn(): queue rollMonsterDie
  queue monsterMoveAll
  queue monsterAttackAll
  // …existing tail
```

After all monster ops complete, sweep `monster_die_side` back to empty in the same op that ends the monster turn.

---

## 3.5 Assumptions (locked-in design decisions)

These are settled and feed §4. Listed here so reviewers don't have to dig through §6 history.

- **A1 — Game option is table-creation only.** `var_monster_die` is set from option `102` at game setup and is immutable for the remainder of the game. Mirrors the time-track option pattern. No runtime toggle.
- **A2 — Maneuver tie-break: closest-to-Grimheim hero.** When a monster is adjacent to multiple heroes during a `maneuver_*` resolve, it rotates around the hero closest to Grimheim (lowest `getDistanceMapToGrimheim` value). Deterministic; matches the `closerToGrimheim` Math term already used elsewhere. Tiebreaker if two heroes are equidistant: lowest hex-id.
- **A3 — Push uses the same step logic as monster movement.** Reuse `HexMap::getMonsterNextHex($heroHex)` — the per-hex `dir` tag drives the choice, exactly as in [Op_monsterMoveAll](../../modules/php/Operations/Op_monsterMoveAll.php). The dir tag is deterministic, so there is no tie-breaking question. If `getMonsterNextHex` returns `null`, hero stays. If the next hex is `isOccupied($nextHex)`, hero stays (mirror Op_monsterMoveAll line 110). Heroes entering Grimheim is a normal move — no house destruction (that rule is monster-specific).
- **A4 — Maneuver mechanics (per designer clarification).** Hero in center; clockwise = next adjacent hex "to the right" (analog-clock dials). Movement is **simultaneous** — if 6 monsters surround a hero, all 6 move at once. A monster's rotation can be **blocked by another hero** occupying the destination hex (in which case that monster doesn't move). A monster **can rotate into Grimheim** during maneuver — this triggers the standard "monster enters Grimheim" path (destroy houses), same as a normal move-in. Note: A4 does not resolve A2 (adjacent-to-multiple-heroes tie-break) — A2 still applies for picking the rotation anchor.
- **A5 — Charge mirrors the existing skull-turn charge.** When `monster_die_side === "charge"`, rank-1 monsters get `$moveSteps += 1` in [Op_monsterMoveAll::moveMonster()](../../modules/php/Operations/Op_monsterMoveAll.php#L64). The extra step still respects the normal "stop if hero-adjacent" rule (`moveMonsterOneStep` line 99). No special "lunge past the stop rule" behavior — same shape as the skull-turn charge already shipped.
- **A6 — Ambush + empty supply: log and skip per-hero.** If the goblin supply is empty when an `ambush` side fires, log a translatable message (`No goblins left in the supply`) and skip the spawn for that hero. Continue processing the remaining heroes. No substitution with other monster types. Verbose-but-honest, consistent with player expectation.
- **A7 — Ambush hex picker: active player resolves all heroes.** A single ambush op is queued (not one per hero); the active player whose Monster Turn is firing picks an adjacent hex for each hero in turn order. Faster than per-player prompting, no cross-player handoff. If a hero has zero valid adjacent hexes (all blocked), auto-skip that hero with a log line (composes with A6).
- **A8 — Attack-side wasted: log it.** When `attack` is rolled but no monster attacks this turn (no monster adjacent to any hero), no special mechanic fires. Add a translatable log line acknowledging the no-op (e.g. `Monsters get +1 strength but have no targets this turn`). No re-roll, no consolation effect.
- **A9 — Legends affected uniformly.** Monster Die effects apply to legends and regular monsters the same way — no per-card opt-out. If a future legend needs to ignore a specific die side, add a flag at that point. Default is "all monsters means all monsters."

---

## 4. Per-effect implementation notes

### Maneuver (cw / ccw)

**Hardest of the six.** Hex grids have 6 neighbors per hex. "Rotate one hex clockwise around a hero" means: monster M is on hex `m`, hero on adjacent hex `h`. Find the index of `m` in `h`'s neighbor list, advance by ±1 mod 6, that's the new target. Skip if the target is occupied / blocked.

If a monster is adjacent to **multiple heroes**, pick one — proposal: closest-to-Grimheim hero (deterministic). Open question §6.

### Attack (+1 strength)

One-line change to `Game::getMonsterStrength($hex)`: `if (getGameStateValue("monster_die_side") === "attack") return $base + 1`. Already called per-attack at [Op_monsterAttack.php:38](../../modules/php/Operations/Op_monsterAttack.php#L38).

### Push (toward Grimheim)

Iterate heroes; for each hero adjacent to ≥1 monster, find the neighbor hex with the lowest distance-to-Grimheim, move the hero there. If multiple neighbors tie — proposal: hero picks (interactive prompt) or deterministic by hex-id sort. Open question §6.

### Charge (+1 move for rank-1 monsters)

In `Op_monsterMoveAll`, when computing per-monster step budget: `$steps = $monster->move + (isCharge() && rank===1 ? 1 : 0)`. The existing TODO comment at [Op_monsterMoveAll.php:50](../../modules/php/Operations/Op_monsterMoveAll.php#L50) is the exact hook.

### Ambush (spawn goblin adjacent to each hero)

For each hero, queue `Op_spawn` with `param=goblin, target=adj(hero)`. Open question: who picks the hex? Probably the hero's player picks (one ambush op per player, prompt their hand for a target hex).

---

## 5. Client display

- Roll the monster die into `display_monsterturn` — already wired visually. The tween is just the existing dice-roll animation reused.
- Log line: `${MonsterTurn} rolls ${SideName}` (translatable).
- For `maneuver_*` and `push`, animate the hex moves the same way hero/monster moves are animated today.
- For `ambush`, route through the existing spawn animation (already used by `Op_spawn` for Leather Purse / Elven Arrows).
- No new badges, no new HUD elements — the die sits on `display_monsterturn` for the duration of the monster turn, then sweeps back to `supply_die_monster`.

---

## 6. Edge cases and open questions

> Per Victoria's convention: questions stay in this doc, get answered inline, item is removed when resolved.

1. ~~Game option timing.~~ → see §3.5 A1.

2. ~~Maneuver tie-break.~~ → see §3.5 A2.

3. ~~Push tie-breaking.~~ → see §3.5 A3.

4. ~~Push into a blocked hex.~~ → see §3.5 A3.

5. ~~Push into Grimheim.~~ → see §3.5 A3.

6. ~~Charge ordering.~~ → see §3.5 A5.

7. ~~Ambush goblin supply exhaustion.~~ → see §3.5 A6.

8. ~~Ambush — who picks the hex?~~ → see §3.5 A7.

9. ~~Maneuver into hero's hex.~~ Strike — geometrically impossible (rotation around a neighbor never lands you on the neighbor).

10. ~~Attack-side die rolled but monster turn has no attacks.~~ → see §3.5 A8.

11. ~~Interaction with bespoke monster cards (legends).~~ → see §3.5 A9.

---

## 7. Implementation phases

Smallest viable shippable units first:

1. **Phase D1 — Game option + roll op.** Add option 102 to `gameoptions.json`, wire `var_monster_die` label, add `isMonsterDieOn()` helper. Implement `Op_rollMonsterDie` that just rolls + parks the die + logs (no effects yet). Hook into `Op_turnMonster`. Test: turn off, no roll. Turn on, die rolls and shows on `display_monsterturn`.
2. **Phase D2 — Attack & Charge (passive effects).** Add `monster_die_side` state value, set/clear in roll op. Modify `getMonsterStrength` and `Op_monsterMoveAll` to read it. Tests: roll attack → +1 strength on all monster attacks that turn. Roll charge → rank-1 monsters move +1.
3. **Phase D3 — Push.** Implement `Op_monsterDiePush`. Reuse `getDistanceMapToGrimheim`. Test: hero adjacent to a monster, roll push → hero moves toward Grimheim.
4. **Phase D4 — Ambush.** Implement `Op_monsterDieAmbush` per hero, reusing `Op_spawn`. Test: roll ambush → each hero prompted to place a goblin adjacent.
5. **Phase D5 — Maneuver (cw / ccw).** Implement the hex-rotation primitive in `HexMap` (or as a helper in `Op_monsterDieManeuver`). Test: monster adjacent to a hero, roll maneuver_1 → monster rotates one hex clockwise around hero.
6. **Phase D6 — Client polish.** Translatable log lines per side, completion log line on monster die sweep.

D1 + D2 are the smallest shippable feature (visible roll, two passive effects). D3–D5 each ship independently.

---

## 8. Files that will change

- [gameoptions.json](../../gameoptions.json) — option 102
- [modules/php/Game.php](../../modules/php/Game.php) — `var_monster_die` label, `isMonsterDieOn()`, `getMonsterStrength` +1 hook
- [modules/php/Operations/Op_turnMonster.php](../../modules/php/Operations/Op_turnMonster.php) — queue `rollMonsterDie` first when option is on
- [modules/php/Operations/Op_monsterMoveAll.php](../../modules/php/Operations/Op_monsterMoveAll.php) — charge +1 hook (TODO at line 50 already exists)
- [modules/php/Operations/Op_rollMonsterDie.php](../../modules/php/Operations/) — **new**, rolls die + dispatches per-side handler
- [modules/php/Operations/Op_monsterDieManeuver.php](../../modules/php/Operations/) — **new**, cw/ccw rotation
- [modules/php/Operations/Op_monsterDiePush.php](../../modules/php/Operations/) — **new**, push heroes toward Grimheim
- [modules/php/Operations/Op_monsterDieAmbush.php](../../modules/php/Operations/) — **new**, per-hero goblin spawn
- [modules/php/Common/HexMap.php](../../modules/php/Common/HexMap.php) — hex rotation primitive (only if Maneuver lands)
- [misc/op_material.csv](../op_material.csv) — register the new ops
- [src/Game.ts](../../src/Game.ts) — animation glue if any (most reused)
- `tests/Operations/Op_rollMonsterDieTest.php` — per-side branch coverage
- `tests/Campaign/Campaign_MonsterDieTest.php` — full monster-turn integration per side

---

## 9. Out of scope

- **Player-rolled monster die** (some house rules let players roll). The variant is GM-style; engine rolls.
- **Custom monster die faces.** Six fixed sides per the rulebook.
- **Re-rolling.** No re-roll affordance in the rulebook for this die.
- **Showing the die between turns.** The die only sits on `display_monsterturn` during a monster turn, then sweeps back to supply.
