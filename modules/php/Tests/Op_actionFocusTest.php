<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionFocusTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR with Sure Shot I (mana=1) and First Bow (no mana)
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_ability_1_3", "tableau_" . PCOLOR); // Sure Shot I, mana=1
        $this->game->tokens->moveToken("card_equip_1_15", "tableau_" . PCOLOR); // Bjorn's First Bow, no mana
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations(PCOLOR);
        return $ops ? reset($ops) : null;
    }

    public function testFocusQueuesGainMana(): void {
        $op = $this->game->machine->instanciateOperation("actionFocus", PCOLOR);
        $op->action_resolve([]);
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("gainMana", $queued["type"]);
    }

    public function testFocusGainManaAddsManaToCard(): void {
        $op = $this->game->machine->instanciateOperation("actionFocus", PCOLOR);
        $op->action_resolve([]);
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $gainManaOp = $this->game->machine->instanciateOperation($queued["type"], PCOLOR);
        $gainManaOp->action_resolve([Operation::ARG_TARGET => "card_ability_1_3"]);
        $mana = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", "card_ability_1_3");
        $this->assertCount(1, $mana);
    }
}
