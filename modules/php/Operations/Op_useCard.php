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

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Combines useAbility, useEquipment and playCard
 */
class Op_useCard extends Operation {
    function getPrompt() {
        if ($this->isOneChoice()) {
            return clienttranslate("You can use this ability now or skip"); // XXX adjust wording for events
        }
        return clienttranslate("Choose a card to use or play");
    }

    /** Return array of token rows (each with "key") that are candidates for this action. */
    protected function getCandidateCards(string $owner): array {
        $hero = $this->game->getHero($owner);
        $cards = $hero->getTableauCards();
        $cards += $hero->getHandCards();
        return $cards;
    }

    function getTrigger() {
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
            $cardIns = $this->game->instantiateCard($card, $this);
            if (!$cardIns->canTrigger($trigger)) {
                continue;
            }
            $targets[$cardId] = ["q" => 0];
            $cardIns->canBePlayed($trigger, $targets[$cardId]);
        }
        return $targets;
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();

        $cardInst = $this->game->instantiateCard($cardId, $this);
        $cardInst->useCard($this->getTrigger());
    }

    public function getUiArgs() {
        if ($this->isOneChoice()) {
            return [];
        }
        return ["buttons" => false];
    }
}
