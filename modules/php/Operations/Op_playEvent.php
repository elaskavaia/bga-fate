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
use Bga\Games\Fate\OpCommon\Operation;

/**
 * playEvent: Play an event card from hand as a free action.
 *
 * Behaviour:
 * - Normal case: player selects an event card from hand; card is discarded, then its
 *   `r` column effect expression is queued as sub-operations (e.g. "2heal(self)", "dealDamage(adj);moveMonster").
 * - No event cards in hand: getPossibleMoves() returns empty — op auto-skips or is not offered.
 *
 * Rules: cards may not be played mid-action (except attack-action cards after the dice roll),
 * and not outside the player's turn unless the card specifies otherwise.
 */
class Op_playEvent extends Operation {
    function getPrompt() {
        if ($this->isOneChoice()) {
            return clienttranslate("You can play Event Card now or skip");
        }
        return clienttranslate("Choose an Event Card to play");
    }

    function getCurrentTrigger() {
        return $this->getDataField("on", "");
    }

    function requireConfirmation() {
        return (bool) $this->getDataField("prompt", false);
    }
    public function getSkipName() {
        return clienttranslate("Not now");
    }
    public function canSkip() {
        if ($this->requireConfirmation()) {
            return true;
        }
        return parent::canSkip();
    }

    private function instanciateCardEffectOp(string $cardId): Operation {
        $r = $this->game->getRulesForAndAssert($cardId, "r");
        return $this->instanciateOperation($r, $this->getOwner(), ["reason" => $cardId, "card" => $cardId]);
    }

    function getPossibleMoves() {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget];
        }
        $hero = $this->game->getHero($this->getOwner());
        $trigger = $this->getCurrentTrigger();
        $cards = $hero->getHandCards();
        $targets = [];
        foreach (array_keys($cards) as $cardId) {
            $on = $this->game->getRulesFor($cardId, "on", "");
            if ($on !== $trigger) {
                $targets[$cardId] = ["q" => Material::ERR_PREREQ, "err" => clienttranslate("Card cannot be played at this time")];
                continue;
            }
            $op = $this->instanciateCardEffectOp($cardId);
            $targets[$cardId] = $op->getErrorInfo();
        }
        return $targets;
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        // discard first so a) other player see it b) its not in hard for other effects that follow
        $hero->discardEventCard($cardId);
        // TODO: remove effect printing - temp for development
        $effect = $this->game->getRulesFor($cardId, "effect", "");
        $this->game->notifyMessage(clienttranslate('${char_name} plays ${token_name}: ${effect_text}'), [
            "char_name" => $hero->getId(),
            "token_name" => $cardId,
            "effect_text" => $effect,
        ]);
        $op = $this->instanciateCardEffectOp($cardId);
        $this->queueOp($op);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
