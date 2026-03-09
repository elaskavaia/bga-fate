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
 * Remove all damage from a target card on the player's tableau.
 * Player selects a damaged card; all red crystals are removed.
 * Used by: Durability event cards.
 */
class Op_repairCard extends Operation {
    function getPrompt() {
        return clienttranslate("Choose a card to repair");
    }

    function getPossibleMoves(): array {
        $owner = $this->getOwner();
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$owner");
        $targets = [];
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
            $targets[$cardId] = ["q" => $damage > 0 ? Material::RET_OK : Material::ERR_NOT_APPLICABLE];
        }
        return $targets;
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $heroId = $this->game->getHeroTokenId($this->getOwner());
        $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
        if ($damage > 0) {
            $this->game->effect_moveCrystals($heroId, "red", -$damage, $cardId, [
                "message" => clienttranslate('${char_name} repairs ${token_name}'),
                "token_name" => $cardId,
            ]);
        }
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
