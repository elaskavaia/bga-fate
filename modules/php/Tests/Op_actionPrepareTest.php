<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Operations\Op_actionPrepare;
use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionPrepareTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->setupGameTables();
    }

    private function createOp(): Op_actionPrepare {
        /** @var Op_actionPrepare */
        $op = $this->game->machine->instanciateOperation("actionPrepare", PCOLOR);
        return $op;
    }

    private function getHandCards(): array {
        return $this->game->tokens->getTokensOfTypeInLocation("card", "hand_" . PCOLOR);
    }

    public function testResolveQueuesDrawEvent(): void {
        $op = $this->createOp();
        $op->resolve();
        $handBefore = count($this->getHandCards());
        // drawEvent now requires player confirmation — resolve it
        $drawOp = $this->game->machine->createTopOperationFromDbForOwner(null);
        $this->assertEquals("drawEvent", $drawOp->getType());
        $drawOp->action_resolve([Operation::ARG_TARGET => "confirm"]);
        $this->assertCount($handBefore + 1, $this->getHandCards());
    }

    public function testResolveWithEmptyDeckNoError(): void {
        // Empty the deck
        $deck = $this->game->tokens->getTokensOfTypeInLocation("card", "deck_event_" . PCOLOR);
        foreach ($deck as $cardId => $info) {
            $this->game->tokens->moveToken($cardId, "limbo");
        }
        $handBefore = count($this->getHandCards());
        $op = $this->createOp();
        $op->resolve();
        $this->game->machine->dispatchAll();
        $this->assertCount($handBefore, $this->getHandCards());
    }
}
