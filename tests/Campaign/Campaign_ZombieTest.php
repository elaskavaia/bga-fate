<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

use Bga\Games\Fate\StateConstants;
use Bga\Games\Fate\States\GameDispatch;
use Bga\Games\Fate\States\PlayerTurn;
use Bga\Games\Fate\States\PlayerTurnConfirm;

class Campaign_ZombieTest extends CampaignBaseTest {
    private int $playerId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]);
        $this->playerId = (int) $this->game->getCurrentPlayerId();
    }

    public function testPlayerTurnZombieMakesProgress(): void {
        $this->assertOperation("turn");
        $before = $this->game->machine->db->getLastId();
        (new PlayerTurn($this->game))->zombie($this->playerId);
        $this->assertNotEquals($before, $this->game->machine->db->getLastId(), "zombie must change the machine");
    }

    public function testPlayerTurnZombieFinishesWholeTurn(): void {
        for ($i = 0; $i < 300; $i++) {
            $this->driver->runDispatchLoop();
            $stateId = $this->game->gamestate->getCurrentMainStateId();
            if ($stateId == StateConstants::STATE_PLAYER_TURN_CONF) {
                (new PlayerTurnConfirm($this->game))->zombie($this->playerId);
            } elseif ($stateId == StateConstants::STATE_PLAYER_TURN) {
                $op = $this->game->machine->createTopOperationFromDb($this->playerId);
                if ($op !== null && $op->getType() === "turn" && $op->canSkip()) {
                    $this->assertTrue(true);
                    return;
                }
                (new PlayerTurn($this->game))->zombie($this->playerId);
            }
        }
        $this->fail("zombie did not reach end of turn");
    }

    public function testConfirmZombieLeavesState(): void {
        $state = (new PlayerTurnConfirm($this->game))->zombie($this->playerId);
        $this->assertEquals(GameDispatch::class, $state);
    }

    public function testConfirmZombieStaysWhenMachineHalted(): void {
        while ($ops = $this->game->machine->getTopOperations(null)) {
            $this->game->machine->db->hide(array_column($ops, "id"));
        }
        $this->assertNull((new PlayerTurnConfirm($this->game))->zombie($this->playerId));
    }
}
