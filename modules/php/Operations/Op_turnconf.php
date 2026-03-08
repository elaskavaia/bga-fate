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
use Bga\Games\Fate\Material;

/**
 * Turn end confirmation operation.
 * Requires confirmation based on player preference MA_PREF_CONFIRM_TURN.
 */
class Op_turnconf extends Operation {
    function requireConfirmation() {
        $player_id = $this->getPlayerId();
        $pref = $this->game->getUserPreference($player_id, Material::MA_PREF_CONFIRM_TURN);
        return (bool) $pref;
    }

    function getPrompt() {
        return clienttranslate("You may confirm or Undo your turn");
    }

    public function getSubTitle() {
        return clienttranslate("You can disable this confirmation in preferences");
    }

    function resolve(): void {
        // Nothing to do - just confirmation
    }
}
