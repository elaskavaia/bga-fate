<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\States;

use Bga\GameFramework\StateType;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\StateConstants;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\States\GameState;

class PlayerTurnConfirm extends GameState {
    public function __construct(protected Game $game) {
        parent::__construct(
            $game,
            id: StateConstants::STATE_PLAYER_TURN_CONF,
            type: StateType::ACTIVE_PLAYER, // This state type means that one player is active and can do actions
            descriptionMyTurn: clienttranslate('${you} must confirm'), // We tell the ACTIVE player what they must do
            description: clienttranslate('${actplayer} performs an action') // We tell OTHER players what they are waiting for
        );
    }

    public function getArgs(int $active_player_id): array {
        return [];
    }

    #[PossibleAction]
    function action_resolve() {
        $this->game->notify->all("message", ""); // empty message
        if ($this->game->isEndOfGame()) {
            return StateConstants::STATE_END_GAME;
        }

        return GameDispatch::class;
    }

    #[PossibleAction]
    function action_undo(int $move_id = 0) {
        return $this->game->machine->action_undo((int) $this->game->getCurrentPlayerId(), $move_id);
    }

    public function zombie(int $playerId) {
        return;
    }
}
