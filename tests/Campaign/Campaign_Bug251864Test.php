<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * BGA #251864: Inspire Defense adds a Town Piece on the server but the Grimheim counter
 * (counter_house_0) is only re-notified by effect_destroyHouses, never by addTownPiece.
 */
class Campaign_Bug251864Test extends CampaignBaseTest {
    public function testInspireDefenseDoesNotNotifyHouseCounter(): void {
        $this->setupGame([2]);
        $this->clearMonstersFromMap();
        $color = $this->getActivePlayerColor();
        $this->clearHand($color);
        $heroId = $this->game->getHeroTokenId($color);
        $inspire = "card_event_2_30_1";
        $this->seedHand($inspire, $color);
        $cardB = "card_ability_2_13";
        $this->game->tokens->moveToken($cardB, "tableau_$color");
        $this->game->effect_moveCrystals($heroId, "green", 1, $cardB, ["message" => ""]);
        $this->assertEquals(4, $this->game->getHousesRemainingCount());

        $notifsBefore = count($this->game->notify->_getNotifications());
        $this->respond($inspire);
        $this->confirmCardEffect();
        $this->respond("card_ability_2_3");

        // Solo starts with all 4 pieces placed; the 5th comes from the unused physical pieces in limbo.
        $this->assertEquals(5, $this->game->getHousesRemainingCount());
        $notifs = array_slice($this->game->notify->_getNotifications(), $notifsBefore);
        $this->assertNotEmpty(array_filter($notifs, fn($n) => ($n["args"]["token_id"] ?? "") === "house_4"));
        $houseCounter = array_filter($notifs, fn($n) => $n["type"] === "counter" && ($n["args"]["name"] ?? "") === "counter_house_0");
        // Pins buggy behavior for BGA #251864 - flip the assertion when the fix lands
        $this->assertEmpty($houseCounter);
    }
}
