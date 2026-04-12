<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * gainEquip: Draw the top equipment card from the player's deck and place it on their tableau.
 *
 * Behaviour:
 * - Automated: picks top card from deck_equip_{owner}, places on tableau via effect_gainEquipment,
 *   which fires onEnter (e.g. Black Arrows seeds 3 arrows, Tiara seeds 6 gold).
 * - If deck is empty, auto-skips silently.
 *
 * Used by: quest completion, upgrade flow, debug_equip.
 */
class Op_gainEquip extends Operation {
    private function getTargetCard(): ?string {
        return $this->getDataField("target");
    }

    public function getPossibleMoves() {
        $card = $this->getTargetCard();
        if ($card) {
            return [$card];
        }
        return parent::getPossibleMoves();
    }
    function resolve(): void {
        $owner = $this->getOwner();
        $cardId = $this->getTargetCard();
        if (!$cardId) {
            $top = $this->game->tokens->pickTokensForLocation(1, "deck_equip_{$owner}", "limbo");
            if (empty($top)) {
                return; // deck empty — nothing to gain
            }
            $card = reset($top);
        } else {
            $card = $this->game->tokens->getTokenInfo($cardId);
        }
        $this->effect_gainEquipment($card);
    }

    /**
     * Place an equipment card on a player's tableau and fire its onEnter hook.
     *
     * @param string $cardId  Token id of the equipment card (e.g. "card_equip_1_20")
     * @param string $owner   Player color
     * @param Operation|null $op  Calling operation — required for onEnter to queue sub-ops.
     *                            Pass null during setupNewGame (no triggers fire).
     */
    function effect_gainEquipment(array $card): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $cardId = $card["key"];
        $this->dbSetTokenLocation($cardId, "tableau_$owner", 0, clienttranslate('${char_name} gains ${token_name}'), [
            "char_name" => $heroId,
        ]);

        $cardObj = $this->game->instantiateCard($card, $this);
        $cardObj->onTrigger("enter");
    }
}
