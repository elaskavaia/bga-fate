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
     * Two seats, first player's turn: one action taken, undo must rewind to the turn-start barrier
     * exactly as in solo. Reproduces BGA #235314 (undo unavailable after the first action in a
     * multiplayer game).
     */
    public function testUndoAfterAnActionReturnsToTheStartOfTheTurnTwoPlayers(): void {
        $this->setupGame([1, 2]); // Bjorn, Alva
        $this->syncCurrentPlayerToActive();
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearMonstersFromMap();

        $startHex = $this->tokenLocation($this->heroId);
        $moveTarget = "hex_7_9"; // one step out of Grimheim
        $this->assertValidTarget($moveTarget);

        $this->respond($moveTarget);
        $this->assertSame($moveTarget, $this->tokenLocation($this->heroId), "the hero moved");

        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertNotCount(0, $moves, "there is a turn-start snapshot to undo to");

        $this->undo();

        $this->assertSame($startHex, $this->tokenLocation($this->heroId), "the hero is back where the turn started");
        $this->assertSame("turn", $this->opType(), "with the action choice offered again");
        $this->assertContains($moveTarget, $this->getOpArgs()["target"] ?? [], "the same move is available again");
    }

    /**
     * BGA #235314: undo twice in one turn. The first undo rewinds to the turn-start barrier; the
     * player then takes another action and undoes again. The second undo must still reach the same
     * turn-start barrier - but undoRestorePoint cleared the very snapshot it restored to
     * (clearSnapshotsAfter used move_id >=), so the barrier was gone and the second undo failed
     * with "Nothing to undo".
     */
    public function testUndoTwiceInOneTurnStillReachesTheTurnStart(): void {
        $startHex = $this->tokenLocation($this->heroId);
        $moveTarget = "hex_7_9";
        $this->assertValidTarget($moveTarget);

        // First action + undo: works, and lands back at the turn start.
        $this->respond($moveTarget);
        $this->undo();
        $this->assertSame($startHex, $this->tokenLocation($this->heroId), "first undo returned to turn start");
        $this->assertSame("turn", $this->opType(), "action choice offered again");

        // Second action + undo: must reach the same turn-start barrier.
        $this->assertValidTarget($moveTarget);
        $this->respond($moveTarget);
        $this->assertSame($moveTarget, $this->tokenLocation($this->heroId), "the hero moved again");

        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertNotCount(0, $moves, "the turn-start barrier survives the first undo");

        $this->undo();
        $this->assertSame($startHex, $this->tokenLocation($this->heroId), "second undo also returned to turn start");
        $this->assertSame("turn", $this->opType(), "action choice offered again after the second undo");
    }

    /**
     * BGA #235518: the very first player takes Focus as the very first action of the game and
     * undoes it. No seat switch has happened yet, so the only barrier is the one
     * Game::setupGameTables writes explicitly.
     */
    public function testFocusAsTheFirstActionOfTheGameCanBeUndone(): void {
        $this->setupThreeSeats();

        $this->assertSame("turn", $this->opType(), "the first player is choosing an action");
        $manaBefore = $this->countManaOnTableau();

        $this->respond("actionFocus");
        if ($this->opType() === "gainMana") {
            $this->respond($this->getOpArgs()["target"][0]);
        }
        $this->assertSame($manaBefore + 1, $this->countManaOnTableau(), "focus put one mana on a card");
        $this->assertNotSame("aslot_{$this->color}_none", $this->tokenLocation("marker_{$this->color}_1"), "and spent an action marker");

        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertNotCount(0, $moves, "setup left a turn-start barrier for the very first player");

        $error = $this->tryUndo();

        $this->assertNull($error, "undo of the first action of the game is accepted");
        $this->assertSame($manaBefore, $this->countManaOnTableau(), "the mana went back");
        $this->assertSame("turn", $this->opType(), "and the action choice is offered again");
    }

    /**
     * RULES.md: "Grimheim is considered to be one big area where any number of heroes may stand
     * at the same time." So per BGA #235653, first half, a destination inside Grimheim is never
     * offered to a hero already
     * standing in Grimheim - picking one used to spend the move action while moving nothing.
     */
    public function testMoveInsideGrimheimIsNotOffered(): void {
        $this->setupThreeSeats();

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
        $this->setupThreeSeats();

        $startHex = $this->tokenLocation($this->heroId);
        $markerHome = $this->tokenLocation("marker_{$this->color}_1");

        $outside = $this->outsideGrimheimTargets();
        $this->respond($outside[0]);
        $this->assertSame($outside[0], $this->tokenLocation($this->heroId), "the hero left Grimheim");
        $this->assertSame("aslot_{$this->color}_actionMove", $this->tokenLocation("marker_{$this->color}_1"), "and spent the move action");

        $error = $this->tryUndo();

        $this->assertNull($error, "undo after a move out of Grimheim is accepted");
        $this->assertSame($startHex, $this->tokenLocation($this->heroId), "the hero is back in Grimheim");
        $this->assertSame($markerHome, $this->tokenLocation("marker_{$this->color}_1"), "the spent action comes back");
        $this->assertSame("turn", $this->opType(), "and the action choice is offered again");
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

    /**
     * BGA #238872 ("Undo Movement doesn't work", solo, first turn). On a real table the only
     * savepoint protecting the whole first solo turn is the one Game::setupGameTables writes,
     * and it lands at move_id 0 (next_move_id is still 1 when the setup request flushes) -
     * Victoria's dump diagnosis. This drives that exact shape: barrier at move 0, first move,
     * undo, second move, undo again - and pins what the shared undo logic does with it.
     */
    public function testFirstTurnUndoWithTheSetupBarrierAtMoveZero(): void {
        $this->setupGameWithBarrierAtMoveZero();

        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertSame([0], array_keys($moves), "the setup barrier landed at move 0, as on a real table");

        $startHex = $this->tokenLocation($this->heroId);
        $moveTarget = "hex_7_9";
        $this->assertValidTarget($moveTarget);
        $this->respond($moveTarget);
        $this->assertSame($moveTarget, $this->tokenLocation($this->heroId), "the hero moved");

        $error = $this->tryUndo();
        $this->assertNull($error, "first undo against the move-0 barrier is accepted");
        $this->assertSame($startHex, $this->tokenLocation($this->heroId), "the hero is back where the game started");

        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertSame([0], array_keys($moves), "the move-0 barrier survives the restore");

        $this->respond($moveTarget);
        $error = $this->tryUndo();
        $this->assertNull($error, "second undo against the move-0 barrier is accepted");
        $this->assertSame($startHex, $this->tokenLocation($this->heroId), "the hero is back again");
    }

    /**
     * BGA #238872, the structural half. In solo the seat-switch savepoint (OpMachine::dispatchOne)
     * never fires - the active player never changes - so before the fix the WHOLE first turn was
     * protected only by the savepoint the setup request defers (Game::setupGameTables). That write
     * is pending in-process memory until the end-of-request flush; if the setup request ends
     * without it, nothing re-anchored one and the first undo answered "Nothing to undo". Now
     * Op_turnStart::auto re-anchors at the start of every player turn, so the dispatch request
     * that presents the first turn writes its own barrier and the first move can be undone.
     */
    public function testSoloFirstTurnReAnchorsItsOwnBarrier(): void {
        $this->setupGameWithLostSetupSavepoint();

        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertCount(1, $moves, "turnStart re-anchored a barrier of its own");

        $startHex = $this->tokenLocation($this->heroId);
        $moveTarget = "hex_7_9";
        $this->assertValidTarget($moveTarget);
        $this->respond($moveTarget);
        $this->assertSame($moveTarget, $this->tokenLocation($this->heroId), "the hero moved");

        $error = $this->tryUndo();
        $this->assertNull($error, "first undo works without the setup savepoint");
        $this->assertSame($startHex, $this->tokenLocation($this->heroId), "the move was rewound");
        $this->assertSame("turn", $this->opType(), "with the action choice offered again");
    }

    /**
     * BGA #238872, the multiplayer interplay. On the turn handover the seat-switch anchor
     * (OpMachine::dispatchOne) and the Op_turnStart anchor fire in the SAME request, and only one
     * savepoint meta can be pending at a time - they must be idempotent (same player, both
     * barrier=1) so the incoming player still gets exactly one turn-start barrier and can undo
     * their first action.
     */
    public function testTurnHandoverAnchorsTheBarrierForTheIncomingPlayer(): void {
        // Fresh wrapper: the gamestate stub caches active_player per state, and the solo
        // setUp() game would leak its active player into the re-seated one.
        $this->rebuildGame();
        $this->setupGame([1, 2]); // Bjorn, Alva
        $this->syncCurrentPlayerToActive();
        $this->color = $this->getActivePlayerColor();
        $this->clearMonstersFromMap();
        $this->seedDeck("deck_event_$this->color", ["card_event_1_27_1"]);
        $this->clearHand($this->color);

        // Player 1 spends both actions and settles the end of turn, handing over to player 2.
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        $this->skipIfOp("upgrade");
        $this->skipIfOp("drawEvent");
        $this->skipIfOp("demote");
        $firstColor = $this->color;
        $this->syncCurrentPlayerToActive();
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->assertNotSame($firstColor, $this->color, "the turn was handed over to player 2");

        $moves = $this->game->dbMultiUndo->getAvailableUndoMoves($this->playerId());
        $this->assertCount(1, $moves, "the handover left exactly one turn-start barrier for player 2");

        $startHex = $this->tokenLocation($this->heroId);
        $moveTarget = $this->outsideGrimheimTargets()[0];
        $this->respond($moveTarget);
        $this->assertSame($moveTarget, $this->tokenLocation($this->heroId), "player 2 moved");

        $this->undo();

        $this->assertSame($startHex, $this->tokenLocation($this->heroId), "player 2 is back at their turn start");
        $this->assertSame("turn", $this->opType(), "with the action choice offered again");
    }

    // -- helpers ----------------------------------------------------------------

    /** Replace the game with a fresh wrapper + driver, dropping all in-process state. */
    private function rebuildGame(): void {
        $this->game = new GameWrapper();
        $this->driver = new GameDriver($this->game, $this->outputDir);
        $this->driver->setVerbose(0);
    }

    /**
     * Solo setup modelling the BGA #238872 loss: the setup request's deferred savepoint dies
     * unflushed with that request's process, and the queued dispatch (reinforcement, turnStart,
     * turn) runs as its own later request - the one whose flush must now carry the barrier.
     */
    private function setupGameWithLostSetupSavepoint(): void {
        $this->rebuildGame();
        $this->game->setPlayersNumber(1);
        $this->game->loadDbState([]);
        $this->game->setHeroOrder([1]);
        $this->game->setupGameTables();
        // The setup request dies before its flush: both the pending flag and the pending
        // savepoint meta evaporate with the PHP process.
        $this->game->setUndoSavepoint(false);
        $meta = new ReflectionProperty($this->game, "undoSavepointMeta");
        $meta->setValue($this->game, []);
        $this->game->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
        $this->driver->runDispatchLoop();
        $this->driver->emitGameStateChange();
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearEquipDecks();
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
    }

    /**
     * Rebuild the game so the setup savepoint lands at move_id 0 the way production does:
     * the in-mem counter normally starts one move later than a real table, so wind it back
     * before setup flushes the deferred savepoint.
     */
    private function setupGameWithBarrierAtMoveZero(): void {
        $this->rebuildGame();
        $counter = new ReflectionProperty($this->game->undoStore, "nextMoveId");
        $counter->setValue($this->game->undoStore, 0);
        $this->setupGame([1]);
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearEquipDecks();
        $this->clearMonstersFromMap();
        $this->clearHand($this->color);
    }

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

    /** Both #235518 and #235653 come from 3 player tables; re-seat the game the way they were. */
    private function setupThreeSeats(): void {
        $this->setupGame([1, 2, 3]);
        $this->syncCurrentPlayerToActive();
        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->clearMonstersFromMap();
    }

    private function undo(int $moveId = 0): void {
        $this->assertNull($this->tryUndo($moveId), "undo was rejected");
    }

    /** Runs undo the way the client does (move_id 0 = "undo everything possible"). */
    private function tryUndo(int $moveId = 0): ?string {
        $this->syncCurrentPlayerToActive();
        try {
            $this->driver->runStep("action_undo", ["move_id" => $moveId]);
        } catch (Throwable $e) {
            return $e->getMessage();
        }
        for ($i = 0; $i < 5 && $this->opType() === ""; $i++) {
            $this->driver->runDispatchLoop();
        }
        $this->syncCurrentPlayerToActive();
        return null;
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
}
