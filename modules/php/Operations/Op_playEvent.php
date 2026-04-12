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
class Op_playEvent extends Op_useCard {
    protected function getCandidateCards(string $owner): array {
        $hero = $this->game->getHero($owner);
        $cards = $hero->getHandCards();
        return $cards;
    }
}
