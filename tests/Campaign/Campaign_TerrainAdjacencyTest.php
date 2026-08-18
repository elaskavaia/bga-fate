<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Terrain adjacency for heroes: which mountains countAdjMountains sees, and how the mountain cards
 * (Dvalin's Pick, Mining Equipment, Orebiter) follow it.
 *
 * BGA #238473 (DESIGNER report): RULES.md:412 "Adjacency - You are considered to be adjacent to the
 * terrain type of your own area as well as adjacent areas." Designer ruling on the report: "When
 * counting number of adjacent terrain areas, count the one you are currently in." countAdjMountains
 * only walked the neighbor ring, so a hero standing on a Troll Caves mountain (RULES.md:54 - heroes
 * may occupy them) with 2 more mountains adjacent counted 2 instead of 3.
 *
 * Grimheim is the exception (maintainer ruling, follow-up to the same report): RULES.md:65 - heroes
 * inside Grimheim "cannot interact with characters or terrain outside of it and vice versa". So a
 * hero in town is NOT adjacent to any mountain, even on border hex_9_10 whose neighbor hex_9_11 is
 * a mountain (the one RULES.md:67 excludes when leaving town).
 */
class Campaign_TerrainAdjacencyTest extends CampaignBaseTest {
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

    /** Designer's scenario: on hex_6_6 (Troll Caves mountain) with mountains hex_5_7 and hex_6_7 adjacent. */
    public function testOwnMountainHexCountsAsAdjacent(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_6_6");

        $this->assertEquals(3, $this->game->countAdjMountains($this->color), "own mountain hex + 2 neighbors = 3");
    }

    /** hex_6_5 is plains with exactly one mountain neighbor (hex_6_6) - neighbor counting unchanged. */
    public function testNonMountainHexCountsOnlyNeighbors(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_6_5");

        $this->assertEquals(1, $this->game->countAdjMountains($this->color), "plains own hex adds nothing");
    }

    /** hex_9_9 is Grimheim (plains terrain, all neighbors Grimheim plains) - the own-hex rule adds nothing in town. */
    public function testGrimheimHexCountsNoMountains(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_9_9");

        $this->assertEquals(0, $this->game->countAdjMountains($this->color), "own Grimheim hex adds nothing");
    }

    public function testCountAdjMountainsIsZeroInsideGrimheim(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_9_10");

        $this->assertEquals(0, $this->game->countAdjMountains($this->color), "no terrain adjacency from inside town");
    }

    /**
     * Consumer path: Dvalin's Pick (card_equip_4_20, quest_on=TMove,
     * quest_r=check('countAdjMountains>=3'):gainEquip) must auto-claim when the
     * move ends ON a Troll Caves mountain with 2 more mountains adjacent.
     */
    public function testDvalinsPickClaimsWhenMoveEndsOnTrollCavesMountain(): void {
        $dvalinsPick = "card_equip_4_20";
        $nextCard = "card_equip_4_19"; // Orebiter
        $this->seedDeck("deck_equip_" . $this->color, [$dvalinsPick, $nextCard]);

        $this->game->tokens->moveToken($this->heroId, "hex_6_5");

        $this->assertValidTarget("hex_6_6", "Troll Caves mountain is hero-passable");
        $this->respond("hex_6_6");

        $this->assertEquals(
            "tableau_" . $this->color,
            $this->tokenLocation($dvalinsPick),
            "Dvalin's Pick should land on tableau - own mountain + 2 adjacent = 3"
        );

        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_" . $this->color);
        $this->assertNotNull($newTop, "deck_equip should have a new top card");
        $this->assertEquals($nextCard, $newTop["key"], "Orebiter should surface as the new deck-top");
    }

    public function testMiningEquipmentNotOfferedInsideGrimheim(): void {
        $miningEquipment = "card_equip_4_17";
        $this->game->tokens->moveToken($miningEquipment, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_9_10");
        $this->game->hexMap->invalidateOccupancy();

        $this->assertNotValidTarget($miningEquipment, "no mountain interaction from inside town");
    }

    /**
     * Orebiter (card_equip_4_19) end to end from ON the mountain: the own hex is
     * offered, the gold vein placed there resolves as the defender (not the hero
     * sharing the hex), and each hit pays 1 gold [XP]. Neighbor-hex mining is
     * covered by Campaign_BoldurEquipTest::testOrebiterMinesGoldFromAdjacentMountain.
     */
    public function testOrebiterMinesTheMountainHeroStandsOn(): void {
        $orebiter = "card_equip_4_19";
        $this->game->tokens->moveToken($orebiter, "tableau_" . $this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_6_6");

        $xpBefore = $this->countXp();
        $damageBefore = $this->countDamage($this->heroId);
        $strength = $this->game->getHero($this->color)->getAttackStrength();
        $this->seedRand(array_fill(0, $strength, 5)); // all hits

        // No monsters on the map: the attack auto-resolves onto the Orebiter card,
        // then Op_c_orebiter prompts for the mountain hex.
        $this->respond("actionAttack");
        $this->assertValidTarget("hex_6_6", "own mountain hex is an Orebiter target");
        $this->respond("hex_6_6");

        $this->assertEquals($xpBefore + $strength, $this->countXp(), "1 gold per damage dealt");
        $this->assertEquals("supply_monster", $this->tokenLocation("monster_goldvein"));
        $this->assertEquals($damageBefore, $this->countDamage($this->heroId), "hero must not become his own defender");
        $this->assertEquals("hex_6_6", $this->tokenLocation($this->heroId), "hero stays on the mined hex");
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
