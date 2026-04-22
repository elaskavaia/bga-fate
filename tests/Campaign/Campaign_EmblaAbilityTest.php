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
}
