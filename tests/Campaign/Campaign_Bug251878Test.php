<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #251878: after Seek Shelter ("You may not move more this turn") Boldur could still play Maneuver and move.
 * Rules: misc/docs/FORUM.md "## Seek Shelter" and "## Boldur - Seek Shelter + Maneuver".
 */
class Campaign_Bug251878Test extends CampaignBaseTest {
    public function testManeuverStillMovesAfterSeekShelter(): void {
        $this->setupGame([4]);
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $this->clearHand($color);
        $heroId = $this->game->getHeroTokenId($color);
        $seekShelter = "card_event_4_37_1";
        $maneuver = "card_event_4_29_1";
        $this->seedHand($seekShelter, $color);
        $this->seedHand($maneuver, $color);
        $this->game->tokens->dbSetTokenLocation($heroId, "hex_11_8");

        $this->respond($seekShelter);
        $this->confirmCardEffect();
        $targets = $this->getOpArgs()["target"] ?? [];
        $grimheim = array_values(array_filter($targets, fn($hex) => $this->game->hexMap->isInGrimheim($hex)));
        $this->assertNotEmpty($grimheim, "Seek Shelter should offer Grimheim");
        $this->respond($grimheim[0]);

        $shelterHex = $this->tokenLocation($heroId);
        $this->assertEquals(0, $this->game->getHero($color)->getNumberOfMoves());
        $this->assertNotValidTarget("actionMove");

        // Pins buggy behavior for BGA #251878 - flip the assertion when the fix lands
        $this->assertValidTarget($maneuver);
        $this->respond($maneuver);
        $moveTargets = $this->getOpArgs()["target"] ?? [];
        $this->assertNotEmpty($moveTargets);
        $this->respond($moveTargets[0]);
        $this->assertNotEquals($shelterHex, $this->tokenLocation($heroId));
    }
}
