<?php

declare(strict_types=1);

use Bga\Games\Fate\Cards\CardEquip_Smiterbiter;

/**
 * Unit tests for CardEquip_Smiterbiter's excess pool.
 *
 * A kill's overkill is held on the card as YELLOW while the attack action runs, a
 * sweep takes back whatever it actually deals, and end of attack commits what is
 * left to RED capped at 3. These cover the arithmetic in isolation; the pipeline
 * wiring is covered by the campaign suite, and the spend path (TActionAttack ->
 * r=c_smiter -> Op_c_smiter) by Op_c_smiterTest.
 */
class CardEquip_SmiterbiterTest extends AbstractCardTestCase {
    private const CARD = CardEquip_Smiterbiter::CARD_ID;

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken(self::CARD, $this->getPlayersTableau());
    }

    private function createCard(): CardEquip_Smiterbiter {
        $parentOp = $this->game->machine->instantiateOperation("nop", $this->owner, ["card" => self::CARD]);
        return new CardEquip_Smiterbiter($this->game, self::CARD, $parentOp);
    }

    private function bank(int $count): void {
        $this->game->effect_moveCrystals("hero_1", "red", $count, self::CARD, ["message" => ""]);
    }

    public function testPendingIsHeldAsYellowNotRed(): void {
        $this->createCard()->addPendingExcess(2);
        $this->assertEquals(2, $this->countYellowCrystals(self::CARD), "pending excess is yellow");
        $this->assertEquals(0, $this->countRedCrystals(self::CARD), "nothing is banked until the attack ends");
    }

    public function testCommitTurnsPendingIntoStoredDamage(): void {
        $card = $this->createCard();
        $card->addPendingExcess(2);
        $card->commitPendingExcess();
        $this->assertEquals(2, $this->countRedCrystals(self::CARD));
        $this->assertEquals(0, $this->countYellowCrystals(self::CARD), "no pending left after commit");
    }

    public function testCommitCapsAtThree(): void {
        $this->bank(2);
        $card = $this->createCard();
        $card->addPendingExcess(5);
        $card->commitPendingExcess();
        $this->assertEquals(3, $this->countRedCrystals(self::CARD));
        $this->assertEquals(0, $this->countYellowCrystals(self::CARD), "the uncapped remainder goes back to supply");
    }

    public function testSweepWithdrawalLeavesNothingToBank(): void {
        $card = $this->createCard();
        $card->addPendingExcess(3);
        $card->removePendingExcess(3); // the sweep dealt all of it
        $card->commitPendingExcess();
        $this->assertEquals(0, $this->countRedCrystals(self::CARD), "damage that was dealt is not excess");
    }

    /**
     * The whole reason pending is a separate colour: a withdrawal must never reach
     * damage banked before this attack.
     */
    public function testWithdrawalCannotEatPreviouslyBankedDamage(): void {
        $this->bank(2);
        $card = $this->createCard();
        $card->addPendingExcess(3); // kill leaves 3
        $card->removePendingExcess(3); // sweep spends all 3
        $card->addPendingExcess(1); // sweep kill leaves 1
        $card->commitPendingExcess();
        $this->assertEquals(3, $this->countRedCrystals(self::CARD), "2 banked + 1 excess");
    }

    /**
     * Two independent kills in one attack action each add to the pool, rather than the second
     * overwriting the first the way a single marker_attack register would.
     */
    public function testSeveralKillsInOneAttackAllBank(): void {
        $card = $this->createCard();
        $card->addPendingExcess(1);
        $card->addPendingExcess(2);
        $card->commitPendingExcess();
        $this->assertEquals(3, $this->countRedCrystals(self::CARD));
    }

    public function testNonPositiveOverkillIsNotPending(): void {
        $card = $this->createCard();
        $card->addPendingExcess(0);
        $card->addPendingExcess(-3); // marker_attack is negative when the target survived
        $this->assertEquals(0, $this->countYellowCrystals(self::CARD));
    }

    public function testCommitWithNothingPendingStoresNothing(): void {
        $this->createCard()->commitPendingExcess();
        $this->assertEquals(0, $this->countRedCrystals(self::CARD));
    }

    public function testAlreadyFullCardBanksNothingMore(): void {
        $this->bank(3);
        $card = $this->createCard();
        $card->addPendingExcess(2);
        $card->commitPendingExcess();
        $this->assertEquals(3, $this->countRedCrystals(self::CARD));
        $this->assertEquals(0, $this->countYellowCrystals(self::CARD));
    }

    public function testCommitPullsStoredDamageFromSupply(): void {
        $supplyBefore = $this->countRedCrystals("supply_crystal_red");
        $card = $this->createCard();
        $card->addPendingExcess(2);
        $card->commitPendingExcess();
        $this->assertEquals($supplyBefore - 2, $this->countRedCrystals("supply_crystal_red"));
    }

    public function testCardOutOfPlayIsNeverTouched(): void {
        $this->game->tokens->moveToken(self::CARD, "limbo");
        $card = $this->createCard();
        $card->addPendingExcess(3);
        $card->commitPendingExcess();
        $this->assertEquals(0, $this->countYellowCrystals(self::CARD));
        $this->assertEquals(0, $this->countRedCrystals(self::CARD));
    }
}
