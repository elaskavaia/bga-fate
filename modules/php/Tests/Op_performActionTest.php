<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Operations\Op_performAction;
use Bga\Games\Fate\Tests\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_performActionTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(string $expr = "performAction(actionAttack)"): Op_performAction {
        /** @var Op_performAction */
        $op = $this->game->machine->instanciateOperation($expr, PCOLOR);
        return $op;
    }

    private function getQueuedOp(): ?array {
        $ops = $this->game->machine->getTopOperations(PCOLOR);
        return $ops ? reset($ops) : null;
    }

    public function testQueuesActionAttack(): void {
        $op = $this->createOp("performAction(actionAttack)");
        $op->action_resolve([]);
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("actionAttack", $queued["type"]);
    }

    public function testQueuesActionMend(): void {
        $op = $this->createOp("performAction(actionMend)");
        $op->action_resolve([]);
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("actionMend", $queued["type"]);
    }

    public function testQueuesActionPractice(): void {
        $op = $this->createOp("performAction(actionPractice)");
        $op->action_resolve([]);
        $queued = $this->getQueuedOp();
        $this->assertNotNull($queued);
        $this->assertEquals("actionPractice", $queued["type"]);
    }
}
