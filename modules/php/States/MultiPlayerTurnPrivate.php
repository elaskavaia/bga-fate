<?php

declare(strict_types=1);

namespace Bga\Games\Fate\States;

use Bga\GameFramework\StateType;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\StateConstants;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\Actions\Types\JsonParam;
use Bga\GameFramework\States\GameState;

class MultiPlayerTurnPrivate extends GameState {
    public function __construct(protected Game $game) {
        parent::__construct(
            $game,
            id: StateConstants::STATE_MULTI_PLAYER_TURN_PRIVATE,
            type: StateType::PRIVATE,
            descriptionMyTurn: clienttranslate('${you} perform an action'), // We tell the ACTIVE player what they must do
            description: clienttranslate('${actplayer} performs an action'), // We tell OTHER players what they are waiting for
            transitions: ["loopback" => MultiPlayerMaster::class]
        );
    }

    public function getArgs(?int $player_id): array {
        if (!$player_id) {
            return [];
        }
        $this->game->systemAssert("Player id is not set in MultiPlayerTurnPrivate getArgs", $player_id);
        $args = $this->game->machine->getArgs($player_id);
        return $args;
    }

    public function onEnteringState(int $player_id) {
        $this->game->systemAssert("Player id is not set in MultiPlayerTurnPrivate onEnteringState", $player_id);
        if (!$this->game->gamestate->isPlayerActive($player_id)) {
            $this->game->gamestate->setPlayersMultiactive([$player_id], "notpossible", false);
        }
        $state = $this->game->machine->onEnteringPlayerState($player_id);
        return $state;
    }
    #[PossibleAction]
    function action_resolve(#[JsonParam] array $data) {
        $player_id = (int) $this->game->getCurrentPlayerId();
        $this->game->machine->action_resolve($player_id, $data);
        return $this->game->machine->multiplayerDistpatchAfterAction($player_id);
    }
    #[PossibleAction]
    function action_skip() {
        $player_id = (int) $this->game->getCurrentPlayerId();
        $this->game->machine->action_skip($player_id);
        return $this->game->machine->multiplayerDistpatchAfterAction($player_id);
    }
    #[PossibleAction]
    function action_undo(int $move_id = 0) {
        $player_id = (int) $this->game->getCurrentPlayerId();
        $this->game->machine->action_undo($player_id, $move_id);
        return $this->game->machine->multiplayerDistpatchAfterAction($player_id);
    }
    #[PossibleAction]
    function action_whatever() {
        $player_id = (int) $this->game->getCurrentPlayerId();
        $this->game->machine->action_whatever($player_id);
        return $this->game->machine->multiplayerDistpatchAfterAction($player_id);
    }
    public function zombie(int $playerId) {
        $player_id = (int) $this->game->getCurrentPlayerId();
        $this->game->machine->action_whatever($playerId);
        return $this->game->machine->multiplayerDistpatchAfterAction($player_id);
    }
}
