<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * The remaining "Nothing to undo" reports, both from 3 player tables:
 *
 * - BGA #235518 (table 891220373, move 16): "I was trying to undo the focus action i just took,
 *   but it didn't let me" / "An error message saying there's nothing to undo". The turn-start
 *   barrier is anchored in OpMachine::dispatchOne's seat switch, which does not fire for the very
 *   first player of the very first turn (they are already active), so the first turn relies on the
 *   explicit savepoint in Game::setupGameTables.
 *
 * - BGA #235653 (table 890906580, move 171): "I probably misclicked grimheim again ... The
 *   character did no movement but stayed in grimheim and I lost the movement action. I wanted to
 *   undo but it shows error: nothing to undo".
 *
 * RULES.md: "Grimheim is considered to be one big area where any number of heroes may stand at the
 * same time." A hero standing in Grimheim is therefore offered no destination inside it - only the
 * areas it can move out to.
 */
class Campaign_UndoBug235518Test extends CampaignBaseTest {
    private string $color;
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1, 2, 3]); // three seats, as in both reports
        $this->syncCurrentPlayerToActive();
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearMonstersFromMap();
    }

    /**
     * BGA #235518: the very first player takes Focus as the very first action of the game and
     * undoes it. No seat switch has happened yet, so the only barrier is the one
     * Game::setupGameTables writes explicitly.
     */
    public function testFocusAsTheFirstActionOfTheGameCanBeUndone(): void {
        $this->assertSame("turn", $this->opType(), "the first player is choosing an action");
        $manaBefore = $this->countManaOnTableau();

        $this->respond("actionFocus");
        if ($this->opType() === "gainMana") {
            $this->respond($this->getOpArgs()["target"][0]);
        }
        $this->assertSame($manaBefore + 1, $this->countManaOnTableau(), "focus put one mana on a card");
        $this->assertNotSame(
            "aslot_{$this->color}_none",
            $this->tokenLocation("marker_{$this->color}_1"),
            "and spent an action marker"
        );

        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertNotCount(0, $moves, "setup left a turn-start barrier for the very first player");

        $error = $this->tryUndo();

        $this->assertNull($error, "undo of the first action of the game is accepted");
        $this->assertSame($manaBefore, $this->countManaOnTableau(), "the mana went back");
        $this->assertSame("turn", $this->opType(), "and the action choice is offered again");
    }

    /**
     * BGA #235653, first half: a destination inside Grimheim is never offered to a hero already
     * standing in Grimheim - picking one used to spend the move action while moving nothing.
     */
    public function testMoveInsideGrimheimIsNotOffered(): void {
        $startHex = $this->tokenLocation($this->heroId);
        $this->assertTrue($this->game->hexMap->isInGrimheim($startHex), "heroes start inside Grimheim");

        $this->assertCount(0, $this->grimheimTargets(), "no Grimheim hex is offered while already standing in Grimheim");
        $this->assertNotCount(0, $this->outsideGrimheimTargets(), "moving out of Grimheim is still offered");
    }

    /**
     * BGA #235653, second half: the reporter also could not undo. A move out of Grimheim spends
     * the action, and undo puts both the hero and the action marker back.
     */
    public function testUndoAfterAMoveOutOfGrimheim(): void {
        $startHex = $this->tokenLocation($this->heroId);
        $markerHome = $this->tokenLocation("marker_{$this->color}_1");

        $outside = $this->outsideGrimheimTargets();
        $this->respond($outside[0]);
        $this->assertSame($outside[0], $this->tokenLocation($this->heroId), "the hero left Grimheim");
        $this->assertSame(
            "aslot_{$this->color}_actionMove",
            $this->tokenLocation("marker_{$this->color}_1"),
            "and spent the move action"
        );

        $error = $this->tryUndo();

        $this->assertNull($error, "undo after a move out of Grimheim is accepted");
        $this->assertSame($startHex, $this->tokenLocation($this->heroId), "the hero is back in Grimheim");
        $this->assertSame($markerHome, $this->tokenLocation("marker_{$this->color}_1"), "the spent action comes back");
        $this->assertSame("turn", $this->opType(), "and the action choice is offered again");
    }

    // -- helpers ----------------------------------------------------------------

    private function playerId(): int {
        return (int) $this->game->getActivePlayerId();
    }

    private function opType(): string {
        return $this->getOpArgs()["type"] ?? "";
    }

    private function countManaOnTableau(): int {
        return $this->countTokens("crystal_green", "card%");
    }

    /** Hex destinations offered by the current prompt. */
    private function hexTargets(): array {
        $targets = $this->getOpArgs()["target"] ?? [];
        return array_values(array_filter($targets, fn($t) => is_string($t) && str_starts_with($t, "hex_")));
    }

    /** Offered destinations inside Grimheim, i.e. the misclick targets from #235653. */
    private function grimheimTargets(): array {
        return array_values(array_filter($this->hexTargets(), fn($t) => $this->game->hexMap->isInGrimheim($t)));
    }

    private function outsideGrimheimTargets(): array {
        return array_values(array_filter($this->hexTargets(), fn($t) => !$this->game->hexMap->isInGrimheim($t)));
    }

    /** Harness has a sticky currentPlayerId; sync it to active so the action targets the right player. */
    private function syncCurrentPlayerToActive(): void {
        $this->game->_setCurrentPlayerId($this->playerId());
    }

    /** Runs undo the way the client does (move_id 0 = "undo everything possible"). */
    private function tryUndo(): ?string {
        $this->syncCurrentPlayerToActive();
        try {
            $this->driver->runStep("action_undo", ["move_id" => 0]);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        for ($i = 0; $i < 5 && $this->opType() === ""; $i++) {
            $this->driver->runDispatchLoop();
        }
        $this->syncCurrentPlayerToActive();
        return null;
    }
}
