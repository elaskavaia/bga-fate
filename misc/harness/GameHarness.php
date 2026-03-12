<?php

declare(strict_types=1);

use Bga\GameFramework\States\GameState;
use Bga\Games\Fate\StateConstants;
use Bga\Games\Fate\Tests\GameUT;

/**
 * Extends GameUT with harness-specific getAllDatas() that includes gamestate
 * (the real BGA framework adds this automatically on reload).
 */
class GameHarness extends GameUT {
    /** When set, getHeroOrder() returns this fixed list instead of shuffling. */
    private ?array $heroOrder = null;
    public array $states;

    public function __construct() {
        parent::__construct();
        $this->states = $this->buildStateNameMap();
    }

    protected function getHeroOrder(): array {
        return $this->heroOrder ?? parent::getHeroOrder();
    }

    function debugLog($info, $args = []) {
        echo "{$info}\n";
    }

    public function debug_Op_roll(): void {
        $this->debug_setupGame_h1();
        // scenario specific setup
        $this->tokens->dbSetTokenLocation("monster_goblin_1", "hex_7_9");
        $this->tokens->dbSetTokenLocation("monster_goblin_2", "hex_8_8");
        $this->tokens->dbSetTokenLocation("hero_1", "hex_8_9");
        $this->hexMap->invalidateOccupancy();
        $this->systemAssert("bad setup", !$this->machine->instanciateOperation("roll", $this->getCurrentPlayerColor())->isVoid());
        $this->machine->push("3roll", PCOLOR, []);
        $this->gamestate->changeActivePlayer(PCOLOR_ID);
        $this->gamestate->jumpToState(StateConstants::STATE_PLAYER_TURN);
    }

    /** Reset and set up a 1-player game with hero 1 (Bjorn). */
    public function debug_setupGame_h1(): void {
        $this->setPlayersNumber(1);
        $this->heroOrder = [1, 2, 3, 4];
        $this->tokens->deleteAll();
        $this->machine->db->loadRows([]);
        $this->setupGameTables();
        $this->heroOrder = null;
        $this->notify->all("message", "setup h1 done", []);
        $this->sendReloadAllNotification();
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
        // we have to manually dispatch this because we call it manually
        $this->machine->dispatchAll();
        // that is fine, it will pick right stack on dispatch
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    /** @return array<int, GameState> map of state id => state instance */
    public function buildStateNameMap(): array {
        $map = [];
        $statesDir = __DIR__ . "/../../modules/php/States";
        foreach (glob("$statesDir/*.php") as $file) {
            $className = "Bga\\Games\\Fate\\States\\" . basename($file, ".php");
            if (!class_exists($className)) {
                continue;
            }
            $inst = new $className($this);
            $map[$inst->id] = $inst;
        }
        return $map;
    }

    public function getAllDatas(): array {
        $result = parent::getAllDatas();

        $stateId = $this->gamestate->state_id();
        $activePlayer = (int) $this->getActivePlayerId();

        $stateNameMap = $this->states;
        /** @var GameState */
        $stateInst = $stateNameMap[$stateId];
        $this->systemAssert("state not found $stateId", $stateInst);
        $stateName = $stateInst->name;

        $stateArgs = [];
        try {
            $stateArgs = $stateInst->getArgs($activePlayer);
        } catch (\Throwable $e) {
            echo "Warning: getArgs for state $stateName failed: " . $e->getMessage() . "\n";
        }

        $result["gamestate"] = [
            "id" => $stateId,
            "name" => $stateName,
            "active_player" => $activePlayer,
            "args" => $stateArgs,
        ];

        return $result;
    }
}
