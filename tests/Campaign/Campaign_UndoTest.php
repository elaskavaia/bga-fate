<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

use Bga\Games\Fate\StateConstants;

/**
 * Undo driven through the real states, the real savepoint hooks and the real DbMultiUndo logic
 * (only the snapshot storage is in-memory, see MultiUndoInMem).
 *
 * Fate writes savepoints only at barriers: the start of a turn, and right after information is
 * revealed (an event draw, an attack roll). Undo therefore means "put me back where this turn
 * started", and a barrier is what stops a player from rewinding past something they have seen.
 *
 * The savepoint is deferred to the end of the request (Game::doCustomUndoSavePoint, reached from
 * sendNotifications) - taking it inline would freeze the still-running operation into the saved
 * machine state, and undo would then replay it (BGA #234789).
 */
class Campaign_UndoTest extends CampaignBaseTest {
    private string $color;
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearEquipDecks();
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
        $this->seedDeck("deck_event_$this->color", ["card_event_1_27_1", "card_event_1_27_2"]);
        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8", "card_monster_9"]);
    }

    /**
     * The regression for BGA #234789: undo after the end-of-turn event draw must not put the
     * draw back on the machine. Pre-fix the snapshot was taken inside Hero::drawEventCard, with
     * the drawEvent operation still on the stack, so undo asked the player to draw again and a
     * second card left the deck.
     */
    public function testUndoAfterTheEventDrawDoesNotReplayTheDraw(): void {
        $this->driveToEventDraw();
        $deckBefore = $this->countTokens("card_event", "deck_event_$this->color");

        $this->respond("confirm");
        $this->assertSame(1, $this->handSize(), "the draw put one card in hand");

        $this->undo();

        $this->assertSame("demote", $this->opType(), "undo lands on the step after the draw, not back inside it");
        $this->assertSame(1, $this->handSize(), "the drawn card stays in hand");
        $this->assertSame($deckBefore - 1, $this->countTokens("card_event", "deck_event_$this->color"), "exactly one card left the deck");
    }

    /** The event draw is a barrier: it is the only place undo can reach once the card is seen. */
    public function testTheEventDrawIsAnUndoBarrier(): void {
        $this->driveToEventDraw();
        $this->respond("confirm");

        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertCount(1, $moves, "the draw barrier dropped the turn-start snapshot");
        $this->assertSame("draw", reset($moves)["label"], "and the surviving snapshot is the draw one");

        $this->undo();

        $this->assertSame(1, $this->handSize(), "undo cannot reach back to before the draw");
    }

    /** The ordinary case: one action taken, undo rewinds to the turn-start barrier. */
    public function testUndoAfterAnActionReturnsToTheStartOfTheTurn(): void {
        $startHex = $this->tokenLocation($this->heroId);
        $markerHome = $this->tokenLocation("marker_{$this->color}_1");
        $moveTarget = "hex_7_9"; // one step out of Grimheim, so the move actually lands somewhere new
        $this->assertValidTarget($moveTarget);

        $this->respond($moveTarget);
        $this->assertSame($moveTarget, $this->tokenLocation($this->heroId), "the hero moved");

        $this->undo();

        $this->assertSame($startHex, $this->tokenLocation($this->heroId), "the hero is back where the turn started");
        $this->assertSame($markerHome, $this->tokenLocation("marker_{$this->color}_1"), "and the action slot is unspent");
        $this->assertSame("turn", $this->opType(), "with the action choice offered again");
        $this->assertContains($moveTarget, $this->getOpArgs()["target"] ?? [], "the same move is available again");
    }

    /**
     * An attack roll reveals information, so it too is a barrier: undo stops right after the roll.
     * The dice a player has already seen stay rolled and the spent action stays spent - only the
     * decisions taken after the reveal can be taken back.
     */
    public function testAnAttackRollIsAnUndoBarrier(): void {
        $heroHex = $this->moveHeroOutOfGrimheim();
        [$monster] = $this->spawnMonsterAdjacent("goblin");
        $monsterHex = $this->tokenLocation($monster);
        $this->rearmTurnStart();

        $this->respond("actionAttack");
        $rolled = $this->countTokens("die_attack", "display_battle");
        $this->assertGreaterThan(0, $rolled, "the attack rolled dice");
        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertCount(1, $moves, "the roll barrier dropped the turn-start snapshot");
        $this->assertSame("roll", reset($moves)["label"]);

        $this->respond($monsterHex);
        $this->undo();

        $this->assertSame($rolled, $this->countTokens("die_attack", "display_battle"), "the rolled dice stay rolled");
        $this->assertSame($heroHex, $this->tokenLocation($this->heroId), "the hero is still on the attacking hex");
        $this->assertSame(
            "aslot_{$this->color}_actionAttack",
            $this->tokenLocation("marker_{$this->color}_1"),
            "and the action spent on the attack stays spent"
        );
    }

    /** Same barrier with two seats: the roll belongs to the player who made it, not to nobody. */
    public function testAnAttackRollAnchorsTheBarrierOnTheRollingPlayer(): void {
        $this->setupGame([3, 4]); // Embla, Boldur
        $this->syncCurrentPlayerToActive();
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearMonstersFromMap();
        $this->moveHeroOutOfGrimheim();
        [$monster] = $this->spawnMonsterAdjacent("goblin");

        $this->respond("actionAttack");

        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertCount(1, $moves, "the rolling player keeps a savepoint taken after the roll");
        $this->assertSame("roll", reset($moves)["label"]);
        $this->assertGreaterThan(0, $this->countTokens("die_attack", "display_battle"), "the attack rolled dice");
    }

    /**
     * The monster die is a reveal too, and it anchors its barrier the same way. Two seats, because
     * a solo game has only one player for customUndoSavepoint to fall back on.
     */
    public function testTheMonsterDieRollAnchorsAnUndoBarrier(): void {
        $this->setupGame([3, 4]); // Embla, Boldur
        $this->syncCurrentPlayerToActive();
        $this->color = $this->getActivePlayerColor();
        $this->game->setGameStateValue("var_monster_die", 1);
        $this->game->machine->push("rollMonsterDie", $this->color);
        $this->game->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
        $this->driver->runDispatchLoop();

        $this->assertSame(1, $this->countTokens("die_monster", "display_monsterturn"), "the die is parked showing its side");
        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertCount(1, $moves, "the roll left a savepoint instead of dropping every one");
        $this->assertSame("roll", reset($moves)["label"]);
    }

    // -- helpers ----------------------------------------------------------------

    private function playerId(): int {
        return (int) $this->game->getActivePlayerId();
    }

    private function handSize(): int {
        return $this->game->getHero($this->color)->getHandSize();
    }

    private function opType(): string {
        return $this->getOpArgs()["type"] ?? "";
    }

    /** Harness has a sticky currentPlayerId; sync it to active so the action targets the right player. */
    private function syncCurrentPlayerToActive(): void {
        $this->game->_setCurrentPlayerId($this->playerId());
    }

    private function undo(int $moveId = 0): void {
        $this->syncCurrentPlayerToActive();
        $this->driver->runStep("action_undo", ["move_id" => $moveId]);
        // Undo lands on GameDispatchForced; BGA re-enters every state it is sent to until one waits
        // for the player, while the harness driver hops a single state per call.
        for ($i = 0; $i < 5 && $this->opType() === ""; $i++) {
            $this->driver->runDispatchLoop();
        }
        $this->syncCurrentPlayerToActive();
    }

    /**
     * Re-arm the turn-start barrier after a test has seeded the board, so undo rewinds to the
     * position the test set up rather than to the one setup left behind.
     */
    private function rearmTurnStart(): void {
        $this->game->customUndoSavepoint($this->playerId(), 1);
        $this->game->sendNotifications();
    }

    /** Spend both actions on free ones and settle the turn end until the event draw is waiting. */
    private function driveToEventDraw(): void {
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skip(); // decline the free third action, ending the turn
        for ($i = 0; $i < 6 && $this->opType() !== "drawEvent"; $i++) {
            $this->skip();
        }
        $this->assertSame("drawEvent", $this->opType(), "expected the end-of-turn event draw");
    }
}
