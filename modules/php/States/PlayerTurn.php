<?php

declare(strict_types=1);

namespace Bga\Games\Fate\States;

use Bga\GameFramework\Actions\CheckAction;
use Bga\GameFramework\StateType;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\StateConstants;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\Actions\Types\JsonParam;
use Bga\GameFramework\States\GameState;

class PlayerTurn extends GameState {
    public function __construct(protected Game $game) {
        parent::__construct(
            $game,
            id: StateConstants::STATE_PLAYER_TURN,
            type: StateType::ACTIVE_PLAYER, // This state type means that one player is active and can do actions
            descriptionMyTurn: clienttranslate('${you} perform an action'), // We tell the ACTIVE player what they must do
            description: clienttranslate('${actplayer} performs an action') // We tell OTHER players what they are waiting for
        );
    }

    public function getArgs(int $active_player_id): array {
        // Send playable card ids of the active player privately
        $args = $this->game->machine->getArgs($active_player_id);
        return [
            "description" => $args["description"] ?? "",
            "_private" => [
                $active_player_id => $args,
            ],
        ];
    }

    public function onEnteringState(int $active_player_id) {
        return $this->game->machine->onEnteringPlayerState($active_player_id);
    }
    #[PossibleAction]
    function action_resolve(#[JsonParam] array $data) {
        return $this->game->machine->action_resolve((int) $this->game->getCurrentPlayerId(), $data);
    }
    #[PossibleAction]
    function action_skip() {
        return $this->game->machine->action_skip((int) $this->game->getCurrentPlayerId());
    }

    #[PossibleAction]
    function action_whatever() {
        return $this->game->machine->action_whatever((int) $this->game->getCurrentPlayerId());
    }
    #[PossibleAction]
    #[CheckAction(false)]
    function action_undo(int $move_id = 0) {
        return $this->game->machine->action_undo((int) $this->game->getCurrentPlayerId(), $move_id);
    }
    public function zombie(int $playerId) {
        return $this->game->machine->action_whatever($playerId);
    }
}
