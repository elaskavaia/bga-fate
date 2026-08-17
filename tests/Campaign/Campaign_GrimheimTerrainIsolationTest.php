<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Grimheim terrain isolation (maintainer ruling, follow-up to BGA #238473).
 *
 * RULES.md:65 - heroes inside Grimheim "cannot interact with characters or
 * terrain outside of it and vice versa". So a hero in town is NOT adjacent to
 * any mountain, even on border hex_9_10 whose neighbor hex_9_11 is a mountain
 * (the one RULES.md:67 excludes when leaving town). countAdjMountains must
 * return 0 there, and Mining Equipment / Orebiter must not be usable from
 * inside the walls.
 */
class Campaign_GrimheimTerrainIsolationTest extends CampaignBaseTest {
    private string $heroId;
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // solo Boldur - owner of both mountain cards
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
    }

    /** Map sanity: the guard is only meaningful if a mountain really borders town. */
    public function testMapFactMountainBordersGrimheim(): void {
        $this->assertTrue($this->game->hexMap->isInGrimheim("hex_9_10"));
        $this->assertEquals("mountain", $this->game->hexMap->getHexTerrain("hex_9_11"));
        $this->assertContains("hex_9_11", $this->game->hexMap->getAdjacentHexes("hex_9_10"));
    }

    public function testCountAdjMountainsIsZeroInsideGrimheim(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_9_10");

        $this->assertEquals(0, $this->game->countAdjMountains($this->color), "no terrain adjacency from inside town");
    }

    public function testMiningEquipmentNotOfferedInsideGrimheim(): void {
        $miningEquipment = "card_equip_4_17";
        $this->game->tokens->moveToken($miningEquipment, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_9_10");
        $this->game->hexMap->invalidateOccupancy();

        $this->assertNotValidTarget($miningEquipment, "no mountain interaction from inside town");
    }

    public function testOrebiterNotOfferedInsideGrimheim(): void {
        $orebiter = "card_equip_4_19";
        $this->game->tokens->moveToken($orebiter, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_9_10");
        $this->game->hexMap->invalidateOccupancy();

        $attack = $this->game->machine->instantiateOperation("actionAttack", $this->color);
        $this->assertArrayNotHasKey($orebiter, $attack->getPossibleMoves(), "Orebiter must not join the attack list in town");
    }
}
