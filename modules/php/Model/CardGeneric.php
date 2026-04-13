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

namespace Bga\Games\Fate\Model;

use Bga\Games\Fate\Material;

/**
 * Default class for ability cards that have no bespoke subclass under
 * modules/php/Cards/. Inherits all default Card behavior.
 */
class CardGeneric extends Card {
    public function onTrigger(string $triggerName): void {
        $method = $this->getTriggerMethod($triggerName);
        if (!method_exists($this, $method)) {
            $this->onTriggerDefault($triggerName);
            return;
        }

        $this->callOnTriggerMethod($method, $triggerName);
    }
    public function onTriggerDefault(string $triggerName): void {
        if ($triggerName === "enter") {
            return; // lifecycle event - handled only by card on itself
        }

        if (!$this->canBePlayed($triggerName)) {
            return;
        }

        $this->promptUseCard($triggerName);
    }

    function promptUseCard($triggerName) {
        $owner = $this->getOwner();
        $action = "useCard";

        $alreadyOp = $this->game->machine->findOperation($owner, $action);
        if (!$alreadyOp) {
            $this->queue($action, null, ["prompt" => true, "on" => [$triggerName]]);
        } else {
            $op = $this->game->machine->instantiateOperationFromDbRow($alreadyOp);
            $onarr = $op->getDataField("on", []);
            if (in_array($triggerName, $onarr)) {
                return;
            }
            $onarr[] = $triggerName;
            $op->withDataField("on", $onarr);
            $this->game->machine->db->updateData($op->getId(), $op->getDataForDb());
        }
    }

    public function canTriggerEffectOn(string $triggerName): bool {
        if (parent::canTriggerEffectOn($triggerName)) {
            return true;
        }
        $cardId = $this->id;
        $on = $this->game->material->getRulesFor($cardId, "on", "");
        if ($on === $triggerName) {
            return true;
        }

        return false;
    }
    public function canBePlayed(string $triggerName, ?array &$errorRes = null): bool {
        if (!parent::canBePlayed($triggerName, $errorRes)) {
            return false;
        }
        $cardId = $this->id;
        $on = $this->game->material->getRulesFor($cardId, "on", "");

        // Cards without a trigger can only be used once per turn
        if (!$on && $this->getState() == 1) {
            $errorRes["q"] = Material::ERR_OCCUPIED;
            $errorRes["err"] = clienttranslate("Already Used");
            return false;
        }

        $r = $this->game->material->getRulesFor($cardId, "r", "");
        if ($r === "") {
            $errorRes["q"] = Material::ERR_NOT_APPLICABLE;
            $errorRes["err"] = clienttranslate("Static");
            return false;
        }

        $op = $this->op->instantiateOperation($r, $this->owner, ["card" => $cardId]);
        if ($op->noValidTargets()) {
            $errorRes = array_merge($errorRes, $op->getErrorInfo());
            return false;
        }
        $errorRes = array_merge($errorRes, ["q" => 0, "err" => ""]);
        return true;
    }

    function isAttackAction() {
        // if end of attack is on stack we are in the middle of attack
        return $this->game->machine->findOperation($this->owner, "endOfAttack");
    }
}
