<?php

declare(strict_types=1);

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

    public function getAllDatas(): array {
        $result = parent::getAllDatas();

        $stateId = $this->gamestate->state_id();
        $activePlayer = (int) $this->gamestate->getPlayerActiveThisTurn() ?: $this->curid;

        $stateNameMap = [
            StateConstants::STATE_PLAYER_TURN              => "PlayerTurn",
            StateConstants::STATE_GAME_DISPATCH            => "GameDispatch",
            StateConstants::STATE_MULTI_PLAYER_TURN_PRIVATE => "MultiPlayerTurnPrivate",
            StateConstants::STATE_PLAYER_TURN_CONF         => "PlayerTurnConfirm",
            StateConstants::STATE_GAME_DISPATCH_FORCED     => "GameDispatchForced",
            StateConstants::STATE_MULTI_PLAYER_MASTER      => "MultiPlayerMaster",
            StateConstants::STATE_MULTI_PLAYER_WAIT_PRIVATE => "MultiPlayerWaitPrivate",
            StateConstants::STATE_MACHINE_HALTED           => "MachineHalted",
            StateConstants::STATE_END_GAME                 => "EndScore",
        ];
        $stateName = $stateNameMap[$stateId] ?? "Unknown";

        $stateArgs = [];
        try {
            if ($stateId === StateConstants::STATE_PLAYER_TURN) {
                $args = $this->machine->getArgs($activePlayer);
                // PHP empty arrays serialize as JSON [], but client expects {} for data field
                if (isset($args["data"]) && $args["data"] === []) {
                    $args["data"] = new \stdClass();
                }
                $stateArgs = [
                    "description" => $args["description"] ?? "",
                    "_private"    => [$activePlayer => $args],
                ];
            } elseif ($stateId === StateConstants::STATE_PLAYER_TURN_CONF) {
                $stateArgs = ["description" => "Confirm your action"];
            }
        } catch (\Throwable $e) {
            echo "Warning: getArgs for state $stateName failed: " . $e->getMessage() . "\n";
        }

        $result["gamestate"] = [
            "id"            => $stateId,
            "name"          => $stateName,
            "active_player" => $activePlayer,
            "args"          => $stateArgs,
        ];

        return $result;
    }
}
