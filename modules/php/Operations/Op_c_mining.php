<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * c_mining: Mining Equipment (card_equip_4_17) gain half.
 *
 * Effect: gain 2 gold [XP] if adjacent to a mountain area, or 3 gold [XP] if
 * adjacent to 3 mountain areas. Voids when not adjacent to any mountain so the
 * card is only offered where it can pay off (BGA #233793).
 *
 * Used by: card_equip_4_17 Mining Equipment (r = spendDurab:c_mining).
 */
class Op_c_mining extends Operation {
    private function getGoldAmount(): int {
        $mountains = $this->game->countAdjMountains($this->getOwner());
        if ($mountains >= 3) {
            return 3;
        }
        if ($mountains >= 1) {
            return 2;
        }
        return 0;
    }

    function getPossibleMoves() {
        if ($this->getGoldAmount() === 0) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Not adjacent to a mountain area")];
        }
        return parent::getPossibleMoves();
    }

    function resolve(): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $this->game->effect_moveCrystals($heroId, "yellow", $this->getGoldAmount(), "tableau_$owner", [
            "message" => clienttranslate('${char_name} gains ${count} [XP]'),
        ]);
    }

    public function isTrivial(): bool {
        return true;
    }
}
