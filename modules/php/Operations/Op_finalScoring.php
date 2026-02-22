<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Game;
use Bga\Games\Fate\OpCommon\Operation;

class Op_finalScoring extends Operation {
    function resolve(): void {
        $this->dbSetTokenState(Game::GAME_STAGE, 5, clienttranslate("Final turn complete. Game ends!"));

        $this->game->finalScoring();
    }

    public function getPrompt() {
        return clienttranslate("Final scoring");
    }
}
