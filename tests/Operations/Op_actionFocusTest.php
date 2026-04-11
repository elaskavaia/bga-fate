<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionFocusTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->clearMachine();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // setupGameTables seeds 1 mana on Sure Shot I — drain to 0 so tests can count.
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3")) as $key) {
            $this->game->tokens->moveToken($key, "supply_crystal_green");
        }
    }

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations($this->owner);
        return $ops ? reset($ops) : null;
    }

    public function testFocusQueuesGainMana(): void {
        $op = $this->createOp("actionFocus");
        $this->call_resolve();
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("gainMana", $queued["type"]);
    }

    public function testFocusGainManaAddsManaToCard(): void {
        $op = $this->createOp("actionFocus");
        $this->call_resolve();
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $gainManaOp = $this->createOp($queued["type"]);
        $gainManaOp->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $mana = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3");
        $this->assertCount(1, $mana);
    }
}
