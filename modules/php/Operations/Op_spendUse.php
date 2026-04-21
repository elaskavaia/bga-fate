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
use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * spendUse: Mark the context card used this turn (card token state → 1).
 * Voids with ERR_OCCUPIED if already used. Reset by Op_turnEnd.
 *
 * Composable with other costs via paygain, e.g. `spendUse:spendDurab:gainXp`
 *   or `spendMana:spendUse:2addDamage`.
 */
class Op_spendUse extends Operation {
    private function getCardId(): string {
        $cardId = $this->getDataField("card", "");
        return (string) $cardId;
    }

    function getPossibleMoves() {
        // spendUse is implicitly a manual-activation cost: voluntary free-action use only,
        // never fires from a trigger context. Void on non-manual events so branches using
        // spendUse are filtered out of trigger-driven useCard offerings.
        $event = (string) $this->getDataField("event", "");
        if ($event !== "" && $event !== Trigger::Manual->value) {
            return ["q" => Material::ERR_PREREQ];
        }

        $cardId = $this->getCardId();
        if (!$cardId) {
            return [];
        }
        $card = $this->game->instantiateCard($cardId, $this);
        if ($card->isUsed()) {
            return [$cardId => ["q" => Material::ERR_OCCUPIED, "err" => clienttranslate("Already Used")]];
        }
        return [$cardId => ["q" => Material::RET_OK]];
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $card = $this->game->instantiateCard($cardId, $this);
        $card->setUsed(true);
    }
}
