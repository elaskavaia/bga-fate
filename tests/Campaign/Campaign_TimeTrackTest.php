<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration test: long-track game variant must drive Op_turnMonster::advanceTimeTrack
 * to walk the rune_stone within `timetrack_2` (length 16), not snap back to `timetrack_1`.
 *
 * Guards against a regression where the track id is read from setup once but later
 * monster turns hardcode `timetrack_1`, causing the rune stone to teleport between tracks.
 */
class Campaign_TimeTrackTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        // Toggle long-track BEFORE setupGameTables runs (it reads the option to place rune_stone).
        $this->game->setGameStateValue("var_long_track", 1);
        $this->setupGame([1]); // Solo Bjorn
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        // Seed monster + event decks so monster-turn / drawEvent steps don't blow up.
        $this->seedDeck("deck_monster_yellow", [
            "card_monster_7",
            "card_monster_8",
            "card_monster_9",
            "card_monster_10",
        ]);
        $this->seedDeck("deck_event_" . $this->getActivePlayerColor(), [
            "card_event_1_27_1", // Rest
            "card_event_1_27_2", // Rest
        ]);
        $this->clearHand($this->getActivePlayerColor());
        $this->clearMonstersFromMap();
    }

    public function testLongTrackSetupPlacesRuneStoneOnTrack2(): void {
        $this->assertTrue($this->game->isLongTimeTrack());
        $this->assertEquals("timetrack_2", $this->game->getTimeTrackId());
        $this->assertEquals(16, $this->game->getTimeTrackLength());
        $this->assertEquals("timetrack_2", $this->tokenLocation("rune_stone"));
        $this->assertEquals(1, $this->game->tokens->getTokenState("rune_stone"));
    }

    public function testMonsterTurnAdvancesRuneStoneOnLongTrack(): void {
        $this->driveOneMonsterTurn();

        $this->assertEquals(
            "timetrack_2",
            $this->tokenLocation("rune_stone"),
            "rune_stone must stay on the long track after monster turn"
        );
        $this->assertEquals(2, $this->game->tokens->getTokenState("rune_stone"), "rune_stone should advance from step 1 → 2");
    }

    public function testMultipleMonsterTurnsWalkLongTrack(): void {
        // Drive 3 full monster turns; rune_stone should walk from step 1 → step 4 within timetrack_2.
        $this->driveOneMonsterTurn();
        $this->driveOneMonsterTurn();
        $this->driveOneMonsterTurn();

        $this->assertEquals(
            "timetrack_2",
            $this->tokenLocation("rune_stone"),
            "rune_stone must remain on the long track across multiple monster turns"
        );
        $this->assertEquals(4, $this->game->tokens->getTokenState("rune_stone"), "rune_stone should advance 3 steps to step 4");
    }

    /**
     * Burn the player's two actions, skip turn, skip any optional end-of-turn ops
     * (upgrade, drawEvent) so the monster turn actually fires.
     */
    private function driveOneMonsterTurn(): void {
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        // Optional end-of-turn ops vary by game state (upgrade prompts only when XP affords it).
        $this->skipIfOp("upgrade");
        $this->skipIfOp("drawEvent");
    }
}
