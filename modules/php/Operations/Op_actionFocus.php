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
 * Focus action: Add 1 mana (green) to one of your abilities or equipment that uses mana.
 * A card "uses mana" if it has a mana capacity (mana field > 0 in material).
 * A card can receive mana only if current green crystals on it < mana capacity.
 */
class Op_actionFocus extends Operation {
    function getPrompt() {
        return clienttranslate('${you} must choose a card to add 1 mana to');
    }

    function getPossibleMoves() {
        $owner = $this->getOwner();
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$owner");
        $targets = [];
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $manaCapacity = (int) $this->game->material->getRulesFor($cardId, "mana", 0);
            if ($manaCapacity <= 0) {
                continue;
            }
            $currentMana = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", $cardId));
            if ($currentMana < $manaCapacity) {
                $targets[$cardId] = ["q" => Material::RET_OK];
            }
        }
        return $targets;
    }

    function resolve(): void {
        $target = $this->getCheckedArg();
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $this->game->effect_moveCrystals($heroId, "green", 1, $target, [
            "message" => clienttranslate('${char_name} adds 1 mana to ${place_name}'),
        ]);
    }
}
