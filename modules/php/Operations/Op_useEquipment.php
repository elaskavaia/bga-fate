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

use Bga\Games\Fate\OpCommon\Operation;

/**
 * useEquipment: Free action — hero activates an equipped item's effect.
 *
 * Behaviour:
 * - Player selects an equipment card from their tableau
 * - The card's `r` column expression is queued (e.g. "gainDamage:1preventDamage")
 * - The `r` expression handles costs (gainDamage) and effects (preventDamage) via paygain
 *
 * Rules: "Use Equipment" is a free action. Equipment effects cost [DAMAGE] (durability).
 *        Cards that prevent damage may be used once each time you receive damage.
 */
class Op_useEquipment extends Operation {
    function getPrompt() {
        return clienttranslate("Choose an equipment card to use");
    }

    function getTrigger() {
        return $this->getDataField("on", "");
    }

    function getPossibleMoves() {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget];
        }
        $owner = $this->getOwner();
        $trigger = $this->getTrigger();
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card_equip", "tableau_$owner");
        $targets = [];
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $r = $this->game->material->getRulesFor($cardId, "r", "");
            if ($r === "" || $r === "passive") {
                continue;
            }

            $on = $this->game->material->getRulesFor($cardId, "on", "");
            if ($on !== $trigger) {
                continue;
            }

            $op = $this->game->machine->instanciateOperation($r, $owner, ["card" => $cardId]);
            $targets[$cardId] = $op->getErrorInfo();
        }
        return $targets;
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        $effect = $this->game->material->getRulesFor($cardId, "effect", "");
        $this->game->notifyMessage(clienttranslate('${char_name} uses ${token_name}: ${effect_text}'), [
            "char_name" => $hero->getId(),
            "token_name" => $cardId,
            "effect_text" => $effect,
        ]);
        $r = $this->game->material->getRulesFor($cardId, "r", "nop");
        $this->queue($r, $this->getOwner(), ["card" => $cardId, "reason" => $cardId]);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
