<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Tests for Op_spendUse — cost op that marks the context card as used this turn.
 * Behaviour mirrors the existing Card::setUsed() path but can be composed into
 * `r` expressions like `spendMana:spendUse:2addDamage`.
 */
final class Op_spendUseTest extends AbstractOpTestCase {
    private string $cardId = "card_ability_1_3"; // Sure Shot I, on Bjorn's starting tableau

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    public function testFreshCardIsValidTarget(): void {
        $this->createOp(null, ["card" => $this->cardId]);
        $this->assertValidTarget($this->cardId);
    }

    public function testAlreadyUsedCardIsOccupied(): void {
        $this->game->tokens->dbSetTokenState($this->cardId, 1, "");
        $this->createOp(null, ["card" => $this->cardId]);
        $this->assertTargetError($this->cardId, Material::ERR_OCCUPIED);
    }

    public function testNoCardDataFieldIsEmpty(): void {
        // Missing `card` data field → no valid targets. Paygain chain voids like any
        // other cost op with nothing to target.
        $this->createOp();
        $this->assertNoValidTargets();
    }

    public function testResolveMarksCardAsUsed(): void {
        $this->createOp(null, ["card" => $this->cardId]);
        $this->assertEquals(0, (int) $this->game->tokens->getTokenInfo($this->cardId)["state"]);
        $this->call_resolve($this->cardId);
        $this->assertEquals(1, (int) $this->game->tokens->getTokenInfo($this->cardId)["state"]);
    }

    public function testSecondAttemptOnSameCardIsOccupied(): void {
        $this->createOp(null, ["card" => $this->cardId]);
        $this->call_resolve($this->cardId);
        // Second attempt: card already used → void-by-error
        $this->createOp(null, ["card" => $this->cardId]);
        $this->assertTargetError($this->cardId, Material::ERR_OCCUPIED);
    }

    // -------------------------------------------------------------------------
    // Composition: spendUse chains with other costs/effects via paygain
    // -------------------------------------------------------------------------

    public function testChainSpendUseCostDamageGainXp(): void {
        // Leather Purse (card_equip_1_19) has durability=3 so costDamage has room.
        // Chain: spend the card's use, then place 1 red on the card, then gain 1 gold.
        $cardId = "card_equip_1_19";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->owner);

        // Precondition checks
        $this->assertEquals(0, (int) $this->game->tokens->getTokenInfo($cardId)["state"]);
        $this->assertEquals(0, $this->countRedCrystals($cardId));
        $xpBefore = $this->countYellowCrystals("tableau_" . $this->owner);

        $this->game->machine->push("spendUse:costDamage:gainXp", $this->owner, ["card" => $cardId]);
        $this->game->machine->dispatchAll();

        $this->assertEquals(1, (int) $this->game->tokens->getTokenInfo($cardId)["state"], "spendUse flipped card state");
        $this->assertEquals(1, $this->countRedCrystals($cardId), "costDamage placed a red crystal");
        $this->assertEquals($xpBefore + 1, $this->countYellowCrystals("tableau_" . $this->owner), "gainXp added 1 gold");
    }

    public function testChainVoidsIfCardAlreadyUsed(): void {
        // Already-used card → chain voids at spendUse → no costDamage, no gainXp.
        $cardId = "card_equip_1_19";
        $this->game->tokens->moveToken($cardId, "tableau_" . $this->owner);
        $this->game->tokens->dbSetTokenState($cardId, 1, "");
        $damageBefore = $this->countRedCrystals($cardId);
        $xpBefore = $this->countYellowCrystals("tableau_" . $this->owner);

        $this->game->machine->push("spendUse:costDamage:gainXp", $this->owner, ["card" => $cardId]);
        $this->game->machine->dispatchAll();

        $this->assertEquals($damageBefore, $this->countRedCrystals($cardId), "no damage added when chain voids");
        $this->assertEquals($xpBefore, $this->countYellowCrystals("tableau_" . $this->owner), "no XP gained when chain voids");
    }
}
