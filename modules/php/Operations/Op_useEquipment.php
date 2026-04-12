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

/**
 * useEquipment: Free action — hero activates an equipped item's effect.
 *
 * Rules: "Use Equipment" is a free action. Equipment effects cost [DAMAGE] (durability).
 *        Cards that prevent damage may be used once each time you receive damage.
 */
class Op_useEquipment extends Op_useCard {
    protected function getCandidateCards(string $owner): array {
        $hero = $this->game->getHero($owner);
        $cards = $hero->getTableauCards();
        return array_filter($cards, fn($c) => str_starts_with($c["key"], "card_equip_"));
    }
}
