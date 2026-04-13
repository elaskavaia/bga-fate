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

    function getTriggers() {
        return $this->getDataField("on", []);
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
        $triggers = $this->getTriggers();
        if (empty($triggers)) {
            $triggers = [""]; // manual activation — cards without `on` field match
        }
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget => ["q" => 0, "trigger" => $triggers[0] ?? ""]];
        }

        $owner = $this->getOwner();

        $cards = $this->getCandidateCards($owner);
        $targets = [];
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $cardIns = $this->game->instantiateCard($card, $this);
            foreach ($triggers as $trigger) {
                $info = ["q" => 0, "trigger" => $trigger];
                if ($cardIns->canBePlayed($trigger, $info)) {
                    $targets[$cardId] = $info;
                    break;
                }
                $targets[$cardId] = $info;
            }
        }
        return $targets;
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();

        $cardInst = $this->game->instantiateCard($cardId, $this);

        $info = $this->getArgsInfo()[$cardId];
        $trigger = $info["trigger"] ?? "";
        $cardInst->useCard($trigger);
    }

    public function getUiArgs() {
        // if ($this->isOneChoice()) {
        //     return [];
        // }
        return ["buttons" => false];
    }
}
