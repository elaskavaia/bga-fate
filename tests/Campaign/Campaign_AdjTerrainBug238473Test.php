<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #238473: standing on a terrain must count as adjacent to it.
 *
 * RULES.md:412 "Adjacency - You are considered to be adjacent to the terrain
 * type of your own area as well as adjacent areas." Designer ruling on the
 * report: "When counting number of adjacent terrain areas, count the one you
 * are currently in." countAdjMountains only walked the neighbor ring, so a
 * hero standing on a Troll Caves mountain (RULES.md:54 - heroes may occupy
 * them) with 2 more mountains adjacent counted 2 instead of 3.
 */
class Campaign_AdjTerrainBug238473Test extends CampaignBaseTest {
    private string $heroId;
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // solo Boldur - the countAdjMountains consumers are his quest cards
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearMonstersFromMap();
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
}
