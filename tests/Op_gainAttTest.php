<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_gainAttTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
    }

    public function testGainStrength(): void {
        $before = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $op = $this->game->machine->instanciateOperation("gainAtt(strength)", PCOLOR);
        $op->resolve();
        $after = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $this->assertEquals($before + 1, $after);
    }

    public function testGainStrengthWithCount(): void {
        $before = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $op = $this->game->machine->instanciateOperation("3gainAtt(strength)", PCOLOR);
        $op->resolve();
        $after = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $this->assertEquals($before + 3, $after);
    }

    public function testDefaultAttributeIsStrength(): void {
        $before = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $op = $this->game->machine->instanciateOperation("gainAtt", PCOLOR);
        $op->resolve();
        $after = $this->game->tokens->getTrackerValue(PCOLOR, "strength");
        $this->assertEquals($before + 1, $after);
    }
}
