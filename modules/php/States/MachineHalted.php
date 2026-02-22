<?php

declare(strict_types=1);

namespace Bga\Games\Fate\States;

use Bga\GameFramework\StateType;
use Bga\Games\Fate\Game;
use Bga\GameFramework\States\GameState;
use Bga\Games\Fate\StateConstants;

/**
 * When nothing is on stack
 */
class MachineHalted extends GameState {
    public function __construct(protected Game $game) {
        parent::__construct($game, id: StateConstants::STATE_MACHINE_HALTED, type: StateType::GAME);
    }

    public function onEnteringState() {
        if ($this->game->isEndOfGame()) {
            if ($this->game->isStudio()) {
                return PlayerTurnConfirm::class;
            }
            return StateConstants::STATE_END_GAME;
        }
        return PlayerTurnConfirm::class;
    }
}
