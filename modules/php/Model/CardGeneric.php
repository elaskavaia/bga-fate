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
    public function onTriggerDefault(string $triggerName): void {
        $cardId = $this->id;
        if (!$this->canBePlayed($triggerName)) {
            return;
        }
        $owner = $this->getOwner();
        $action = $this->getUseCardOperationType();
        $this->queue($action, $owner, ["target" => $cardId, "prompt" => true]);
    }

    public function canTrigger(string $triggerName): bool {
        $method = $this->getTriggerMethod($triggerName);
        if (method_exists($this, $method)) {
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
        if (!$errorRes) {
            $errorRes = [];
        }

        if (!$this->canTrigger($triggerName)) {
            $errorRes["q"] = Material::ERR_PREREQ;
            $errorRes["err"] = clienttranslate("Cannot be used now");
            return false;
        }
        $cardId = $this->id;
        $on = $this->game->material->getRulesFor($cardId, "on", "");

        // Cards without a trigger can only be used once per turn
        if (!$on && $this->state == 1) {
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

        $op = $this->op->instanciateOperation($r, $this->owner, ["card" => $cardId]);
        if ($op->noValidTargets()) {
            $errorRes = array_merge($errorRes, $op->getErrorInfo());
            return false;
        }
        $errorRes = ["q" => 0];
        return true;
    }
}
