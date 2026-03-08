<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Operations\Op_actionPrepare;
use Bga\Games\Fate\Tests\GameUT;
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
        // drawEvent should be queued — dispatch it and check hand grew
        $handBefore = count($this->getHandCards());
        $this->game->machine->dispatchAll();
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
