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

/**
 * Default class for ability cards that have no bespoke subclass under
 * modules/php/Cards/. Inherits all default Card behavior.
 */
class CardGeneric extends Card {
    public function onTriggerDefault(string $triggerType): void {
        $cardId = $this->id;
        $on = $this->game->material->getRulesFor($cardId, "on", "");
        if ($on !== $triggerType) {
            return;
        }
        $owner = $this->getOwner();
        $action = $this->getUseCardOperationType();
        $op = $this->game->machine->instanciateOperation($action, $owner, ["on" => $triggerType]);
        $info = $op->getArgsInfo()[$cardId] ?? null;
        if (!$info || ($info["q"] ?? 0) != 0) {
            return;
        }
        $this->queue($action, $owner, ["target" => $cardId, "prompt" => true]);
    }
}
