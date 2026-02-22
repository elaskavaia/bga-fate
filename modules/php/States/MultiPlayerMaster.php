<?php

declare(strict_types=1);

namespace Bga\Games\Fate\States;

use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\StateType;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\StateConstants;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\States\GameState;

class MultiPlayerMaster extends GameState {
    public function __construct(protected Game $game) {
        parent::__construct(
            $game,
            id: StateConstants::STATE_MULTI_PLAYER_MASTER,
            type: StateType::MULTIPLE_ACTIVE_PLAYER, // This state type means that one player is active and can do actions
            descriptionMyTurn: "",
            description: "Other players making their choices",
            transitions: ["loopback" => StateConstants::STATE_MULTI_PLAYER_MASTER],
            initialPrivate: StateConstants::STATE_MULTI_PLAYER_WAIT_PRIVATE
        );
    }

    public function getArgs(): array {
        // Send playable card ids of the active player privately
        // $this->game->systemAssert("getArgs MultiPlayerMaster");
        //return [];
        $ops = $this->game->machine->db->getOperations(null, "draft");
        $res = [
            "description" => $args["description"] ?? "",
            "_private" => [],
            "ui" => ["undo" => count($ops) > 0],
        ];
        // $ids = $this->game->gamestate->getActivePlayerList();
        // foreach ($ids as $player_id) {
        //     $args = $this->game->machine->getArgs((int) $player_id);
        //     $res["_private"][$player_id] = $args;
        // }

        return $res;
    }

    public function onEnteringState() {
        return $this->game->machine->multiplayerDistpatch();
    }

    #[PossibleAction]
    #[CheckAction(false)]
    function action_undo(int $move_id = 0) {
        $player_id = (int) $this->game->getCurrentPlayerId();
        return $this->game->machine->action_undo($player_id, $move_id);
    }

    public function zombie(int $playerId) {
        $this->game->systemAssert("Not supported zombie in this state");
    }
}
