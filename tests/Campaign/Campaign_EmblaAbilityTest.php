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
}
