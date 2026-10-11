<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_reinforcement;

final class Op_reinforcementTwoQueensTest extends AbstractOpTestCase {
    function getOperationType(): string {
        return "reinforcement";
    }

    protected function setUp(): void {
        parent::setUp();
        $this->game->machine->push("turnStart");
        $this->game->machine->dispatchAll();
    }

    public function testRedQueenCardPlacesSecondQueenWhileYellowQueenInPlay(): void {
        $this->game->tokens->moveToken("monster_legend_1_1", "hex_13_5");
        $this->game->setPlayersNumber(1);
        /** @var Op_reinforcement */
        $op = $this->createOp("reinforcement", ["deck" => "deck_monster_red", "card" => "card_monster_37"]);
        $op->resolve();

        // Documents buggy behavior for BGA #245127; flip the assertion when fixed
        $this->assertStringStartsWith("hex_", $this->game->tokens->getTokenLocation("monster_legend_1_1"));
        $this->assertStringStartsWith("hex_", $this->game->tokens->getTokenLocation("monster_legend_1_2"));
    }
}
