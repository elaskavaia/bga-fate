<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_gainAttTest extends AbstractOpTestCase {
    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->owner = $this->game->getPlayerColorById((int) $this->game->getActivePlayerId());
    }

    public function testGainStrength(): void {
        $before = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $op = $this->createOp("gainAtt(strength)");
        $op->resolve();
        $after = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $this->assertEquals($before + 1, $after);
    }

    public function testGainStrengthWithCount(): void {
        $before = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $op = $this->createOp("3gainAtt(strength)");
        $op->resolve();
        $after = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $this->assertEquals($before + 3, $after);
    }

    public function testDefaultAttributeIsStrength(): void {
        $before = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $op = $this->createOp("gainAtt");
        $op->resolve();
        $after = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $this->assertEquals($before + 1, $after);
    }
}
