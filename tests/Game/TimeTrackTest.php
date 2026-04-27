<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

/**
 * Verify the long-track game option drives both the rune_stone location and
 * the track-length helper.
 */
final class TimeTrackTest extends TestCase {
    public function testShortTrackDefault(): void {
        $game = new GameUT();
        $game->initWithHero(1);

        $this->assertFalse($game->isLongTimeTrack());
        $this->assertEquals("timetrack_1", $game->getTimeTrackId());
        $this->assertEquals(12, $game->getTimeTrackLength());
        $this->assertEquals("timetrack_1", $game->tokens->getTokenLocation("rune_stone"));
    }

    public function testLongTrackOption(): void {
        $game = new GameUT();
        $game->setGameStateValue("var_long_track", 1);
        $game->initWithHero(1);

        $this->assertTrue($game->isLongTimeTrack());
        $this->assertEquals("timetrack_2", $game->getTimeTrackId());
        $this->assertEquals(16, $game->getTimeTrackLength());
        $this->assertEquals("timetrack_2", $game->tokens->getTokenLocation("rune_stone"));
    }
}
