<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Move action: hero moves to a hex on the board (Iteration 0: no validation beyond "is it a hex").
 */
class Op_actionMove extends Operation {
    function getPossibleMoves(): array {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $currentHex = $this->game->tokens->db->getTokenLocation($heroId);

        $moves = [];
        $tokenTypes = $this->game->material->getTokensWithPrefix("hex");
        foreach ($tokenTypes as $key => $info) {
            // Any hex on the map is valid — no terrain validation in Iteration 0
            if ($key === $currentHex) {
                // Can't move to current position
                $moves[$key] = ["q" => Material::ERR_NOT_APPLICABLE];
            } else {
                $moves[$key] = ["q" => Material::RET_OK];
            }
        }

        return $moves;
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
    function resolve(): void {
        $target = $this->getCheckedArg();
        $heroId = $this->game->getHeroTokenId($this->getOwner());

        $this->dbSetTokenLocation($heroId, $target, null, clienttranslate('${player_name} moves hero to ${token_name}'), [
            "token_name" => $this->game->getTokenName($target, $target),
        ]);
    }

    public function getPrompt() {
        return clienttranslate("Select where to move");
    }
}
