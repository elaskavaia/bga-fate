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
 * gainTracker: Add 1 red crystal to the deck-top equipment card to track quest progress.
 *
 * Same mechanism as Op_spendDurab (red crystal on equipment card), but:
 * - target is locked to the deck-top equip card of the active owner (not a useCard target)
 * - no durability cap (quest counters can exceed any printed durability)
 *
 * Multiplicity prefix works naturally via the OpExpression parser: NgainTracker = N crystals.
 */
class Op_gainTracker extends CountableOperation {
    private function getDeckTopCardId(): ?string {
        $owner = $this->getOwner();
        $top = $this->game->tokens->getTokenOnTop("deck_equip_$owner");
        return $top["key"] ?? null;
    }

    public function getPossibleMoves() {
        $cardId = $this->getDeckTopCardId();
        if (!$cardId) {
            return ["q" => Material::ERR_NOT_APPLICABLE];
        }
        return [$cardId => ["q" => Material::RET_OK]];
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $amount = (int) $this->getCount();
        $heroId = $this->game->getHeroTokenId($this->getOwner());
        $this->game->effect_moveCrystals($heroId, "red", $amount, $cardId, [
            "message" => clienttranslate('${char_name} adds ${count} quest progress to ${place_name}'),
        ]);
    }
}
