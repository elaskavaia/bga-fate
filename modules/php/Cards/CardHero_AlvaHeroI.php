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
 * Alva Hero I (card_hero_2_1)
 *
 * Effect: "End your move action in a forest to add 1 mana [MANA] to any card."
 *
 * Listens on Event::ActionMove (move-action specifically). When the action ends,
 * if Alva is on a forest hex, queue a gainMana op so the player picks a target card.
 */
class CardHero_AlvaHeroI extends CardGeneric {
    public function onActionMove(): void {
        $hero = $this->game->getHero($this->getOwner());
        if (!$hero->isInForest()) {
            return;
        }
        // ?gainMana → optional (min=0), so the op auto-skips silently if Alva has no
        // mana-target cards on her tableau instead of throwing a "no valid targets" error.
        $this->queue("?gainMana");
    }
}
