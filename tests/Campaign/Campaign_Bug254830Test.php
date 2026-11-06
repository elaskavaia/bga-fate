<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #254830: Seek Shelter played with no location reachable within 2 areas.
 * FORUM.md (designer): "Seek Shelter must be into a location ... you must end in the location,
 * otherwise you cannot seek shelter out in the open".
 */
class Campaign_Bug254830Test extends CampaignBaseTest {
    private string $color;
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]);
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
    }

    private function findHexWithNoLocationWithin2(): string {
        $hexMap = $this->game->hexMap;
        $hero = $this->game->getHero($this->color);
        foreach ($hexMap->getHexesInRange("hex_9_9", 20) as $hexId) {
            if ($hexMap->getHexNamedLocation($hexId) !== "") {
                continue;
            }
            $this->game->tokens->moveToken($this->heroId, $hexId);
            $hexMap->invalidateOccupancy();
            $reachable = $hexMap->getReachableHexes($hexId, 2, $hero);
            if (count($reachable) === 0) {
                continue;
            }
            $locations = array_filter(array_keys($reachable), fn($h) => $hexMap->getHexNamedLocation($h) !== "");
            if (count($locations) === 0) {
                return $hexId;
            }
        }
        $this->fail("no hex without a location within 2");
    }

    public function testSeekShelterWithNoLocationInReachIsPlayedForNothing(): void {
        $seekShelter = "card_event_4_37_1";
        $this->seedHand($seekShelter, $this->color);
        $hex = $this->findHexWithNoLocationWithin2();

        // pins buggy behavior for BGA #254830: card is offered and consumed with no effect; flip when fixed
        $this->assertValidTarget($seekShelter);
        $this->respond($seekShelter);
        $this->confirmCardEffect();

        $this->assertEquals($hex, $this->tokenLocation($this->heroId));
        $this->assertNotEquals("hand_$this->color", $this->tokenLocation($seekShelter));
    }
}
