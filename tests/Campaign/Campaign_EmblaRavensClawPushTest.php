<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Raven's Claw (card_equip_3_22, quest_on=TStep, "Enter 10 forest areas") vs
 * Monster Die push moving Embla into a forest.
 */
class Campaign_EmblaRavensClawPushTest extends CampaignBaseTest {
    public function testMonsterDiePushIntoForestDoesNotTickRavensClaw(): void {
        $this->setupGame([3]); // Solo Embla
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);
        $ravensClaw = "card_equip_3_22";
        $this->seedDeck("deck_equip_$color", [$ravensClaw, "card_equip_3_16"]);

        $plainsHex = "hex_10_3"; // push dir leads to hex_10_4
        $forestHex = "hex_10_4";
        $this->assertNotEquals("forest", $this->game->hexMap->getHexTerrain($plainsHex));
        $this->assertEquals("forest", $this->game->hexMap->getHexTerrain($forestHex));
        $this->game->tokens->moveToken($heroId, $plainsHex);
        $this->game->getMonster("monster_goblin_20")->moveTo("hex_10_2", "");

        $this->game->machine->push("monsterDiePush", $color);
        $this->game->machine->dispatchAll();

        $this->assertEquals($forestHex, $this->tokenLocation($heroId), "push should move Embla into the forest");
        // Documents buggy behavior for BGA #240644; flip the assertion when fixed (expected 1)
        $this->assertEquals(0, $this->countTokens("crystal_red", $ravensClaw), "push into forest does not count toward Raven's Claw");
    }
}
