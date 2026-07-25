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
        if ($this->canRepairTunic()) {
            return clienttranslate("Choose a hero or equipment to repair");
        }
        return clienttranslate("Choose a hero to heal (up to 2 damage)");
    }

    private function isInGrimheim(): bool {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $currentHex = $this->game->tokens->getTokenLocation($heroId);
        return $this->game->hexMap->isInGrimheim($currentHex);
    }

    /**
     * Home Sewn Tunic repairs itself with a mend action anywhere on the map, unlike
     * the Grimheim-only equipment repair granted by Freyja's Well.
     */
    private function canRepairTunic(): bool {
        $tunic = Op_removeDamage::CARD_TUNIC;
        if (!$this->game->getHero($this->getOwner())->heroHasCardsOnTableau($tunic)) {
            return false;
        }
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $tunic)) > 0;
    }

    function getPossibleMoves() {
        if ($this->isInGrimheim()) {
            return $this->getPossibleMovesDelegate("5removeDamage");
        }
        $moves = $this->getPossibleMovesDelegate("2heal");
        if ($this->canRepairTunic()) {
            unset($moves["q"], $moves["err"]);
            $tunic = Op_removeDamage::CARD_TUNIC;
            $moves[$tunic] = [
                "q" => Material::RET_OK,
                "name" => $this->game->getTokenName($tunic),
                "delegate" => "removeDamage(max)",
            ];
        }
        return $moves;
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
