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

//20|1|Black Arrows|||||spendGold:3addDamage||<i>Spend 1 attack action in the Robber Camp</i> to loot it.|Starts with 3 arrows here (use [XP] markers).<br>Spend 1 arrow to add 3 damage to this attack action.|Painted black for the funeral they are about to attend.|
// custon on enter trigger + generit manual play effect using r

class CardEquip_BlackArrows extends CardGeneric {
    public function onCardEnter(): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $amount = 3;
        $cardId = $this->id;
        $this->game->effect_moveCrystals($heroId, "yellow", $amount, $cardId, [
            "message" => clienttranslate('${char_name} adds ${count} arrows to ${place_name}'),
        ]);
    }
}
