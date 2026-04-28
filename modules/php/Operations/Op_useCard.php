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

use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Combines useAbility, useEquipment and playCard
 */
class Op_useCard extends Operation {
    function getPrompt() {
        return clienttranslate("You may choose a card to play or activate");
    }

    /** Return array of token rows (each with "key") that are candidates for this action. */
    protected function getCandidateCards(string $owner): array {
        $hero = $this->game->getHero($owner);
        $cards = $hero->getTableauCards();
        $cards += $hero->getHandCards();
        return $cards;
    }

    /**
     * Returns the list of Trigger cases this useCard prompt is offering.
     * `on` data field stores wire-format strings (e.g. "TActionAttack");
     * convert them back to Trigger cases at this boundary.
     *
     * @return Trigger[]
     */
    function getTriggers(): array {
        $wire = $this->getDataField("on", []);
        return array_map(fn($w) => Trigger::from((string) $w), $wire);
    }

    function requireConfirmation() {
        return (bool) $this->getDataField("l_confirm", false);
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
        /** @var Trigger[] $triggers */
        $triggers = $this->getTriggers();
        if (empty($triggers)) {
            $triggers = [Trigger::Manual]; // manual activation — cards without `on` field match
        }
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget => ["q" => 0, "trigger" => $triggers[0]->value]];
        }

        $owner = $this->getOwner();
        $excluded = (array) $this->getDataField("excluded", []);

        $cards = $this->getCandidateCards($owner);
        $targets = [];
        foreach ($cards as $card) {
            $cardId = $card["key"];
            if (in_array($cardId, $excluded, true)) {
                continue;
            }
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
        $wasTargeted = $this->getDataField("target") !== null;

        $cardInst = $this->game->instantiateCard($cardId, $this);

        $info = $this->getArgsInfo()[$cardId];
        $triggerWire = $info["trigger"] ?? Trigger::Manual->value;
        $trigger = Trigger::from($triggerWire);
        $cardInst->useCard($trigger);

        // Re-queue so the player may chain a different card to the same trigger.
        // Skip ends the chain (no resolve = no re-queue). The played card is excluded
        // from the next prompt to avoid re-offering it.
        // Targeted useCard (target preset) means a forced single-card play with no
        // choice to chain — don't re-queue in that case.
        if (!$wasTargeted && !$this->isOneChoice() && $trigger != Trigger::Manual) {
            $excluded = (array) $this->getDataField("excluded", []);
            $excluded[] = $cardId;
            $data = $this->getDataForDb();
            $data["excluded"] = $excluded;
            $this->queue($this->getType(), $this->getOwner(), $data);
        }
    }

    public function getUiArgs() {
        // if ($this->isOneChoice()) {
        //     return [];
        // }
        return ["buttons" => false];
    }
}
