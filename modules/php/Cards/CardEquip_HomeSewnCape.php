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
 * Home Sewn Cape (card_equip_1_24)
 *
 * Effect: "Add 1 [MANA] here every time you roll a [RUNE].
 *          2[MANA]: Move 1 area.
 *          3[MANA]: Prevent 2 damage."
 *
 * Passive: per-roll auto-trigger reads rune count from display_battle and
 * adds that many green crystals to this card.
 *
 * Voluntary: spend clauses come from the CSV `r` field via standard
 * `useCard` activation — this class does not need to override that path.
 */
class CardEquip_HomeSewnCape extends CardGeneric {
    public function onRoll(): void {
        $runes = $this->game->countRunes();
        if ($runes <= 0) {
            return;
        }
        $this->queue("{$runes}gainMana", null, ["target" => $this->id]);
    }
}
