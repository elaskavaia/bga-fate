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
 * c_smiter: Spend N red crystals stored on a card, then add N hit dice to
 * the current attack action.
 *
 * Params: none.
 *
 * Data Fields:
 * - card - host card id (red crystals on this card are the spendable pool) - precondition, card must be passed.
 *   Seeded by CardGeneric::canBePlayed when promptUseCard fires.
 *
 * Behaviour:
 * - Range is dynamic: min=1, max=#crystals on the card. CountableOperation's
 *   getRangeMoves() produces choice keys "1".."max" — player picks how many.
 * - resolve() removes N reds from card to supply, then queues NaddDamage to
 *   add N guaranteed hit dice to display_battle.
 * - Precondition fail (no crystals stored): returns ERR_NOT_APPLICABLE — the
 *   useCard prompt is suppressed upstream by canBePlayed.
 *
 * Used by: Smiterbiter (card_equip_4_21, r=c_smiter).
 */
class Op_c_smiter extends CountableOperation {
    function getPrompt() {
        return clienttranslate('Spend stored damage from ${place_name}');
    }

    private function getCardId(): string {
        return $this->getDataField("card", "");
    }

    function getCount() {
        $cardId = $this->getCardId();
        if (!$cardId) {
            return 0;
        }
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
    }

    function getMinCount() {
        return 1;
    }

    function getPossibleMoves() {
        if ($this->getCount() <= 0) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No stored damage")];
        }
        return $this->getRangeMoves();
    }

    function resolve(): void {
        $cardId = $this->getCardId();
        $heroId = $this->game->getHeroTokenId($this->getOwner());
        $chosen = (int) $this->getCheckedArg();

        $this->game->effect_moveCrystals($heroId, "red", -$chosen, $cardId, [
            "message" => clienttranslate('${char_name} spends ${count} stored damage from ${place_name}'),
        ]);

        $this->queue("{$chosen}addDamage", null, ["card" => $cardId, "reason" => $cardId]);
    }
}
