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
 * Treetreader II (card_ability_2_6): on top of Treetreader I (forest-to-forest move),
 * each time the hero moves into a forest area, heal 1 damage.
 * Not on a push: "it is not an active movement, just something that happened to you" (FORUM.md:3352).
 */
class CardAbility_TreetreaderII extends CardGeneric {
    public function onStep(): void {
        $hero = $this->game->getHero($this->getOwner());
        if ($this->op->getDataField("push", false) || !$hero->isInForest()) {
            return;
        }
        $this->queue("?heal(self)");
    }
}
