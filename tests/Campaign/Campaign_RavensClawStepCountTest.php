<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

class Campaign_RavensClawStepCountTest extends CampaignBaseTest {
    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([3]); // Solo Embla
        $this->clearMonstersFromMap();
    }

    public function testThreeForestStepsCountOncePerArea(): void {
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);
        $ravensClaw = "card_equip_3_22";
        $this->seedDeck("deck_equip_$color", [$ravensClaw, "card_equip_3_16"]);

        $from = "hex_9_1";
        $to = "hex_12_1";
        $this->game->tokens->moveToken($heroId, $from);
        $path = $this->game->hexMap->getPath($from, $to, $this->game->getHero($color));
        $this->assertCount(3, $path);
        foreach ($path as $hex) {
            $this->assertEquals("forest", $this->game->hexMap->getHexTerrain($hex), $hex);
        }

        $this->game->machine->push("move", $color, ["target" => $to, "count" => 3, "reason" => "Op_actionMove"]);
        $this->game->machine->dispatchAll();

        $this->assertEquals($to, $this->tokenLocation($heroId));
        // Documents buggy behavior for BGA #240884; flip the assertion when fixed (expected 3)
        $this->assertEquals(4, $this->countTokens("crystal_red", $ravensClaw));
    }
}
