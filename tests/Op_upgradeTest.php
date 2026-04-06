<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_upgrade;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_upgradeTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
    }

    private function createOp(): Op_upgrade {
        /** @var Op_upgrade */
        $op = $this->game->machine->instanciateOperation("upgrade", PCOLOR);
        return $op;
    }

    private function giveXp(int $amount): void {
        $this->game->effect_moveCrystals("hero_1", "yellow", $amount, "tableau_" . PCOLOR, ["message" => ""]);
    }

    private function getXp(): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
    }

    private function getUpgradeCost(): int {
        return (int) $this->game->tokens->getTokenState("marker_" . PCOLOR . "_3");
    }

    // -------------------------------------------------------------------------
    // upgrade cost track
    // -------------------------------------------------------------------------

    public function testInitialCostIs5(): void {
        $this->assertEquals(5, $this->getUpgradeCost());
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — not enough XP
    // -------------------------------------------------------------------------

    public function testNotEnoughXpReturnsError(): void {
        // initWithHero gives 2 starting XP + 2 = 4, which is < 5
        $this->giveXp(2);
        $op = $this->createOp();
        $this->assertNotEquals(0, $op->getErrorCode());
    }

    public function testNoXpReturnsError(): void {
        $op = $this->createOp();
        $this->assertNotEquals(0, $op->getErrorCode());
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves — enough XP
    // -------------------------------------------------------------------------

    public function testEnoughXpShowsDeckCard(): void {
        $this->giveXp(5);
        $op = $this->createOp();
        $info = $op->getArgsInfo();
        // Should have at least the top deck card
        $deckCards = $this->game->tokens->getTokensOfTypeInLocation("card_ability", "deck_ability_" . PCOLOR);
        $this->assertNotEmpty($deckCards);
        $topCard = $this->game->tokens->getTokenOnTop("deck_ability_" . PCOLOR);
        $this->assertArrayHasKey($topCard["key"], $info);
    }

    public function testEnoughXpShowsFlippableCards(): void {
        $this->giveXp(5);
        $op = $this->createOp();
        $info = $op->getArgsInfo();
        // Hero card (card_hero_1_1) and starting ability (card_ability_1_3) should be flippable
        $this->assertArrayHasKey("card_hero_1_1", $info);
        $this->assertArrayHasKey("card_ability_1_3", $info);
    }

    public function testEmptyDeckOnlyShowsFlippable(): void {
        $this->giveXp(5);
        // Empty the ability deck
        $deckCards = $this->game->tokens->getTokensOfTypeInLocation("card_ability", "deck_ability_" . PCOLOR);
        foreach ($deckCards as $id => $card) {
            $this->game->tokens->moveToken($id, "limbo");
        }
        $op = $this->createOp();
        $info = $op->getArgsInfo();
        // Should still have flippable cards but no deck card
        $this->assertArrayHasKey("card_hero_1_1", $info);
        $this->assertArrayHasKey("card_ability_1_3", $info);
    }

    // -------------------------------------------------------------------------
    // resolve — gain new ability
    // -------------------------------------------------------------------------

    public function testGainMovesCardToTableau(): void {
        $this->giveXp(5);
        $topCard = $this->game->tokens->getTokenOnTop("deck_ability_" . PCOLOR);
        $cardId = $topCard["key"];
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => $cardId]);
        $this->assertEquals("tableau_" . PCOLOR, $this->game->tokens->getTokenLocation($cardId));
    }

    public function testGainDeductsXp(): void {
        $this->giveXp(7); // total 9 (2 starting + 7)
        $topCard = $this->game->tokens->getTokenOnTop("deck_ability_" . PCOLOR);
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => $topCard["key"]]);
        $this->assertEquals(4, $this->getXp()); // 9 - 5 = 4
    }

    public function testGainAdvancesCostMarker(): void {
        $this->giveXp(5);
        $topCard = $this->game->tokens->getTokenOnTop("deck_ability_" . PCOLOR);
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => $topCard["key"]]);
        $this->assertEquals(6, $this->getUpgradeCost());
    }

    public function testGainNoManaIfCardHasNone(): void {
        $this->giveXp(5);
        $topCard = $this->game->tokens->getTokenOnTop("deck_ability_" . PCOLOR);
        $cardId = $topCard["key"];
        // Bjorn's deck abilities have no mana generation
        $this->assertEquals(0, (int) $this->game->material->getRulesFor($cardId, "mana", 0));
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => $cardId]);
        $manaOnCard = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", $cardId));
        $this->assertEquals(0, $manaOnCard);
    }

    public function testGainManaGeneratedImmediately(): void {
        $this->giveXp(5);
        // Sure Shot I (card_ability_1_3) has mana=1 — move it to deck to test mana generation on gain
        $this->game->tokens->moveToken("card_ability_1_3", "deck_ability_" . PCOLOR, 999);
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $manaOnCard = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3"));
        // Had 1 mana from setup + 1 generated = 2
        $this->assertEquals(2, $manaOnCard);
    }

    // -------------------------------------------------------------------------
    // resolve — improve card
    // -------------------------------------------------------------------------

    public function testImproveFlipsCardToLevel2(): void {
        $this->giveXp(5);
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        // L1 should be in limbo, L2 on tableau
        $this->assertEquals("limbo", $this->game->tokens->getTokenLocation("card_ability_1_3"));
        $this->assertEquals("tableau_" . PCOLOR, $this->game->tokens->getTokenLocation("card_ability_1_4"));
    }

    public function testImproveHeroCard(): void {
        $this->giveXp(5);
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "card_hero_1_1"]);
        $this->assertEquals("limbo", $this->game->tokens->getTokenLocation("card_hero_1_1"));
        $this->assertEquals("tableau_" . PCOLOR, $this->game->tokens->getTokenLocation("card_hero_1_2"));
    }

    public function testImproveTransfersCrystals(): void {
        $this->giveXp(5);
        // Add mana and damage to the starting ability card
        $this->game->effect_moveCrystals("hero_1", "green", 2, "card_ability_1_3", ["message" => ""]);
        $this->game->effect_moveCrystals("hero_1", "red", 1, "card_ability_1_3", ["message" => ""]);

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);

        // Crystals should now be on L2 card (2 added + 1 from setup = 3 green)
        $greenOnL2 = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_4"));
        $redOnL2 = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "card_ability_1_4"));
        $this->assertEquals(3, $greenOnL2);
        $this->assertEquals(1, $redOnL2);
        // Nothing left on L1
        $greenOnL1 = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3"));
        $redOnL1 = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "card_ability_1_3"));
        $this->assertEquals(0, $greenOnL1);
        $this->assertEquals(0, $redOnL1);
    }

    public function testImproveDeductsXpAndAdvancesMarker(): void {
        $this->giveXp(5); // total 7 (2 starting + 5)
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $this->assertEquals(2, $this->getXp()); // 7 - 5 = 2
        $this->assertEquals(6, $this->getUpgradeCost());
    }

    // -------------------------------------------------------------------------
    // cost progression
    // -------------------------------------------------------------------------

    public function testCostCapsAt10(): void {
        // Set marker to state 9 (cost=9), upgrade should advance to 10
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_3", "tableau_" . PCOLOR, 9);
        $this->giveXp(9);
        $op = $this->createOp();
        $topCard = $this->game->tokens->getTokenOnTop("deck_ability_" . PCOLOR);
        $op->action_resolve([Operation::ARG_TARGET => $topCard["key"]]);
        $this->assertEquals(10, $this->getUpgradeCost());
    }

    public function testCostStaysAt10(): void {
        // Set marker to state 10, upgrade should stay at 10
        $this->game->tokens->moveToken("marker_" . PCOLOR . "_3", "tableau_" . PCOLOR, 10);
        $this->giveXp(10);
        $op = $this->createOp();
        $topCard = $this->game->tokens->getTokenOnTop("deck_ability_" . PCOLOR);
        $op->action_resolve([Operation::ARG_TARGET => $topCard["key"]]);
        $this->assertEquals(10, $this->getUpgradeCost());
    }

    // -------------------------------------------------------------------------
    // skip
    // -------------------------------------------------------------------------

    public function testCanSkip(): void {
        $op = $this->createOp();
        $this->assertTrue($op->canSkip());
    }
}
