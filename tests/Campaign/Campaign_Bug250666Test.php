<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/** BGA #250666: Windbite on a rune rolled by Throwing Darts. */
class Campaign_Bug250666Test extends CampaignBaseTest {
    public function testWindbiteOnThrowingDartsRuneAfterEarlierDartsKill(): void {
        $this->setupGame([2]);
        $color = $this->getActivePlayerColor();
        $heroId = $this->game->getHeroTokenId($color);
        $this->clearMonstersFromMap();
        $this->clearHand($color);
        $this->game->tokens->moveToken("card_equip_2_15", "limbo");
        $this->game->tokens->moveToken("card_equip_2_19", "tableau_$color");
        $this->game->tokens->moveToken("card_equip_2_17", "tableau_$color");
        $this->game->getHeroById($heroId)->recalcTrackers();
        $this->game->tokens->moveToken($heroId, "hex_7_9");
        $adjacent = array_values(
            array_filter(
                $this->game->hexMap->getAdjacentHexes("hex_7_9"),
                fn($hex) => !$this->game->hexMap->isInGrimheim($hex) &&
                    in_array($this->game->material->getRulesFor($hex, "terrain", ""), ["plains", "forest"])
            )
        );
        [$goblinHex, $bruteHex] = $adjacent;

        // Earlier Darts throw kills a goblin; nothing resets marker_attack afterwards
        $this->game->getMonster("monster_goblin_20")->moveTo($goblinHex, "");
        $this->seedRand([5, 5, 5]);
        $this->respond("card_equip_2_17");
        $this->assertEquals("supply_monster", $this->tokenLocation("monster_goblin_20"));
        $this->assertEquals($goblinHex, $this->game->getAttackHex(), "stale marker_attack left on the dead goblin's hex");

        // Next Darts throw (use reset as on a later turn) rolls a rune against a brute
        $this->game->tokens->setTokenState("card_equip_2_17", 0);
        $this->game->getMonster("monster_brute_1")->moveTo($bruteHex, "");
        $this->seedRand([3, 1, 1, 5]);
        $this->respond("card_equip_2_17");
        $this->assertOperation("useCard");
        $this->assertValidTarget("card_equip_2_19");

        // Documents buggy behavior for BGA #250666, flip the assertion when the fix lands
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->expectExceptionMessageMatches("/ERR:roll:noCharOnHex:$goblinHex/");
        $this->respond("card_equip_2_19");
        $this->confirmCardEffect();
    }
}
