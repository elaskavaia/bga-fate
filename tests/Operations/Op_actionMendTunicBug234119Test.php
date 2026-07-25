<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;

/**
 * BGA #234119: Home Sewn Tunic (card_equip_1_23).
 *
 * Card text: "Spend 1 mend action to remove all damage from this card."
 * A mend pick on the Tunic strips all of its damage at once, anywhere on the map;
 * inside Grimheim the rest of the 5 damage budget stays spendable on other targets.
 */
final class Op_actionMendTunicBug234119Test extends AbstractOpTestCase {
    private const TUNIC = "card_equip_1_23";

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->game->clearHand();
        $this->game->clearMachine();
        $this->game->clearEquipDecks();
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . $this->owner);
        $this->game->tokens->moveToken("hero_1", "hex_9_9"); // inside Grimheim
        $this->game->tokens->moveToken(self::TUNIC, "tableau_" . $this->owner);
        $this->game->hexMap->invalidateOccupancy();
    }

    private function addDamage(string $tokenId, int $amount): void {
        $this->game->effect_moveCrystals($tokenId, "red", $amount, $tokenId, ["message" => ""]);
    }

    public function testMendOnTunicRemovesAllDamageInGrimheim(): void {
        $this->addDamage(self::TUNIC, 2);
        $this->addDamage("hero_1", 1); // second damaged target so mend is not single-choice

        $this->createOp("actionMend");
        $this->call_resolve(self::TUNIC); // spend the Mend action targeting the Tunic

        $top = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertNotNull($top, "Mend should queue a removeDamage op");
        $this->game->fakeUserAction($top, self::TUNIC); // pick the Tunic to repair

        $this->assertSame(0, $this->countRedCrystals(self::TUNIC));
    }

    public function testMendOnTunicKeepsRestOfGrimheimBudget(): void {
        $this->addDamage(self::TUNIC, 2);
        $this->addDamage("hero_1", 3);

        $this->createOp("actionMend");
        $this->call_resolve(self::TUNIC);
        $top = $this->game->machine->createTopOperationFromDbForOwner();
        $this->game->fakeUserAction($top, self::TUNIC);

        $remaining = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertNotNull($remaining, "3 of the 5 damage budget is left for other targets");
        $this->game->fakeUserAction($remaining, $this->game->tokens->getTokenLocation("hero_1"));
        $this->assertSame(0, $this->countRedCrystals("hero_1"));
    }

    public function testMendOnTunicOutsideGrimheim(): void {
        $this->game->tokens->moveToken("hero_1", "hex_5_5");
        $this->game->hexMap->invalidateOccupancy();
        $this->addDamage(self::TUNIC, 3);
        $this->addDamage("hero_1", 1);

        $op = $this->createOp("actionMend");
        $this->assertArrayHasKey(self::TUNIC, $op->getArgsInfo(), "Tunic is repairable outside Grimheim");

        $this->call_resolve(self::TUNIC);
        $top = $this->game->machine->createTopOperationFromDbForOwner();
        $this->assertNotNull($top, "Mend should queue a removeDamage op");
        $this->game->fakeUserAction($top, self::TUNIC);

        $this->assertSame(0, $this->countRedCrystals(self::TUNIC));
        $this->assertSame(1, $this->countRedCrystals("hero_1"), "Tunic repair replaces the normal 2 heal");
    }

    public function testUndamagedTunicIsNotAMendTargetOutsideGrimheim(): void {
        $this->game->tokens->moveToken("hero_1", "hex_5_5");
        $this->game->hexMap->invalidateOccupancy();
        $this->addDamage("hero_1", 2);

        $op = $this->createOp("actionMend");
        $this->assertArrayNotHasKey(self::TUNIC, $op->getArgsInfo());
    }
}
