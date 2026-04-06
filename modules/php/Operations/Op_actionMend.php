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
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Mend action: remove 2 damage from hero (5 if in Grimheim).
 * In Grimheim, damage may also be removed from equipment cards.
 */
class Op_actionMend extends Operation {
    function getPrompt() {
        if ($this->isInGrimheim()) {
            return clienttranslate("Choose a hero or equipment to repair (up to 5 damage)");
        }
        return clienttranslate("Choose a hero to heal (up to 2 damage)");
    }

    private function isInGrimheim(): bool {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $currentHex = $this->game->tokens->getTokenLocation($heroId);
        return $this->game->hexMap->isInGrimheim($currentHex);
    }

    function getPossibleMoves() {
        if (!$this->isInGrimheim()) {
            $amount = 2;
            return $this->getPossibleMovesDelegate("{$amount}heal");
        }
        $amount = 5;
        return $this->getPossibleMovesDelegate(["{$amount}heal", "{$amount}repairCard"]);
    }

    function resolve(): void {
        $target = $this->getCheckedArg();
        $args = $this->getArgs();
        $delegate = $args["info"][$target]["delegate"] ?? null;
        $this->game->systemAssert("ERR:actionMend:noDelegate", $delegate !== null);
        $this->queue($delegate, $this->getOwner(), ["target" => $target]);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
