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

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * Move hero up to X areas (count = max steps).
 * Reuses getReachableHexes() from HexMap with count as maxSteps.
 * Used by: Agility (2moveHero), Maneuver (1moveHero), Fleetfoot (1moveHero), Quick Reflexes (1moveHero)
 */
class Op_moveHero extends CountableOperation {
    function getPrompt() {
        return clienttranslate("Select where to move");
    }

    function getPossibleMoves(): array {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $currentHex = $this->game->tokens->getTokenLocation($heroId);
        $steps = (int) $this->getCount();
        $reachable = $this->game->hexMap->getReachableHexes($currentHex, $steps);
        $moves = [];
        foreach (array_keys($reachable) as $hexId) {
            $moves[$hexId] = ["q" => Material::RET_OK];
        }
        return $moves;
    }

    function resolve(): void {
        $target = $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        // When entering Grimheim, place hero at their home hex
        if ($this->game->hexMap->isInGrimheim($target)) {
            $target = $hero->getRulesFor("location", $target);
        }
        $hero->moveTo($target);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
