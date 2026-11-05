<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #253550: Singing Bow quest (counter(countDice) on TRoll) double-counts
 * the base dice when Mastery adds 4 dice to an attack.
 */
class Campaign_Bug253550Test extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([2]);
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    public function testMasteryAddRollDoubleCountsSingingBowProgress(): void {
        $color = $this->getActivePlayerColor();
        $bow = "card_equip_2_20";
        $mastery = "card_event_2_27_1";
        $this->seedDeck("deck_equip_$color", [$bow, "card_equip_2_22"]);
        $this->seedHand($mastery, $color);

        $this->game->tokens->moveToken($this->heroId, "hex_2_8");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_3_7", "");

        $this->seedRand([1, 1, 1, 1, 1, 1, 1]);
        $this->respond("actionAttack");
        $this->assertEquals(3, $this->countTokens("crystal_red", $bow), "3 base dice in forest");

        $this->assertOperation("useCard");
        $this->respond($mastery);
        $this->confirmCardEffect();
        $this->skipIfOp("useCard");

        // documents buggy behavior for BGA #253550; flip assertion when fixed (expect 7 crystals, bow still in deck)
        $this->assertEquals("tableau_$color", $this->tokenLocation($bow), "Bow claimed: 3 + 7 (all display dice) >= 10");
    }
}
