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
    public function onTrigger(Event $event): void {
        $method = $this->getTriggerMethod($event);
        if (!method_exists($this, $method)) {
            $this->onTriggerDefault($event);
            return;
        }

        $this->callOnTriggerMethod($method, $event);
    }
    public function onTriggerDefault(Event $event): void {
        if ($event === Event::Enter) {
            return; // lifecycle event - handled only by card on itself
        }

        if (!$this->canBePlayed($event)) {
            return;
        }

        $this->promptUseCard($event);
    }

    public function canTriggerEffectOn(Event $event): bool {
        if (parent::canTriggerEffectOn($event)) {
            return true;
        }
        $cardId = $this->id;
        $on = $this->game->material->getRulesFor($cardId, "on", "");
        // Manual play: generic cards with no `on` field are offered as free-action useCard targets.
        if ($event === Event::Manual && $on === "") {
            return true;
        }
        if ($on === $event->value) {
            return true;
        }

        if ($on === "custom") {
            // Custom-triggered cards (e.g. Bloodline Crystal) accept any event here;
            // canBePlayed() narrows by instantiating the r expression.
            return true;
        }

        return false;
    }
    public function canBePlayed(Event $event, ?array &$errorRes = null): bool {
        if (!parent::canBePlayed($event, $errorRes)) {
            return false;
        }
        $cardId = $this->id;

        $r = $this->game->material->getRulesFor($cardId, "r", "");
        if ($r === "") {
            $errorRes["q"] = Material::ERR_NOT_APPLICABLE;
            $errorRes["err"] = clienttranslate("Static");
            return false;
        }

        $op = $this->op->instantiateOperation($r, $this->owner, ["card" => $cardId, "event" => $event->value]);
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
