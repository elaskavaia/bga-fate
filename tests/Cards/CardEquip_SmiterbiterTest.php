<?php

declare(strict_types=1);

use Bga\Games\Fate\Cards\CardEquip_Smiterbiter;
use Bga\Games\Fate\Model\Trigger;

/**
 * Unit tests for CardEquip_Smiterbiter::onMonsterKilled (auto-store).
 *
 * The store path bypasses useCard / r entirely — onMonsterKilled reads the
 * overkill from marker_attack state and pulls reds from supply onto the card,
 * capped at MAX_STORED=3. The spend path (TActionAttack -> r=c_smiter -> Op_c_smiter)
 * is covered by Op_c_smiterTest and the campaign suite.
 */
class CardEquip_SmiterbiterTest extends AbstractCardTestCase {
    private const CARD = "card_equip_4_21";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken(self::CARD, $this->getPlayersTableau());
    }

    private function createCard(): CardEquip_Smiterbiter {
        $parentOp = $this->game->machine->instantiateOperation("nop", $this->owner, ["card" => self::CARD]);
        return new CardEquip_Smiterbiter($this->game, self::CARD, $parentOp);
    }

    private function setOverkill(int $amount): void {
        // Op_dealDamage writes overkill (positive int) to marker_attack state when a monster dies.
        $this->game->tokens->moveToken("marker_attack", "limbo", $amount);
    }

    public function testStoresOverkillOnKill(): void {
        $this->setOverkill(2);
        $this->createCard()->onMonsterKilled(Trigger::MonsterKilled);
        $this->assertEquals(2, $this->countRedCrystals(self::CARD));
    }

    public function testStorageCappedAtThree(): void {
        $this->game->effect_moveCrystals("hero_1", "red", 2, self::CARD, ["message" => ""]);
        $this->setOverkill(5);
        $this->createCard()->onMonsterKilled(Trigger::MonsterKilled);
        $this->assertEquals(3, $this->countRedCrystals(self::CARD));
    }

    public function testNoOverkillStoresNothing(): void {
        $this->setOverkill(0);
        $this->createCard()->onMonsterKilled(Trigger::MonsterKilled);
        $this->assertEquals(0, $this->countRedCrystals(self::CARD));
    }

    public function testNegativeOverkillStoresNothing(): void {
        // marker_attack state is negative when monster survived (remaining HP).
        $this->setOverkill(-3);
        $this->createCard()->onMonsterKilled(Trigger::MonsterKilled);
        $this->assertEquals(0, $this->countRedCrystals(self::CARD));
    }

    public function testNoStorageWhenAlreadyFull(): void {
        $this->game->effect_moveCrystals("hero_1", "red", 3, self::CARD, ["message" => ""]);
        $this->setOverkill(2);
        $this->createCard()->onMonsterKilled(Trigger::MonsterKilled);
        $this->assertEquals(3, $this->countRedCrystals(self::CARD));
    }

    public function testPullsFromSupplyNotMarker(): void {
        $supplyBefore = $this->countRedCrystals("supply_crystal_red");
        $this->setOverkill(2);
        $this->createCard()->onMonsterKilled(Trigger::MonsterKilled);
        $this->assertEquals($supplyBefore - 2, $this->countRedCrystals("supply_crystal_red"));
    }
}
