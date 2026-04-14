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

namespace Bga\Games\Fate\Cards;

use Bga\Games\Fate\Model\CardGeneric;

/**
 * Alva Hero II (card_hero_2_2)
 *
 * Effect: "End any movement in a forest to add 1 mana [MANA] to any card."
 *
 * Like Alva Hero I but listens on Event::Move (any movement, not just the move action),
 * so card-driven movement (Treetreader, Fleetfoot, Seek Shelter, …) also triggers it.
 */
class CardHero_AlvaHeroII extends CardGeneric {
    public function onMove(): void {
        $hero = $this->game->getHero($this->getOwner());
        if (!$hero->isInForest()) {
            return;
        }
        $this->queue("?gainMana");
    }
}
