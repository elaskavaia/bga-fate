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
        // TODO: remove effect printing - temp for development
        $effect = $this->game->material->getRulesFor($target, "effect", "");
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
