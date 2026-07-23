<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Regression for BGA #233685 softlock.
 *
 * Speedy Attack (card_event_2_33, r=discardEvent:actionAttack, "Discard ANOTHER
 * card from hand to perform an attack action") played as the ONLY card in hand.
 *
 * Card::useCard discards the event card itself before its effect runs, so the
 * discardEvent cost is left with an empty hand and no card to pay. But the
 * pre-play affordability probe (CardGeneric::canBePlayed -> noValidTargets)
 * counts the event card itself as a discardable candidate, so the card is
 * offered anyway -> once played, the machine is stuck in a live discardEvent
 * state with zero valid moves and Undo cannot roll back the committed play.
 *
 * Fix: Op_discardEvent::getPossibleMoves must exclude the card being played
 * (getDataField("card")) from both the candidate list and the count check,
 * matching the "another card" wording.
 */
class Campaign_SpeedyAttackSoftlockTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([2]); // Solo Alva
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    public function testSpeedyAttackNotOfferedAsOnlyCardInHand(): void {
        $color = $this->getActivePlayerColor();
        $speedy = "card_event_2_33_1";
        $this->seedHand($speedy, $color); // ONLY card in hand

        // Adjacent goblin so actionAttack is not the reason the card would be void.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $goblin = "monster_goblin_20";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");
        $this->seedRand([5, 5, 5]);

        // With no OTHER card to discard, the discard cost is unpayable, so the
        // card must NOT be offered. (Currently it IS offered -> playing it
        // softlocks in discardEvent with no valid target.)
        $this->assertNotValidTarget(
            $speedy,
            "Speedy Attack must be hidden when it is the only card in hand (no other card to discard)"
        );
    }
}
