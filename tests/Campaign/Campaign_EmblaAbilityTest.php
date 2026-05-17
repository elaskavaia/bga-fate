<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Embla's ability and hero cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_EmblaAbilityTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([3]); // Solo Embla
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
        $this->clearEquipDecks();
    }

    // --- Queen of the Hill I (card_ability_3_11) ---
    // r=2c_queen, on=empty → manual activation.
    // "Deal 2 damage to an adjacent monster and switch places with it."

    public function testQueenOfTheHillIDealsDamageAndSwaps(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_3_11";
        $this->game->tokens->moveToken($cardId, "tableau_$color");

        // Place Embla on plains with a brute (health=3) adjacent — 2 damage leaves it alive
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_7_8", "");

        $this->respond($cardId);
        $this->respond("hex_7_8"); // Op_c_queen prompts for adjacent monster hex

        // Brute took 2 damage, survived, swapped onto hero's old hex
        $this->assertEquals(2, $this->countDamage($brute));
        $this->assertEquals("hex_7_9", $this->tokenLocation($brute));
        // Embla moved into the brute's old hex
        $this->assertEquals("hex_7_8", $this->tokenLocation($this->heroId));
    }

    public function testQueenOfTheHillIKillsWeakMonsterAndHeroStillMoves(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_3_11";
        $this->game->tokens->moveToken($cardId, "tableau_$color");

        // Goblin (health=2) dies from 2 damage; hero still moves into the vacated hex
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $goblin = "monster_goblin_1";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");

        $this->respond($cardId);
        $this->respond("hex_7_8");

        // Goblin killed → off the map
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
        // Embla moved into vacated hex — the movement is the tactic
        $this->assertEquals("hex_7_8", $this->tokenLocation($this->heroId));
    }

    // --- Queen of the Hill II (card_ability_3_12) ---
    // r=4c_queen, on=empty → manual activation.
    // "Deal 4 damage to an adjacent monster and switch places with it."

    public function testQueenOfTheHillIIDealsFourDamageAndSwaps(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_3_12";
        $this->game->tokens->moveToken($cardId, "tableau_$color");

        // Troll (health=6) survives 4 damage — swap should happen
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        $this->respond($cardId);
        $this->respond("hex_7_8");

        $this->assertEquals(4, $this->countDamage($troll));
        $this->assertEquals("hex_7_9", $this->tokenLocation($troll));
        $this->assertEquals("hex_7_8", $this->tokenLocation($this->heroId));
    }

    // --- In Charge I (card_ability_3_5) ---
    // r=killMonster(adj,'rank==1'), on=TActionMove — after each move action, may kill
    // an adjacent rank 1 monster.

    public function testInChargeIKillsAdjacentRank1MonsterAfterMove(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_3_5";
        $this->game->tokens->moveToken($cardId, "tableau_$color");

        // Park Embla at hex_6_9 (plains). After move to hex_7_9, goblin at hex_7_8 is adjacent
        // and is rank 1; brute at hex_6_9's old neighbor would be rank 2 — non-eligible.
        $this->game->tokens->moveToken($this->heroId, "hex_6_9");
        $goblin = "monster_goblin_20";
        $brute = "monster_brute_1";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");
        $this->game->getMonster($brute)->moveTo("hex_8_9", ""); // adjacent to hex_7_9 (in Grimheim, but rank 2)

        // Move Embla — turn op inlines hex targets for actionMove
        $this->respond("hex_7_9");

        // TActionMove trigger queues useCard with In Charge I
        $this->assertOperation("useCard");
        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        // killMonster(adj, rank==1) prompts for a hex — only the goblin qualifies
        $this->assertValidTarget("hex_7_8");
        $this->assertNotValidTarget("hex_8_9"); // brute is rank 2, not offered
        $this->respond("hex_7_8");

        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
        $this->assertEquals("hex_8_9", $this->tokenLocation($brute)); // brute untouched
        $this->assertEquals("hex_7_9", $this->tokenLocation($this->heroId));
    }

    // --- In Charge II (card_ability_3_6) ---
    // r=killMonster(adj,'rank<=2'), on=TActionMove — after each move action, may kill
    // an adjacent rank 1 OR rank 2 monster. Superset of In Charge I.

    public function testInChargeIIKillsAdjacentRank2MonsterAfterMove(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_3_6";
        $this->game->tokens->moveToken($cardId, "tableau_$color");

        // Park Embla at hex_6_9. After move to hex_7_9: brute (rank 2) at hex_7_8 adjacent,
        // troll (rank 3) at hex_8_9 also adjacent but non-eligible.
        $this->game->tokens->moveToken($this->heroId, "hex_6_9");
        $brute = "monster_brute_1";
        $troll = "monster_troll_1";
        $this->game->getMonster($brute)->moveTo("hex_7_8", "");
        $this->game->getMonster($troll)->moveTo("hex_8_9", "");

        $this->respond("hex_7_9");

        $this->assertOperation("useCard");
        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        $this->assertValidTarget("hex_7_8"); // brute eligible (rank 2)
        $this->assertNotValidTarget("hex_8_9"); // troll is rank 3, not offered
        $this->respond("hex_7_8");

        $this->assertEquals("supply_monster", $this->tokenLocation($brute));
        $this->assertEquals("hex_8_9", $this->tokenLocation($troll)); // troll untouched
        $this->assertEquals("hex_7_9", $this->tokenLocation($this->heroId));
    }

    // --- Reaper Swing I (card_ability_3_9) ---
    // r=c_reaper, on=TActionAttack. "In each attack action, you may divide the damage
    // you deal between the target and another adjacent monster."
    // c_reaper picks a monster adjacent to PRIMARY (may be out of hero's reach),
    // then Op_resolveHits prompts for the split via choice_N buttons (primary hit count).

    public function testReaperSwingISplitsDamageBetweenPrimaryAndSecondary(): void {
        $cardId = "card_ability_3_9";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($cardId, "tableau_$color");

        // Embla at hex_7_9. Primary brute adjacent to hero; secondary brute adjacent to
        // primary (but NOT to hero — exercises the out-of-reach convention).
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $primary = "monster_brute_1";
        $secondary = "monster_brute_2";
        $this->game->getMonster($primary)->moveTo("hex_7_8", "");
        $this->game->getMonster($secondary)->moveTo("hex_6_8", "");

        // Embla I(3) + Flimsy Blade(1) = 4 dice, all hits.
        $this->seedRand([5, 5, 5, 5]);
        $this->respond("hex_7_8");

        // TActionAttack trigger queues useCard with Reaper Swing as a target.
        $this->assertOperation("useCard");
        $this->assertValidTarget($cardId);
        $this->respond($cardId);

        // Op_c_reaper prompts for the secondary (adjacent to primary). hex_6_8 is
        // adjacent to primary hex_7_8 but NOT to the hero at hex_7_9.
        $this->respond("hex_6_8");

        // Op_resolveHits now has secondary set — prompts for split. choice_2 = 2 to primary.
        $this->respond("choice_2");

        // 2 damage to each brute (both survive, health=3).
        $this->assertEquals(2, $this->countDamage($primary));
        $this->assertEquals(2, $this->countDamage($secondary));
        $this->assertEquals("hex_7_8", $this->tokenLocation($primary));
        $this->assertEquals("hex_6_8", $this->tokenLocation($secondary));
    }

    // --- Reaper Swing II (card_ability_3_10) ---
    // Same r=c_reaper as I, but strength +3 — boosts the attack's die count.
    // Embla I(3) + Flimsy Blade(1) + Reaper II(3) = 7 dice.

    public function testReaperSwingIISplitsBoostedDamage(): void {
        $cardId = "card_ability_3_10";
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        $this->game->getHero($color)->recalcTrackers();

        // Trolls (health=6) so a 3+4 split leaves both alive — easier to assert damage.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $primary = "monster_troll_1";
        $secondary = "monster_troll_2";
        $this->game->getMonster($primary)->moveTo("hex_7_8", "");
        $this->game->getMonster($secondary)->moveTo("hex_6_8", "");

        // 7 dice, all hits — proves strength bonus is applied.
        $this->seedRand([5, 5, 5, 5, 5, 5, 5]);
        $this->respond("hex_7_8");

        $this->assertOperation("useCard");
        $this->assertValidTarget($cardId);
        $this->respond($cardId);

        $this->respond("hex_6_8");
        $this->respond("choice_3"); // 3 to primary, 4 to secondary

        $this->assertEquals(3, $this->countDamage($primary));
        $this->assertEquals(4, $this->countDamage($secondary));
        $this->assertEquals("hex_7_8", $this->tokenLocation($primary));
        $this->assertEquals("hex_6_8", $this->tokenLocation($secondary));
    }

    // --- Riposte I (card_ability_3_3) ---
    // r=2spendMana:(2preventDamage:2dealDamage), on=TResolveHits, mana=1.
    // Embla's *starting* ability. 2[MANA]: Prevent up to 2 damage dealt by an
    // adjacent monster, and deal 2 damage to it.

    public function testRiposteIPreventsDamageAndDealsCounterDamageToAttacker(): void {
        $color = $this->getActivePlayerColor();
        $riposte = "card_ability_3_3";

        // Riposte I is Embla's starting ability — already in tableau with 1 mana.
        // Op_turnEnd will top it up further; we just need ≥2 by the time Riposte fires.
        $this->assertEquals("tableau_$color", $this->tokenLocation($riposte));

        // Park a goblin (strength 1) adjacent so it attacks Embla on the monster turn.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $goblin = "monster_goblin_20";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");
        $this->seedRand([5]); // 1 hit guaranteed

        // Burn both actions → monster turn → goblin attacks Embla.
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        $this->skipOp("drawEvent");

        // TResolveHits fires before the hit lands → useCard prompt offers Riposte.
        $this->assertOperation("useCard");
        $this->assertValidTarget($riposte);
        $manaBefore = $this->countTokens("crystal_green", $riposte);
        $this->respond($riposte);

        // Embla took 0 damage (1 incoming, up to 2 prevented).
        $this->assertEquals(0, $this->countDamage($this->heroId), "Embla should take no damage");
        // Goblin (health=2) took 2 counter-damage → dies.
        $this->assertEquals(
            "supply_monster",
            $this->tokenLocation($goblin),
            "Goblin should be killed by Riposte counter-damage (2 dmg, health=2)"
        );
        // Riposte spent 2 mana from its own crystals.
        $this->assertEquals($manaBefore - 2, $this->countTokens("crystal_green", $riposte), "2 mana spent off Riposte I");
    }

    // --- Riposte II (card_ability_3_4) ---
    // r=3spendMana:(3preventDamage:3dealDamage), on=TResolveHits, mana=2.
    // 3[MANA]: Prevent up to 3 damage, deal 3 damage to attacker.

    public function testRiposteIIPreventsAndDealsCounterDamage(): void {
        $color = $this->getActivePlayerColor();
        $riposte = "card_ability_3_4";
        // Flip from L1 to L2 — swap Riposte I out, put Riposte II in.
        $this->game->tokens->moveToken("card_ability_3_3", "limbo");
        $this->game->tokens->moveToken($riposte, "tableau_$color");
        $this->game->effect_moveCrystals($this->heroId, "green", 3, $riposte, ["message" => ""]);

        // Use a brute (str=3) so 3 hits land — Riposte II prevents all 3.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_7_8", "");
        $this->seedRand([5, 5, 5]); // 3 hits guaranteed

        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        $this->skipOp("drawEvent");

        $this->assertOperation("useCard");
        $manaBefore = $this->countTokens("crystal_green", $riposte);
        $this->respond($riposte);

        $this->assertEquals(0, $this->countDamage($this->heroId), "Embla should take no damage");
        // Brute (health=3) takes 3 counter-damage → dies.
        $this->assertEquals("supply_monster", $this->tokenLocation($brute), "Brute killed by Riposte II");
        $this->assertEquals($manaBefore - 3, $this->countTokens("crystal_green", $riposte), "3 mana spent");
    }
}
