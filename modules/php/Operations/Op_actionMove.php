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
 * Move action: hero moves to a hex on the board
 */
class Op_actionMove extends Operation {
    function getPossibleMoves(): array {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $currentHex = $this->game->tokens->db->getTokenLocation($heroId);

        $reachable = $this->game->hexMap->getReachableHexes($currentHex, 3);
        $moves = [];
        foreach (array_keys($reachable) as $hexId) {
            $moves[$hexId] = ["q" => Material::RET_OK];
        }
        return $moves;
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
    function resolve(): void {
        $target = $this->getCheckedArg();
        $heroId = $this->game->getHeroTokenId($this->getOwner());

        // When entering Grimheim, place hero at their home hex (from material)
        if ($this->game->hexMap->isInGrimheim($target)) {
            $target = $this->game->getRulesFor($heroId, "location", $target);
        }

        // TODO: do set location to individual hexes along the path to animate
        $this->dbSetTokenLocation($heroId, $target, null, clienttranslate('${player_name} moves hero to ${place_name}'));
    }

    public function getPrompt() {
        return clienttranslate("Select where to move");
    }
}
