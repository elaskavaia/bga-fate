<?php

declare(strict_types=1);

use Bga\Games\Fate\Cards\CardEquip_EitrisPick;
use Bga\Games\Fate\Model\Trigger;

/**
 * Unit tests for CardEquip_EitrisPick::onActionAttack.
 *
 * The hook reads the source card from the parent op's "card" data field —
 * propagated by Card::createOperationForCardEffect when Rapid Strike's
 * spendUse:3spendMana:actionAttack chain was instantiated. If the source
 * is one of the Rapid Strike cards, queue 2addRoll. Otherwise no-op.
 */
class CardEquip_EitrisPickTest extends AbstractCardTestCase {
    private const CARD = "card_equip_4_22";

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken(self::CARD, $this->getPlayersTableau());
    }

    private function fireOnActionAttack(string $sourceCard): void {
        $parentOp = $this->game->machine->instantiateOperation("actionAttack", $this->owner, [
            "card" => $sourceCard,
        ]);
        $card = new CardEquip_EitrisPick($this->game, self::CARD, $parentOp);
        $card->onActionAttack(Trigger::ActionAttack);
    }

    private function queuedOpTypes(): array {
        return array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations($this->owner));
    }

    public function testQueuesAddRollWhenAttackFromRapidStrikeI(): void {
        $this->fireOnActionAttack("card_ability_4_3");
        $this->assertContains("2addRoll", $this->queuedOpTypes());
    }

    public function testQueuesAddRollWhenAttackFromRapidStrikeII(): void {
        $this->fireOnActionAttack("card_ability_4_4");
        $this->assertContains("2addRoll", $this->queuedOpTypes());
    }

    public function testNoOpWhenAttackFromOtherCard(): void {
        $this->fireOnActionAttack("card_equip_4_20"); // Dvalin's Pick
        $this->assertNotContains("2addRoll", $this->queuedOpTypes());
    }

    public function testNoOpWhenPlainActionAttack(): void {
        $this->fireOnActionAttack(""); // direct turn action, no card source
        $this->assertNotContains("2addRoll", $this->queuedOpTypes());
    }
}
