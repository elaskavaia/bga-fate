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

// card_equip_2_16 Tiara
// "Starts with 6 gold [XP] on this card. Gain 1 gold [XP] from here each turn."
// On enter: seed 6 yellow crystals on the card.
// On TurnStart (Alva's only — Op_trigger already scopes to owner's cards): move 1 yellow
// off the card onto Alva via gainXp. No-op silently when the card is empty.

class CardEquip_Tiara extends CardGeneric {
    public function onCardEnter(): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $this->game->effect_moveCrystals($heroId, "yellow", 6, $this->id, [
            "message" => clienttranslate('${char_name} places ${count} gold on ${place_name}'),
        ]);
    }

    public function onTurnStart(): void {
        $remaining = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", $this->id));
        if ($remaining <= 0) {
            return;
        }
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        // Remove 1 gold from the card, then gain 1 XP for Alva.
        $this->game->effect_moveCrystals($heroId, "yellow", -1, $this->id, [
            "message" => clienttranslate('${char_name} takes 1 gold from ${place_name}'),
        ]);
        $this->queue("gainXp", $owner);
    }
}
