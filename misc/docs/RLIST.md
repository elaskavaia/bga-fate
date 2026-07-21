# RLIST — Formal rules index

Itemized, numbered list of the formal rules in [RULES.md](RULES.md), extracted for use as a checklist when validating that each rule is correctly implemented in code.

**Conventions**
- Rule IDs follow `R.<section>.<n>`. Section numbers mirror the RULES.md table-of-contents order so they're stable across re-reads.
- "Source" is `RULES.md:<line>` for the primary rule text. "Clarif" points at [DESIGN.md §Rule clarifications](DESIGN.md) entries; "Forum" points at thread headings in [FORUM.md](FORUM.md).
- Items are intentionally short. Drill into the source for the full wording.
- Flavor-text-only items (component counts, victory narration, credits) are omitted — they are not code-validatable.

Status column:
- `?` = unverified
- `✅` = implemented + has a test pinning it
- `⚠️` = implemented but not pinned by a test, OR partial / drifts from rule text
- `❌` = not implemented (or code disagrees with rule)
- `—` = intentionally not implemented (e.g. table chat etiquette)

Statuses below were swept on **2026-06-02** by a 7-agent code-review pass. Per-row notes cite the source files / tests / TODOs that drove the verdict. The punch list of all ⚠️/❌ items is collected in **§23** at the bottom.

---

## 1. Board & terrain

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.1.1 | Map is a hex grid; each hex has one terrain type: plains, mountain, forest, or lake. | [RULES.md:38](RULES.md#L38) | [map_material.csv](../map_material.csv); test `HexMapTest::testGetHexTerrain` | ✅ |
| R.1.2 | Any area, regardless of terrain, may have **roads**. Roads affect only monster movement. | [RULES.md:38,48](RULES.md#L38) | `dir` clock-tag drives monster path; no explicit `road` column read by code (CSV has it, code uses `dir` only) | ⚠️ |
| R.1.3 | **Plains**: no special rules. | [RULES.md:44](RULES.md#L44) | `HexMapTest::testIsImpassablePlains` | ✅ |
| R.1.4 | **Mountain**: only monsters may enter. Heroes are blocked. | [RULES.md:45,167](RULES.md#L45) | `HexMap::isImpassable`; `HexMapTest::testIsImpassableMountainForHero` | ✅ |
| R.1.5 | **Forest**: a character occupying a forest hex has **cover** (modifies attack-die hit-with-cover side). | [RULES.md:46,297](RULES.md#L46) | `Character::countHit` handles `hitcov`; no dedicated cover unit test | ⚠️ |
| R.1.6 | **Lake**: no character may enter. | [RULES.md:47](RULES.md#L47) | `HexMap::isImpassable`; `HexMapTest::testIsImpassableLake` | ✅ |
| R.1.7 | Named locations are areas with names; some span multiple hexes (Troll Caves = 3 mountain hexes traversable by heroes; Nailfare = 2 lake hexes traversable by characters). | [RULES.md:50–55](RULES.md#L50) | `HexMapTest::testTrollCavesMountainPassableToHero`, `testNailfareLakePassableToHero` | ✅ |
| R.1.8 | Heroes and monsters are collectively "characters". | [RULES.md:59](RULES.md#L59) | `Character` base class | ✅ |
| R.1.9 | Each area holds at most 1 character. An occupied area blocks all movement *through* it — including teammates'. | [RULES.md:61](RULES.md#L61) | `HexMap::canStopOn`; `HexMapTest::testCanEnterHexOccupiedBlocked` | ✅ |
| R.1.10 | Moving into any area costs 1 movement regardless of terrain. | [RULES.md:61](RULES.md#L61) | `HexMap::getReachableHexes` BFS uniform cost | ✅ |
| R.1.11 | Characters may never move (or be moved) off the map. | [RULES.md:61](RULES.md#L61) | `HexMap::isValidHex`; `testIsValidHexFalse` | ✅ |

## 2. Grimheim (special area)

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.2.1 | Grimheim is a single area regardless of its visual hex count. Any number of heroes may stand there simultaneously. | [RULES.md:65](RULES.md#L65) | `testGetHexesInGrimheim`, `testGetReachableHexesEntersGrimheimButStopsThere` | ✅ |
| R.2.2 | Heroes inside Grimheim are adjacent to each other, **not** to surrounding hexes. They cannot interact with characters or terrain outside, and vice versa. | [RULES.md:65](RULES.md#L65) | `getHexesInRange` blocks pass-through; `testGetHexesInRangeGrimheimBlocksPassthrough` | ✅ |
| R.2.3 | Moving **into** Grimheim ends your current movement. Another move action/effect is required to exit. | [RULES.md:67](RULES.md#L67) | `getReachableHexes` marks Grimheim reachable but doesn't expand | ✅ |
| R.2.4 | Moving **out of** Grimheim, you may enter any adjacent area **except mountain** (heroes still can't enter mountain). | [RULES.md:67](RULES.md#L67) | implicit via `canStopOn` + impassable mountain; **no explicit "exit Grimheim → mountain blocked" test** | ⚠️ |
| R.2.5 | Monster entering Grimheim → remove the monster + 1 Town Piece (or 3 if Legend). | [RULES.md:69](RULES.md#L69) | `effect_monsterEntersGrimheim`; `testMonsterEnteringGrimheimDestroysHouse`, `testLegendEnteringGrimheimDestroys3Houses` | ✅ |
| R.2.6 | Last Town Piece removed (Freyja's Well) → players **lose**. | [RULES.md:69,128](RULES.md#L69) | `Game::isWellDestroyed`; `testGameEndsImmediatelyWhenLastHouseDestroyed` | ✅ |
| R.2.7 | Healing Effect of Freyja's Well: mend in Grimheim removes up to **5 damage** (instead of 2), and damage may be removed from hero **and equipment** cards. | [RULES.md:71,175](RULES.md#L71) | `Op_actionMend` Grimheim branch (cross-confirmed by R.6.5 agent) | ✅ |
| R.2.8 | Heroes inside Grimheim may *not* be pushed/kicked **out** by hero-driven effects (they're "in a house"). They *may* be pushed *in*, which deals 1 damage. | Clarif: §4 (Wrecking Ball) | not explicitly verified in §1–§4 sweep; depends on Wrecking-Ball + Kick semantics (see §22 R.22.4, R.22.6) | ⚠️ |

## 3. Time tracks

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.3.1 | Two alternative time tracks: short (12-turn, ~30 min/player) and long (16-turn, ~40 min/player). Pick one at setup. | [RULES.md:75,135](RULES.md#L75) | Option 101; `TimeTrackTest` | ✅ |
| R.3.2 | Each turn with **crossed axes** triggers a Reinforcements step (monster cards drawn). | [RULES.md:75,288](RULES.md#L75) | `Op_turnMonster` checks `tm_yellow_axes`/`tm_red_axes`; `testReinforcementTriggeredOnYellowAxesStep` | ✅ |
| R.3.3 | Each turn with a **skull** spot triggers a global Charge — all monsters charge that turn. | [RULES.md:75,273](RULES.md#L75) | `tm_red_skull` → `isChargeTurn=true`; `testChargeAddsOneStep` | ✅ |
| R.3.4 | If Freyja's Well remains after the **last** spot, players **win**. | [RULES.md:77,248](RULES.md#L77) | `Game::isEndOfGame` checks `currentStep >= max`; **no end-of-track-win test** | ⚠️ |

## 4. Setup

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.4.1 | Place monster tiles on supply spots. | [RULES.md:120](RULES.md#L120) | monsters live on `supply_monster` per material; no setup test asserting all seeded | ⚠️ |
| R.4.2 | Town piece count by player count: 1p=4, 2p=6, 3p=8, 4p=10 (including Freyja's Well). | [RULES.md:122–127](RULES.md#L122) | `Game.php:127` scales `2*pnum+2`; **no per-player-count test** | ⚠️ |
| R.4.3 | Bonus pickup markers placed: 3 red on Troll Caves; 3 green on Nailfare; 3 yellow on Wyrm Lair. | [RULES.md:131–133](RULES.md#L131) | `Game.php:171–173`; `Campaign_EncounterTest` covers yellow only | ⚠️ |
| R.4.4 | Choose time track; place Rune Stone on its first spot. | [RULES.md:135](RULES.md#L135) | `Game.php:110`; `TimeTrackTest` | ✅ |
| R.4.5 | Build two monster decks (yellow + red, shuffled). Each player draws 1 yellow monster card and places monsters. | [RULES.md:137](RULES.md#L137) | shuffle + first-reinforcement queued; no setup-specific draw test | ⚠️ |
| R.4.6 | Each player picks a hero deck + miniature; miniature starts in Grimheim. | [RULES.md:143](RULES.md#L143) | heroes start at home hex via material default; no explicit "all in Grimheim at setup" test | ⚠️ |
| R.4.7 | Hero card, starting equipment, and starting ability go **outside** the player board (active zone). | [RULES.md:145](RULES.md#L145) | `Game.php:143–145`; `testSetupCreatesHeroCardsInDecks` | ✅ |
| R.4.8 | Place 1 marker on green start of upgrade-cost track. Place 2 markers in the empty Action spots. | [RULES.md:147](RULES.md#L147) | upgrade marker tested (`testUpgradeCostMarkerOnTableauWithCost5`); **action markers not tested** | ⚠️ |
| R.4.9 | Shuffle the 5 remaining ability cards face down (Level I side up) on the Abilities spot. Top only visible. | [RULES.md:149](RULES.md#L149) | shuffled, L2 in limbo (`testAbilityDeckHasNoLevelIICards`); face-down rendering is UI-side | ⚠️ |
| R.4.10 | Shuffle remaining equipment cards face up on the Equipment spot. Top only visible — this is the current quest. | [RULES.md:151](RULES.md#L151) | `testSetupHeroCardsInCorrectDecks`; top-as-quest is verified separately (R.9.1) | ⚠️ |
| R.4.11 | Shuffle event cards face down on the Event Deck spot. | [RULES.md:153](RULES.md#L153) | shuffled at `Game.php:153`; no shuffle-only test | ⚠️ |
| R.4.12 | Standard start kit: 2 gold to player board, 1 mana to starting ability card, 1 event card drawn. (Skippable for harder game.) | [RULES.md:155](RULES.md#L155) | implemented; `testSetupEventCardInHand` covers the event draw only — kit components not asserted | ⚠️ |

## 5. Turn structure

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.5.1 | Players agree on a starting player; player order does not change during the game. | [RULES.md:159](RULES.md#L159) | `queueNextTurnOrEnd` loops via `getFirstPlayer`/`getNextReadyPlayerId` | ✅ |
| R.5.2 | On a hero turn, the player performs **2 different** actions by moving their action markers. Both markers may not go to the same action space. | [RULES.md:161](RULES.md#L161) | `Op_turn.php:89–91`; `testAlreadyTakenActionIsNotApplicable` | ✅ |
| R.5.3 | Free actions may be performed in any number, mixed with the main actions. | [RULES.md:163](RULES.md#L163) | `testFreeActionsStillOfferedAfterBothMainActionsTaken` | ✅ |
| R.5.4 | When the player is done acting, End of Turn fires, then the next player begins. | [RULES.md:163](RULES.md#L163) | `skip()` queues `turnEnd`; `testSkipQueuesEndOfTurn` | ✅ |
| R.5.5 | After all players have taken turns, the Monster Turn fires. | [RULES.md:163](RULES.md#L163) | `turnMonster` queued at `Game.php:837`; **no dedicated round-flow test pinning the transition** | ⚠️ |
| R.5.6 | A card-granted "specific action" is **additional** (no marker moved) — this is the only way to repeat an action this turn. | [RULES.md:179](RULES.md#L179) | `Op_performAction` queues without `placeActionMarker`; pinned by `Op_performActionTest` | ✅ |

## 6. Hero actions

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.6.1 | **Move**: move the hero up to 3 areas (4 with Embla). Cannot move through occupied areas. Heroes cannot enter mountain. | [RULES.md:167](RULES.md#L167) | `Hero::calcBaseMove` 3/Embla=4/+Wrecking Ball II=+1; move tests cover mountain + occupied blocks | ✅ |
| R.6.2 | **Attack**: roll attack-strength dice against a monster within attack range (default range 1). | [RULES.md:169,292](RULES.md#L169) | `Op_actionAttack` uses `getAttackRange`/`getAttackStrength`; range 1 + bow range 2 tested | ✅ |
| R.6.3 | **Prepare**: draw 1 event card. Hand cap 4 — may discard freely to make room. | [RULES.md:171](RULES.md#L171) | `Op_actionPrepare` → `drawEvent`; hand-limit discard prompt covered | ✅ |
| R.6.4 | **Focus**: add 1 mana to one ability or equipment that uses mana. | [RULES.md:173](RULES.md#L173) | `Op_actionFocus` → `gainMana`; `Op_actionFocusTest` | ✅ |
| R.6.5 | **Mend**: remove 2 damage from hero. In Grimheim → up to 5, also from equipment (R.2.7). | [RULES.md:175](RULES.md#L175) | `Op_actionMend` Grimheim branch; 7 mend tests | ✅ |
| R.6.6 | **Practice**: add 1 experience (yellow) to the player board. | [RULES.md:177](RULES.md#L177) | `Op_actionPractice` → `gainXp`; **no dedicated `Op_actionPracticeTest`** (only indirect coverage) | ⚠️ |
| R.6.7 | "Move +1" adds 1 step to a Move *action* only; "Move 1 area" is a separate movement. | [RULES.md:416](RULES.md#L416) | requires per-card sweep, not turn-layer code | ? |

## 7. Free actions

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.7.1 | **Use Equipment** — once per turn per card, except damage-prevention effects (those fire once per damage received). | [RULES.md:183](RULES.md#L183) | `Op_spendUse`; `testCardWithoutTriggerCannotBeUsedTwice` | ✅ |
| R.7.2 | An equipment with a **durability** value tracks damage markers as its use-counter; once full, it cannot be used again until repaired. | [RULES.md:183](RULES.md#L183) | `Op_spendDurab.php:39–42` blocks at cap | ✅ |
| R.7.3 | **Use Ability** — once per turn per card; same prevention exception. Mana paid for an ability must come from that same card. | [RULES.md:185](RULES.md#L185) | same `spendUse` mechanism; mana-from-same-card via `card` data-field threading | ✅ |
| R.7.4 | **Play Event** — play from hand, resolve effect, discard. Cannot interrupt your own ongoing action (e.g. mid-movement). | [RULES.md:187](RULES.md#L187) | events play via `useCard` from hand; **"cannot interrupt mid-action" relies on op-queue ordering, no explicit unit test** | ⚠️ |
| R.7.5 | Events with "this attack action" *may* be played after the dice roll. | [RULES.md:187](RULES.md#L187) | `Trigger::ActionAttack` chains through `Roll`; no rule-level pin | ⚠️ |
| R.7.6 | Events generally cannot be played outside your own turn — unless the card says so or it prevents damage. | [RULES.md:187](RULES.md#L187) | implicit via active-player gating, no test | ? |
| R.7.7 | **Share Gold** — give or receive gold from any adjacent hero. *Non-interactive in our implementation* (deferred). | [RULES.md:189](RULES.md#L189) | `shareGold` row commented out in `op_material.csv:60` | ❌ |
| R.7.8 | Free actions **never** count as "attack actions" even if they roll attack dice or deal damage. | [RULES.md:191](RULES.md#L191) | architectural split via `Trigger::ActionAttack` only firing from `Op_actionAttack`-reason rolls (`Op_roll.php:117`); no rule-named test | ⚠️ |
| R.7.9 | Ability rule exception: cards that grant a **permanent** stat upgrade or **trigger on other actions** are not "uses per turn" — they're always-on. | [RULES.md:420](RULES.md#L420) | correctness depends on each card not declaring `spendUse:` in its `r`; per-card sweep needed | ? |

## 8. End of Turn (hero)

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.8.1 | Reset action markers to empty spots. | [RULES.md:197](RULES.md#L197) | `Op_turnEnd.php:28–29`; `testActionMarkersResetToEmpty` | ✅ |
| R.8.2 | If you have enough XP, upgrade once (only once per turn, even if you could afford more). | [RULES.md:198,238](RULES.md#L198) | queued once per turnEnd; 14+ upgrade tests | ✅ |
| R.8.3 | Add mana to every card with mana-generation (green icon). Each card only generates onto itself. | [RULES.md:199](RULES.md#L199) | `Op_turnEnd.php:42–51`; `testManaGeneratedForCardWithManaField` | ✅ |
| R.8.4 | Draw 1 event card. If hand is full (4), only draw if you discard another card first. | [RULES.md:200](RULES.md#L200) | `Op_drawEvent` queued; hand-limit + discard covered | ✅ |
| R.8.5 | Optionally demote the top of the equipment pile OR ability pile to the bottom; you may **not** peek before deciding. | [RULES.md:201](RULES.md#L201) | `Op_demote` queued in turnEnd; 6 demote tests | ✅ |

## 9. Quests & equipment

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.9.1 | New equipment is acquired *only* by completing quests. The top of your equipment pile is your current quest. | [RULES.md:205](RULES.md#L205) | `Op_gainEquip.php:62` (`effect_gainEquipment`); `Op_completeQuest.php:40` | ✅ |
| R.9.2 | When a quest is completed, immediately move the equipment to your active zone; the next equipment card is revealed. | [RULES.md:205–207](RULES.md#L205) | `Op_gainEquip.php:97–111` | ✅ |
| R.9.3 | "Spend an action" means take the action **without** its normal effect — you complete the quest *instead of* the effect. | [RULES.md:209](RULES.md#L209) | quests use `spendAction(actionX):...:gainEquip` pattern | ✅ |
| R.9.4 | Quests may span multiple turns. Progress is tracked with crystals/damage dice on the equipment card. | [RULES.md:209](RULES.md#L209) | red-crystal tracker on deck-top equip via `Op_gainTracker` + `countTracker`; 8+ quests use this | ✅ |
| R.9.5 | Any number of equipment cards may be in play. Restriction: only **1 Main Weapon** at a time. New Main Weapon replaces the old. | [RULES.md:211](RULES.md#L211) | `mw` flag triggers displacement; parked crystals swept (`Op_gainEquip.php:67–91`) | ✅ |

## 10. Experience, gold, upgrades

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.10.1 | Gold and XP are interchangeable (one resource). | [RULES.md:217](RULES.md#L217) | both use `crystal_yellow`; `Op_gainXp`, `Op_spendXp`, `Op_spendGold` share pool | ✅ |
| R.10.2 | Killing a monster grants XP equal to its rank: rank 1 → 1, rank 2 → 2, rank 3 → 3. | [RULES.md:219–221](RULES.md#L219) | monster CSV `xp` column equals rank; awarded via `Hero::gainXp` (`Monster.php:53–61`) | ✅ |
| R.10.3 | Legends grant XP per their card text (typically more). | [RULES.md:223](RULES.md#L223) | Legend XP 3–7 in monster CSV; same `countMonsterXp` path | ✅ |
| R.10.4 | Upgrade option A: gain a new ability. | [RULES.md:233](RULES.md#L233) | `Op_upgrade::resolveGain` (`Op_upgrade.php:107–128`); `Campaign_UpgradeTest.php:91` | ✅ |
| R.10.5 | Upgrade option B: improve a card (flip L1→L2). | [RULES.md:235](RULES.md#L235) | `Op_upgrade::resolveImprove`; transfers parked tokens; tested | ✅ |
| R.10.6 | At most 1 upgrade per turn. | [RULES.md:238](RULES.md#L238) | `Op_upgrade` queued exactly once in `Op_turnEnd.php:39` | ✅ |
| R.10.7 | If the new ability has mana-generation, it generates this same turn. | [RULES.md:239](RULES.md#L239) | mana-gen applied immediately after card move (`Op_upgrade.php:114–121`); **not asserted in any test** | ⚠️ |
| R.10.8 | At the red square on the cost track, all subsequent upgrades cost 10. | [RULES.md:240](RULES.md#L240) | `min(cost+1, 10)` cap (`Op_upgrade.php:95`); no explicit "red-square" position concept; **no 10-cap test** | ⚠️ |

## 11. Monster Turn

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.11.1 | Step 1 — Move the Time Marker one step forward. | [RULES.md:248](RULES.md#L248) | `Op_turnMonster::advanceTimeTrack()` | ✅ |
| R.11.2 | Step 2 — Roll the Monster Dice (gated by option). | [RULES.md:250–259](RULES.md#L250) | `isMonsterDieOn()` gate at `Op_turnMonster.php:34` | ✅ |
| R.11.3 | Step 3 — Monsters Move. | [RULES.md:261–280](RULES.md#L261) | `monsterMoveAll` queued at `Op_turnMonster.php:41` | ✅ |
| R.11.4 | Step 4 — Monsters Attack. | [RULES.md:282–284](RULES.md#L282) | `monsterAttackAll` queued at `Op_turnMonster.php:46` after `AfterMonsterMove` trigger | ✅ |
| R.11.5 | Step 5 — Reinforcements (only on axes spots). | [RULES.md:286–288](RULES.md#L286) | spot-type-gated at `Op_turnMonster.php:49–55`; deck color matches axes color | ✅ |

## 12. Monster Dice (variant)

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.12.1 | Variant is opt-in (table-creation only per MDICE.md A1). | [RULES.md:252](RULES.md#L252) | Option 102; `var_monster_die` immutable at runtime | ✅ |
| R.12.2 | **Maneuver (CW)** — in player order, all monsters adjacent to that hero simultaneously rotate CW around that hero. May rotate into Grimheim. | [RULES.md:254](RULES.md#L254) | `Op_monsterDieManeuver` iterates `getPlayerColors()` (**by player_id, NOT first-player turn order** — diverges from rulebook "in player order"); CW math + Grimheim entry handled | ⚠️ |
| R.12.3 | **Maneuver (CCW)** — like R.12.2 but counter-clockwise. | [RULES.md:255](RULES.md#L255) | same code path with `clockwise=false`; same player-order caveat | ⚠️ |
| R.12.4 | **Attack** — all monsters get +1 strength this turn. | [RULES.md:256](RULES.md#L256) | `Op_monsterAttack.php:181` adds +1 when side === `attack`; A8 wasted-attack log present | ✅ |
| R.12.5 | **Push** — each hero adjacent to a monster is pushed 1 area toward Grimheim along the monster path. | [RULES.md:257](RULES.md#L257) | `Op_monsterDiePush` uses `getMonsterNextHex`; skips Grimheim heroes | ✅ |
| R.12.6 | **Charge** — all rank-1 monsters charge this turn. Does not stack with skull-turn charge. | [RULES.md:258](RULES.md#L258) | `Op_monsterMoveAll.php:58` rank-1 + side `charge` → +1; explicit `!isChargeForMonster` non-stack guard | ✅ |
| R.12.7 | **Ambush** — each hero must place 1 new goblin adjacent. Heroes in Grimheim are **skipped**. Empty supply → log + skip. | [RULES.md:259](RULES.md#L259) | `Op_monsterDieAmbush.php:25` skips Grimheim heroes; empty supply / no-adj-hex delegated to `Op_spawn` | ✅ |

## 13. Monsters Move (Step 3)

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.13.1 | **Golden rule 1** — monsters adjacent to a hero NEVER move in this phase. | [RULES.md:264](RULES.md#L264) | `Op_monsterMoveAll.php:112` adjacency check skips movement | ✅ |
| R.13.2 | **Golden rule 2** — monsters ALWAYS follow their path toward Grimheim. | [RULES.md:265](RULES.md#L265) | `HexMap::getMonsterNextHex` via per-hex `dir` | ✅ |
| R.13.3 | Map-edge arrows define the spawn-area direction. | [RULES.md:267](RULES.md#L267) | spawn-area arrows encoded as `dir` clock values | ✅ |
| R.13.4 | Once on a road, the monster follows the road the rest of the way to Grimheim. | [RULES.md:267](RULES.md#L267) | road hexes' `dir` points along road; same lookup | ✅ |
| R.13.5 | Resolve order: closest-to-Grimheim monsters first, farthest last. | [RULES.md:269](RULES.md#L269) | `getMonstersOnMap` sorts by distance ascending (`HexMap.php:629`) | ✅ |
| R.13.6 | A monster cannot move if its target area is occupied (by any character). | [RULES.md:269](RULES.md#L269) | `isOccupied` check at `Op_monsterMoveAll.php:123` | ✅ |
| R.13.7 | If 2 monsters would enter the same road hex, the one already on the road moves first. | [RULES.md:269](RULES.md#L269) | tiebreak is `strcmp(hex)` (`HexMap.php:633`), **NOT "on-road first"** — wrong order possible at road merge | ⚠️ |
| R.13.8 | **Charge** = +1 additional step. Never more than +1. | [RULES.md:271](RULES.md#L271) | charge is `+= 1` once; never compounded (`Op_monsterMoveAll.php:79–80`) | ✅ |
| R.13.9 | Charge cause A — Monster-Dice charge side → rank-1 monsters charge. | [RULES.md:272](RULES.md#L272) | rank-1 + die `charge` → +1 (`Op_monsterMoveAll.php:58–62`) | ✅ |
| R.13.10 | Charge cause B — skull turn → all monsters charge. | [RULES.md:273](RULES.md#L273) | `tm_red_skull` sets `isChargeTurn=true` for everyone | ✅ |
| R.13.11 | Charge cause C — attack-driven charge: monster that can't attack after normal move but could after +1 step, charges. | [RULES.md:274](RULES.md#L274) | after normal move at `Op_monsterMoveAll.php:90–100`; uses `getAttackRange` (Fire Horde reaches 2), not adjacency; tested `testFireHordeChargesIntoRange2` / `testFireHordeDoesNotChargeWhenAlreadyInRange2` | ✅ |
| R.13.12 | If a monster is **not** adjacent to a hero, it moves normally — even if that takes it out of attack range. | [RULES.md:276](RULES.md#L276) | adjacency only blocks the *start* of move, not mid-path | ✅ |
| R.13.13 | Legends **swap places** with monsters blocking their path. | [RULES.md:278](RULES.md#L278) | swap with non-Legend at `Op_monsterMoveAll.php:124–136`; tested | ✅ |
| R.13.14 | Monster entering Grimheim → tile removed + 1 Town Piece (3 for Legend). | [RULES.md:280](RULES.md#L280) | `Game.php:621–626` | ✅ |
| R.13.15 | Suppressive Fire and similar only block normal movement — not Monster-Dice rotation, Legend push, etc. Charging IS blocked. | [RULES.md:414](RULES.md#L414) | stun blocks the normal-move loop; push/legend ops don't consult `stunmarker`; **no explicit test confirming Monster-Dice rotation bypasses stun** | ⚠️ |

## 14. Monsters Attack (Step 4)

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.14.1 | Players decide the resolution order of monster attacks. | [RULES.md:284](RULES.md#L284) | **NOT implemented** — `Op_monsterAttackAll.php:24–44` auto-buckets by hero hex, no player choice. Matters when several monsters attack the same hero with chain effects | ❌ |
| R.14.2 | If a monster has multiple valid hero targets, players decide which is attacked. | [RULES.md:284](RULES.md#L284) | known TODO at `Op_monsterAttack.php:124–126`; `pickTarget` returns `$hexes[0]`; `testPicksWeakestHero` passes by hash-order luck | ⚠️ |

## 15. Reinforcements (Step 5)

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.15.1 | Only fires when the Rune Stone is on a yellow/red axes spot. | [RULES.md:288](RULES.md#L288) | `tm_yellow_axes`/`tm_red_axes` gate (`Op_turnMonster.php:49–55`) | ✅ |
| R.15.2 | In player order, each player draws 1 monster card of the matching colour and places monsters per the card. | [RULES.md:288,375](RULES.md#L288) | loops `playerCount` times drawing top (`Op_reinforcement.php:38–40`) | ✅ |
| R.15.3 | Standard monster card → place monsters at indicated location; card to bottom of deck. | [RULES.md:379](RULES.md#L379) | `cleanupMonsterDisplay` returns card to deck bottom (`Op_turnMonster.php:70–78`) | ✅ |
| R.15.4 | Legend card → place Legend tile (correct side per turn track) + accompanying monsters. | [RULES.md:381](RULES.md#L381) | yellow/red deck maps to `_1`/`_2` legend variant via `legend` column; **"correct side per turn track" (yellow vs red) NOT explicitly verified by a test** | ⚠️ |
| R.15.5 | Killed Legend → card goes to bottom of corresponding deck. | [RULES.md:383](RULES.md#L383) | same `cleanupMonsterDisplay` path | ✅ |
| R.15.6 | Card cannot be placed → discard to bottom, redraw until one fits. | [RULES.md:385–390](RULES.md#L385) | redraw loop up to 20 tries (`Op_reinforcement.php:43–60`); tests cover occupied-hex + supply-empty | ✅ |

## 16. Attacks (combat)

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.16.1 | Attacker chooses a target within attack range. Default attack range = 1. | [RULES.md:292](RULES.md#L292) | `Op_actionAttack.php:34` via `getMonsterHexesInRange(getAttackRange())` | ✅ |
| R.16.2 | Attacker's strength = sum of strength values on hero card + equipment + abilities. | [RULES.md:294](RULES.md#L294) | `Hero::getAttackStrength` reads tracker | ✅ |
| R.16.3 | Roll N attack dice. | [RULES.md:294](RULES.md#L294) | `effect_rollAttackDice` (`Game.php:427`) | ✅ |
| R.16.4 | Die side **Hit (crossed axes)** = HIT. | [RULES.md:296](RULES.md#L296) | sides 5,6 = `hit` (`dice_material.csv:8–9`) | ✅ |
| R.16.5 | Die side **Hit with cover** = HIT unless defender in forest. | [RULES.md:297](RULES.md#L297) | side 4 = `hitcov`; `Character.php:97` negates in forest | ✅ |
| R.16.6 | Die side **Miss (blank)** = MISS. | [RULES.md:298](RULES.md#L298) | sides 1,2 = `miss` | ✅ |
| R.16.7 | Die side **Rune** = MISS (some effects key off it). | [RULES.md:299](RULES.md#L299) | side 3 = `rune`; base `countHit` returns 0; Dead/Boldur overrides hook | ✅ |
| R.16.8 | "This attack action" cards may be played after the dice roll. | [RULES.md:301](RULES.md#L301) | `Trigger::Roll` and `Trigger::ActionAttack` post-roll | ✅ |
| R.16.9 | Defender prevention during resolution. | [RULES.md:301](RULES.md#L301) | `Trigger::ResolveHits` queued to defender before damage applied (`Op_resolveHits.php:122`) | ✅ |
| R.16.10 | After all modifying effects, sum hits and apply damage. | [RULES.md:301](RULES.md#L301) | `countHits` → `applyArmor` → `Op_dealDamage` → `Op_applyDamage` | ✅ |
| R.16.11 | Monster damage = damage dice; ≥ health → killed. | [RULES.md:303](RULES.md#L303) | `Op_applyDamage` places red crystals; `evaluateDamage` triggers `MonsterKilled` | ✅ |
| R.16.12 | Hero damage = red crystals; ≥ health → knocked out. | [RULES.md:304](RULES.md#L304) | same path; KO when totalDamage ≥ effectiveHealth | ✅ |

## 17. Knocked-out heroes

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.17.1 | Knocked-out hero is moved back to Grimheim. | [RULES.md:308](RULES.md#L308) | `Hero::finalizeDamage` (`Hero.php:364–368`) | ✅ |
| R.17.2 | Remove damage until hero card has **exactly 5** damage remaining. | [RULES.md:310](RULES.md#L310) | signed diff `totalDamage − 5; moveCrystals("red", -$diff)` (`Hero.php:356–361`) | ✅ |
| R.17.3 | Remove 2 town pieces from Grimheim (panic). | [RULES.md:311](RULES.md#L311) | `effect_destroyHouses(2, …)` (`Hero.php:371`) | ✅ |
| R.17.4 | No other penalty. | [RULES.md:312](RULES.md#L312) | no further penalty applied; control returns | ✅ |

## 18. Monster faction effects

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.18.1 | **Trollkin effect** — +1 strength per other adjacent trollkin. Applies to Legend trollkin. | [RULES.md:320](RULES.md#L320) | `Op_monsterAttack::getMonsterStrength`; Grendel/Hrungbald CSV `faction=trollkin` | ✅ |
| R.18.2 | **Goblin additional effect** — moves 2 areas (3 with charge). | [RULES.md:322](RULES.md#L322) | Goblin `move=2`; `Op_monsterMoveAll` loops `move` steps; charge adds +1 | ✅ |
| R.18.3 | **Fire Horde effect** — range 2. Applies to Legend fire horde. | [RULES.md:336](RULES.md#L336) | `Monster::getAttackRange` returns 2 for `firehorde`; Seer/Surt CSV match | ✅ |
| R.18.4 | **Dead effect** — runes count as hits when the dead attack. Applies to Queen. | [RULES.md:348](RULES.md#L348) | `Monster::countHit` upgrades rune → hit for `faction=dead`; Queen has it | ✅ |
| R.18.5 | **Draugr armor** — prevents 1 damage per damage event. | [RULES.md:350](RULES.md#L350) | Draugr armor=1 in CSV; `applyArmor` in `Op_resolveHits::queueDamage` | ✅ |
| R.18.6 | Per-monster stat lines per the rulebook table. | [RULES.md:324–356](RULES.md#L324) | material CSVs | ✅ |

## 19. Legends

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.19.1 | Each Legend has stats + sometimes special effects. | [RULES.md:362](RULES.md#L362) | CSV + per-legend op code (e.g. Seer II `Op_monsterAttack:38`) | ✅ |
| R.19.2 | Legend entering Grimheim destroys 3 Town Pieces. | [RULES.md:362](RULES.md#L362) | `destroyCount = isLegend ? 3 : 1` (`Game.php:622–624`) | ✅ |
| R.19.3 | Legends switch places with blockers. | [RULES.md:362,278](RULES.md#L362) | `Op_monsterMoveAll.php:123–138` | ✅ |
| R.19.4 | Each Legend tile has yellow + red sides — pick matching spot type. | [RULES.md:364](RULES.md#L364) | yellow/red deck maps to `_1`/`_2` via `legend` column in `monstercard_material.csv`; spot type drives deck choice | ✅ |
| R.19.5 | Roster: Grendel, Nidhuggr, Surt, Queen, Hrungbald, Seer of Odin. | [RULES.md:366–371](RULES.md#L366) | all 6 present in `monster_material.csv:40–51` with both variants | ✅ |
| R.19.6 | Each Legend belongs to its printed faction and shares faction abilities. | DESIGN.md "Legend factions" | factions: Queen=dead, Seer/Surt=firehorde, Grendel/Hrungbald=trollkin, Nidhuggr=wyrm; faction-keyed code applies uniformly | ✅ |
| R.19.7 | **Hrungbald** doubles trollkin support: all trollkin get +2 (not +1) per adjacent trollkin while he is on the board. | [FORUM.md:6898](FORUM.md#L6898) | `Op_monsterAttack::getMonsterStrength` uses `isHrungbaldInPlay` (either legend_5 level on a hex); `testHrungbaldDoublesTrollkinSupport` | ✅ |

## 20. Clarifications (from RULES.md "Clarifications" section)

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.20.1 | **Line of sight (range ≥ 2)** — may target through characters/mountains. Grimheim cannot be shot over. | [RULES.md:408](RULES.md#L408) | `getHexesInRange` BFS blocks Grimheim from outside, allows everything else | ✅ |
| R.20.2 | **Unpreventable damage** — recipient may not use any effect to prevent it. | [RULES.md:410](RULES.md#L410) | **no `unpreventable` flag** — pattern is "bypass `dealDamage`": Seer-of-Odin and `Op_spendHealth` queue `applyDamage` directly, skipping preventDamage. Works ad-hoc, no general flag. | ⚠️ |
| R.20.3 | **Adjacency to terrain type** — character is adjacent to terrains of its own hex AND adjacent hexes. | [RULES.md:412](RULES.md#L412) | **BUG**: `Op_adj.php:46` iterates only `getAdjacentHexes($hex)`, never the hero's own hex. Hero standing in a forest surrounded by plains does NOT satisfy "adjacent to forest" — contradicts rule. | ❌ |
| R.20.4 | **Suppressive Fire** blocks only normal monster movement. Charging blocked; rotations/pushes not. | [RULES.md:414](RULES.md#L414) | `stunmarker` checked only inside `Op_monsterMoveAll.php:45`; push/legend ops don't consult it | ✅ |
| R.20.5 | **Wrecking Ball** — push 1 character, may swap into Boldur's came-from hex, never off-board, never into occupied. | [RULES.md:418](RULES.md#L418) | `Op_c_wrecking.php:84` uses `canStopOn` | ✅ |
| R.20.6 | **Abilities: free actions vs. ongoing** — once-per-turn cap doesn't apply to permanent / on-action-trigger / prevent-damage effects. | [RULES.md:420](RULES.md#L420) | `spendUse` only declared on once-per-turn cards; trigger-only / always-on cards omit it | ✅ |

## 21. Adjusting difficulty / Solo

| ID | Rule | Source | Clarif / Forum / Code | Status |
|---|---|---|---|---|
| R.21.1 | Monster Dice variant is an opt-in difficulty toggle. | [RULES.md:396](RULES.md#L396) | Option 102 | ✅ |
| R.21.2 | Long time track is an opt-in difficulty toggle. | [RULES.md:397](RULES.md#L397) | Option 101 | ✅ |
| R.21.3 | Skipping the starting kit is an opt-in difficulty toggle. | [RULES.md:398](RULES.md#L398) | **no gameoption** for skip-starting-kit; only 100/101/102 exist | ❌ |
| R.21.4 | Starting 1 step up on the upgrade-cost track is an opt-in difficulty toggle. | [RULES.md:399](RULES.md#L399) | **no gameoption** for starting +1 on upgrade track | ❌ |
| R.21.5 | Solo mode uses identical rules. | [RULES.md:404](RULES.md#L404) | Option 100 + `var_solo_board` (`Game.php:658`) | ✅ |

## 22. Designer rulings beyond RULES.md

| ID | Ruling | Source | Code | Status |
|---|---|---|---|---|
| R.22.1 | **Queen of the Hill (Embla I/II)** — hero always moves into target; target must be hero-enterable. | DESIGN.md §1 | `Op_c_queen` enforces enterable target | ✅ |
| R.22.2 | **Orebiter** — attack action slot consumed; target is adj mountain hex. | DESIGN.md §2 | `Op_c_orebiter` consumes attack slot | ✅ |
| R.22.3 | **Sweeping Strike** — Boldur sweeps CW, max 2 enemies. | DESIGN.md §3 | `Op_c_sweep` clockwise, capped at 2 | ✅ |
| R.22.4 | **Wrecking Ball** details — pendulum/swap, +1 Move passive, may push into Grimheim (deals 1 damage), cannot push *out* of Grimheim. | DESIGN.md §4 | pendulum + passive +1 move (`Hero.php:244`); push uses `canStopOn`. **Push always deals 1 damage regardless of destination** — matches card text | ✅ |
| R.22.5 | **Event deck reshuffles when exhausted.** | DESIGN.md §5 | `autoreshuffle_custom` (`Game.php:65`) | ✅ |
| R.22.6 | **Hero card effects CAN push monsters/characters into Grimheim** (Kick, Wrecking Ball). | DESIGN.md §6 | **BUG**: `Op_moveMonster.php:106` explicitly strips Grimheim hexes from push targets — contradicts designer Kick ruling | ❌ |
| R.22.7 | **Event discard pile is face up** (public). | DESIGN.md §7 | standard top-card-visible stack; no hide flag (`Board.scss:298`) | ✅ |
| R.22.8 | **Stitching cross-tableau repair.** | DESIGN.md §8 | `Op_removeDamage.php:31` extends to adjacent heroes | ✅ |
| R.22.9 | **Windbite chain** — every rune adds another die; runes STAY on table. | DESIGN.md §9 | `countNewRunes` + high-water-mark in `addRoll` (`Game.php:561`) | ✅ |
| R.22.10 | **Displaced Main Weapons strip all parked tokens.** | DESIGN.md §10 | `Op_gainEquip.php:78` strips crystals on displacement | ✅ |
| R.22.11 | **"Move X" = "up to X".** | DESIGN.md §11 | hero move ops use "up to" semantics | ✅ |

---

## 23. Implementation gaps

For the live punch list of all ⚠️/❌/? items above (bugs, behaviour drift, missing options, untested coverage, per-card sweeps), see **[TODO.md → "Rules-sweep gaps"](TODO.md#rules-sweep-gaps-rlist-2026-06-02)**. That section is the working backlog; the status cells in this file (§1–§22) are the source of truth for "is rule X covered" at sweep time.

When a gap from TODO.md is closed, update the matching row's Status here (`⚠️`/`❌` → `✅`) and re-run the sweep if the change touches multiple rules.

---

## How to use this list

1. When implementing or reviewing a feature, find the R.x.y entries that apply.
2. Update the **Status** column as you ship: `?` → `✅` (implemented + test) or `⚠️` (implemented, untested) or `❌` (deliberately skipped or actual gap).
3. When a rule changes (designer ruling, errata), update the row and adjust §23 if appropriate.
4. When a new designer clarification lands in DESIGN.md or FORUM.md, append to §22 if it's a new ruling, or update the relevant row's "Clarif / Forum" column.

## Known gaps in this list

- §16 doesn't yet break down per-die-side card interactions (Magic Runes, Piercing Arrows, Wildfire, Bone Bane Bow, etc.) — those are card-level, not rulebook-level; will live in card-spec docs.
- Monster-card placement details (per-card listings) are data, not rules — see `card_monster_material.csv`.
- Per-Legend special effects are in their own card data; rule R.19.1 is the umbrella.
- The "Adjusting Difficulty" option about contacting a Fryxelius brother is jokey flavor and intentionally out of scope. 🙂
