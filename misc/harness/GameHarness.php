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

    protected function getHeroOrder(): array {
        return $this->heroOrder ?? parent::getHeroOrder();
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
    }

    /** @return array<int, GameState> map of state id => state instance */
    private function buildStateNameMap(): array {
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

        $stateNameMap = $this->buildStateNameMap();
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
