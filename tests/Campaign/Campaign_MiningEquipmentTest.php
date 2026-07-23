<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Pins the current (buggy) behavior for BGA #233793: Boldur's Mining Equipment
 * (card_equip_4_17) cannot be clicked/used when standing adjacent to a mountain.
 *
 * Card data: r = spendDurab:custom, durability = 2.
 * Effect text: [DAMAGE] gain 2 gold adjacent to 1 mountain / 3 gold adjacent to 3.
 *
 * The test asserts the card is NOT offered (green today). Flip the final assertion
 * to assertValidTarget once the :custom gain half is implemented.
 */
class Campaign_MiningEquipmentTest extends CampaignBaseTest {
    private string $heroId;
    private string $cardId = "card_equip_4_17";

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    public function testMiningEquipmentCurrentlyNotClickable(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($this->cardId, "tableau_$color");

        // Boldur on hex_5_8 (forest) - adjacent mountain at hex_5_7 (as in Orebiter test).
        $this->game->tokens->moveToken($this->heroId, "hex_5_8");
        $this->game->hexMap->invalidateOccupancy();

        // Sanity: hero on hex_5_8 is adjacent to mountain hex_5_7.
        $this->assertEquals("mountain", $this->game->hexMap->getHexTerrain("hex_5_7"));
        $neighbors = $this->game->hexMap->getAdjacentHexes("hex_5_8");
        $this->assertContains("hex_5_7", $neighbors, "hero hex must be adjacent to the mountain");

        // Root cause: r = "spendDurab:custom" parses to Op_paygain, whose gain half
        // "custom" is the unimplemented Op_custom stub (always ERR_NOT_APPLICABLE).
        // Op_paygain::getPossibleMoves treats the card as void if ANY delegate is void,
        // so useCard never offers it and the card cannot be clicked.
        //
        // BUGGY BEHAVIOR pinned for BGA #233793: Mining Equipment's :custom gain half is
        // unimplemented so the card is never offered; flip this assertion once the effect
        // is implemented.
        $this->assertNotValidTarget($this->cardId, "Mining Equipment is currently not clickable (BGA #233793)");
    }
}
