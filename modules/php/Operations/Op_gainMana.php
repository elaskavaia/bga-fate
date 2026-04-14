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
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * gainMana: Add X mana (green crystals) to a chosen card on tableau.
 * Count = amount of mana to add (default 1).
 * Used by: Power Surge (2gainMana), Elementary Student (gainMana), actionFocus (1gainMana).
 */
class Op_gainMana extends CountableOperation {
    function getPrompt() {
        return clienttranslate("Choose a card to add mana to");
    }

    function getPossibleMoves() {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget => ["q" => Material::RET_OK]];
        }
        $owner = $this->getOwner();
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$owner");
        $targets = [];
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $mana = $this->game->material->getRulesFor($cardId, "mana", "");
            if ($mana === "") {
                continue;
            }
            $targets[$cardId] = ["q" => Material::RET_OK];
        }
        return $targets;
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $amount = (int) $this->getCount();
        $this->game->effect_moveCrystals($heroId, "green", $amount, $cardId, [
            "message" => clienttranslate('${char_name} adds ${count} mana to ${place_name}'),
        ]);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }

    public function canSkip() {
        if ($this->noValidTargets()) {
            return parent::canSkip();
        }
        return false; //mandatory is possible
    }

    public function isTrivial(): bool {
        if ($this->isOneChoice()) {
            return true;
        }
        return false;
    }
}
