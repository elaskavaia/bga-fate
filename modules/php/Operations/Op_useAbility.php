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
 * Use Ability free action: hero activates a special ability.
 * Also serves as the base class for Op_useEquipment.
 */
class Op_useAbility extends Operation {
    function getPrompt() {
        return clienttranslate("Choose an ability card to use");
    }

    /** Return array of token rows (each with "key") that are candidates for this action. */
    protected function getCandidateCards(string $owner): array {
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$owner");
        return array_filter($cards, function ($card) {
            return str_starts_with($card["key"], "card_ability_") || str_starts_with($card["key"], "card_hero_");
        });
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
        $cards = $this->getCandidateCards($owner);
        $targets = [];
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $r = $this->game->material->getRulesFor($cardId, "r", "");
            if ($r === "") {
                continue;
            }

            $on = $this->game->material->getRulesFor($cardId, "on", "");
            if ($on !== $trigger) {
                continue;
            }
            // Cards without a trigger can only be used once per turn
            if (!$on && $card["state"] == 1) {
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
        $on = $this->game->material->getRulesFor($cardId, "on", "");
        if (!$on) {
            //mark card as used, as these can only be used once per turn
            $this->dbSetTokenState($cardId, 1, "");
        }
        $this->queue($r, $this->getOwner(), ["card" => $cardId, "reason" => $cardId]);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
