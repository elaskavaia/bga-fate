<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #257482: Limber Bow range +2 lost when a quest completes mid-turn (recalcTrackers),
 * so a later Sure Shot II in the same turn has range 2 again.
 */
class Campaign_Bug257482Test extends CampaignBaseTest {
    private string $heroId;
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]);
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearEquipDecks();
        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8", "card_monster_9", "card_monster_10"]);
        $this->seedDeck("deck_event_$this->color", ["card_event_1_27_1", "card_event_1_27_2"]);
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
    }

    private function playLimberBow(): void {
        $this->seedHand("card_event_1_32", $this->color);
        $this->respond("card_event_1_32");
        $this->confirmCardEffect();
        $this->assertEquals(4, $this->game->getHero($this->color)->getAttackRange());
    }

    private function killBrute(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_11_8");
        $this->game->getMonster("monster_brute_1")->moveTo("hex_12_8", "");
        $this->game->machine->push("dealDamage", $this->color, ["target" => "hex_12_8", "count" => 3]);
        $this->game->machine->dispatchAll();
    }

    public function testLimberBowRangeSurvivesKillWithoutQuest(): void {
        $this->playLimberBow();
        $this->killBrute();
        $this->assertEquals("supply_monster", $this->tokenLocation("monster_brute_1"));
        $this->assertEquals(4, $this->game->getHero($this->color)->getAttackRange());
    }

    public function testLimberBowRangeLostWhenQuestCompletesMidTurn(): void {
        $this->seedDeck("deck_equip_$this->color", ["card_equip_1_21", "card_equip_1_17"]);
        $this->playLimberBow();
        $this->killBrute();
        $this->confirmCardEffect();
        $this->confirmGainEquip();
        $this->assertEquals("tableau_$this->color", $this->tokenLocation("card_equip_1_21"));
        // documents buggy behavior for BGA #257482; flip the assertion when the fix lands (expected 4)
        $this->assertEquals(2, $this->game->getHero($this->color)->getAttackRange());
    }
}
