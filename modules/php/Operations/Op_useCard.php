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

use Bga\Games\Fate\Model\Event;
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

    /**
     * Returns the list of Event cases this useCard prompt is offering.
     * `on` data field stores wire-format strings (e.g. "EventActionAttack");
     * convert them back to Event cases at this boundary.
     *
     * @return Event[]
     */
    function getTriggers(): array {
        $wire = $this->getDataField("on", []);
        return array_map(fn($w) => Event::from((string) $w), $wire);
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
        /** @var Event[] $triggers */
        $triggers = $this->getTriggers();
        if (empty($triggers)) {
            $triggers = [Event::Manual]; // manual activation — cards without `on` field match
        }
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget => ["q" => 0, "trigger" => $triggers[0]->value]];
        }

        $owner = $this->getOwner();

        $cards = $this->getCandidateCards($owner);
        $targets = [];
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $cardIns = $this->game->instantiateCard($card, $this);
            foreach ($triggers as $trigger) {
                $info = ["q" => 0, "trigger" => $trigger->value];
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
        $triggerWire = $info["trigger"] ?? Event::Manual->value;
        $trigger = Event::from($triggerWire);
        $cardInst->useCard($trigger);
    }

    public function getUiArgs() {
        // if ($this->isOneChoice()) {
        //     return [];
        // }
        return ["buttons" => false];
    }
}
