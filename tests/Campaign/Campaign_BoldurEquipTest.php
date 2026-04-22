<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Boldur's equipment cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_BoldurEquipTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    // --- Dvalin's Pick (card_equip_4_20) ---
    // r=spendAction(actionAttack):gainXp,gainMana,drawEvent — spend attack action for 1 XP, 1 mana, 1 card.

    public function testDvalinsPickSpendsAttackActionForXpManaAndCardDraw(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_equip_4_20";
        $this->game->tokens->moveToken($cardId, "tableau_$color");

        // Boldur's Rapid Strike I (mana=1) is the sole mana-holding card → gainMana auto-resolves onto it.
        $manaCard = "card_ability_4_3";
        $manaBefore = $this->countTokens("crystal_green", $manaCard);

        // Seed one event card so drawEvent has something to pull.
        $drawnCard = "card_event_4_35_1"; // Dodge
        $this->seedDeck("deck_event_$color", [$drawnCard]);

        $xpBefore = $this->countXp();

        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        $this->confirmCardEffect(); // spendAction(actionAttack) confirm
        $this->respond("confirm");  // drawEvent confirm

        // Attack action slot consumed — can't re-take it this turn.
        $hero = $this->game->getHero($color);
        $this->assertContains("actionAttack", $hero->getActionsTaken());
        // Resources gained.
        $this->assertEquals($xpBefore + 1, $this->countXp());
        $this->assertEquals($manaBefore + 1, $this->countTokens("crystal_green", $manaCard));
        // Event drawn into hand.
        $this->assertEquals("hand_$color", $this->tokenLocation($drawnCard));
    }
}
