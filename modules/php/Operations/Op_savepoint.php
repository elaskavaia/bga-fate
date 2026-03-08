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

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

class Op_savepoint extends Operation {
    function auto(): bool {
        $player_id = $this->getPlayerId();

        if ($player_id) {
            $barrier = (int) $this->getDataField("barrier", 0);
            $label = $this->getDataField("label", $this->getType());
            $this->destroy(); // have to remove first
            $this->game->customUndoSavepoint($player_id, $barrier, $label);
        }
        return true;
    }
}
