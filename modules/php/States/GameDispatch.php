<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
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
use Bga\GameFramework\States\GameState;
use Bga\Games\Fate\StateConstants;

class GameDispatch extends GameState {
    public function __construct(protected Game $game) {
        parent::__construct($game, id: StateConstants::STATE_GAME_DISPATCH, type: StateType::GAME);
    }

    public function onEnteringState() {
        $state = $this->game->machine->dispatchAll();
        return $state;
    }
}
