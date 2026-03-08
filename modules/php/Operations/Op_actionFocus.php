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
 * Focus action: Add 1 mana (green) to one of your cards ?
 */
class Op_actionFocus extends Operation {
    function getPrompt() {
        return clienttranslate("Choose a card to add 1 mana to");
    }

    function getPossibleMoves() {
        $hero = $this->game->getHero($this->getOwner());
        $cards = $hero->getTableauCards();
        $targets = [];
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $mana = (int) $this->game->material->getRulesFor($cardId, "mana", 0);
            if ($mana <= 0) {
                // TODO: approximation if cards produces mana it also uses it - to be verified later
                continue;
            }

            $targets[$cardId] = ["q" => Material::RET_OK];
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
