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
 * Use Ability free action: hero activates a special ability.
 * Also serves as the base class for Op_useEquipment.
 */
class Op_useAbility extends Op_useCard {
    protected function getCandidateCards(string $owner): array {
        $hero = $this->game->getHero($owner);
        $cards = $hero->getTableauCards();
        return array_filter($cards, fn($c) => str_starts_with($c["key"], "card_ability_") || str_starts_with($c["key"], "card_hero_"));
    }
}
