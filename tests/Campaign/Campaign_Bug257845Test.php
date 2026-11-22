<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/** BGA #257845: Bone Bane Bow rune damage to a forest monster adjacent to a killed Draugr. */
class Campaign_Bug257845Test extends CampaignBaseTest {
    public function testBoneBaneBowDamagesForestSkeletonWhenDraugrKilled(): void {
        $this->setupGame([1]);
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);
        $this->clearHand($color);
        $this->clearEquipDecks();
        $this->clearMonstersFromMap();
        $bow = "card_equip_1_16";
        $this->game->tokens->moveToken($bow, "tableau_$color");
        $this->game->tokens->moveToken("card_equip_1_15", "limbo"); // Bone Bane Bow replaces First Bow as main weapon

        $this->game->tokens->moveToken($heroId, "hex_7_9");
        $this->assertEquals("forest", $this->game->hexMap->getHexTerrain("hex_5_8"));
        $this->assertContains("hex_5_8", $this->game->hexMap->getAdjacentHexes("hex_5_9"));
        $this->game->getMonster("monster_draugr_1")->moveTo("hex_5_9", "");
        $this->game->getMonster("monster_skeleton_1")->moveTo("hex_5_8", "");
        $this->game->effect_moveCrystals("supply_red", "red", 4, "monster_draugr_1", ["message" => ""]);

        $this->seedRand([3, 5, 5]); // 1 rune + 2 hits: 2 - 1 armor = 1 kills the 1-health-left Draugr
        $this->respond("hex_5_9");
        $this->assertOperation("useCard");
        $this->assertValidTarget($bow);
        $this->respond($bow);
        $this->skipIfOp("useCard");

        $this->assertEquals("supply_monster", $this->tokenLocation("monster_draugr_1"), "Draugr killed");
        $this->assertEquals(1, $this->countDamage("monster_skeleton_1"), "Skeleton takes 1 rune damage");
    }
}
