<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #256311: Stitching ignores heroes elsewhere in Grimheim.
 */
class Campaign_BjornStitchingGrimheimTest extends CampaignBaseTest {
    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1, 2]); // Bjorn, Alva
        $this->clearMonstersFromMap();
    }

    /** documents buggy behavior for BGA #256311; flip the assertion when the fix lands */
    public function testStitchingIInGrimheimIgnoresDamagedHeroOnNonAdjacentGrimheimHex(): void {
        $color = $this->getActivePlayerColor();
        $bjorn = $this->game->getHeroTokenId($color);
        $this->assertEquals("hero_1", $bjorn);
        $alva = "hero_2";

        $grimheim = $this->game->hexMap->getHexesInGrimheim();
        $bjornHex = $grimheim[0];
        $alvaHex = null;
        foreach ($grimheim as $hex) {
            if ($hex !== $bjornHex && $this->game->hexMap->getHexDistance($bjornHex, $hex) > 1) {
                $alvaHex = $hex;
                break;
            }
        }
        $this->assertNotNull($alvaHex, "need two non-adjacent Grimheim hexes");
        $this->game->tokens->moveToken($bjorn, $bjornHex);
        $this->game->tokens->moveToken($alva, $alvaHex);

        $this->game->tokens->dbSetTokenLocation("card_ability_1_7", "tableau_$color", 0);
        $this->game->effect_moveCrystals($bjorn, "red", 2, $bjorn, ["message" => ""]);
        $this->game->effect_moveCrystals($bjorn, "red", 4, $alva, ["message" => ""]);

        $this->respond("card_ability_1_7");
        $this->confirmCardEffect();

        // RULES "Grimheim": heroes inside Grimheim are adjacent to each other, so Alva should
        // be offered. Current code only checks hex adjacency: repairCard has no target, heal(adj)
        // sees only Bjorn, both auto-resolve with no prompt.
        $this->assertOperation("turn");
        $this->assertEquals(1, $this->countDamage($bjorn));
        $this->assertEquals(4, $this->countDamage($alva));
    }
}
