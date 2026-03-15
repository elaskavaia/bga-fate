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
 * Remove up to X damage from a target card on the player's tableau.
 * Count = max damage to remove (use 99 for "all").
 * Used by: Durability (99repairCard), Mend in Grimheim (5repairCard).
 */
class Op_repairCard extends CountableOperation {
    function getPrompt() {
        return clienttranslate("Choose a card to repair");
    }

    function getPossibleMoves(): array {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget => ["q" => Material::RET_OK]];
        }
        $owner = $this->getOwner();
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$owner");
        $targets = [];
        foreach ($cards as $cardId => $card) {
            $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
            $targets[$cardId] = ["q" => $damage > 0 ? Material::RET_OK : Material::ERR_NOT_APPLICABLE];
        }
        return $targets;
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $heroId = $this->game->getHeroTokenId($this->getOwner());
        $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
        $amount = min($this->getCount(), $damage);
        if ($amount > 0) {
            $this->game->effect_moveCrystals($heroId, "red", -$amount, $cardId, [
                "message" => clienttranslate('${char_name} repairs ${token_name}'),
                "token_name" => $cardId,
            ]);
        }
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
