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

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Play Event free action: play an event card from hand.
 *
 * Rules:
 * "Play an event card from your hand. Perform its effect, then place it
 *  in your discard pile. You may not play event cards while performing an
 *  action, such as in the middle of your movement. However, if the card
 *  says 'this attack action', it may be played after the dice roll in an
 *  attack action to alter the results. You may not play cards outside your
 *  turn unless the card specifically tells you to do so or prevents damage."
 *
 * Current implementation is a stub: effect text is logged but not executed.
 */
class Op_playEvent extends Operation {
    function getPrompt() {
        return clienttranslate("Choose an Event card to play");
    }

    function getPossibleMoves() {
        $hero = $this->game->getHero($this->getOwner());
        $cards = $hero->getHandCards();
        $targets = [];
        foreach ($cards as $card) {
            $cardId = $card["key"];
            // Only event cards can be played
            if (str_starts_with($cardId, "card_event_")) {
                $targets[$cardId] = ["q" => Material::RET_OK];
            }
        }
        return $targets;
    }

    function resolve(): void {
        $target = $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        // discard first so a) other player see it b) its not in hard for other effects that follow
        $hero->discardEventCard($target);
        $effect = $this->game->material->getRulesFor($target, "effect", "");
        // TODO: execute the actual card effect
        $this->game->notifyMessage(clienttranslate('${char_name} plays ${token_name}: ${effect_text}'), [
            "char_name" => $hero->getId(),
            "token_name" => $target,
            "effect_text" => $effect,
        ]);
        $r = $this->game->material->getRulesFor($target, "r", "nop");
        $this->queue($r, $this->getOwner(), ["reason" => $target]);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
