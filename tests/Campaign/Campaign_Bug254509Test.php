<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #254509: Nailed Together is offered after a Sure Shot kill, but Sure Shot is a free action
 * and RULES.md says "Free actions never count as "attack actions", even if they roll attack dice
 * or deal damage."
 */
class Campaign_Bug254509Test extends CampaignBaseTest {
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]);
        $this->color = $this->getActivePlayerColor();
        $this->clearEquipDecks();
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
    }

    public function testNailedTogetherOfferedAfterSureShotKill(): void {
        $sureShotId = "card_ability_1_3";
        $nailedId = "card_ability_1_13";
        $heroId = $this->game->getHeroTokenId($this->color);
        $this->game->tokens->moveToken($nailedId, "tableau_$this->color");
        $this->game->effect_moveCrystals($heroId, "green", 2, $sureShotId, ["message" => ""]);

        $this->moveHeroOutOfGrimheim("hex_7_9");
        $goblin = "monster_goblin_20";
        $brute = "monster_brute_1";
        $this->game->getMonster($goblin)->moveTo("hex_6_9", "");
        $this->game->getMonster($brute)->moveTo("hex_5_9", "");

        $this->respond($sureShotId);
        $this->confirmCardEffect();
        $this->respond("hex_6_9");

        // documents buggy behavior for BGA #254509; flip assertion when fixed
        $this->assertValidTarget($nailedId);
        $this->respond($nailedId);
        $this->confirmCardEffect();
        $this->assertEquals("supply_monster", $this->tokenLocation($goblin));
        $this->assertEquals(1, $this->countDamage($brute));
    }
}
