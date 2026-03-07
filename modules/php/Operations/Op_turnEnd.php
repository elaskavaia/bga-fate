<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
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
 * End of turn sequence.
 * Runs automatically after a player has taken their 2 actions (or ended turn early).
 */
class Op_turnEnd extends Operation {
    function getPossibleMoves() {
        return ["confirm"];
    }

    function resolve(): void {
        // TODO: implement end-of-turn sequence:
        // 1. Reset action markers to empty slots
        $owner = $this->getOwner();
        $this->dbSetTokenLocation("marker_{$owner}_1", "aslot_{$owner}_empty_1", 0, "");
        $this->dbSetTokenLocation("marker_{$owner}_2", "aslot_{$owner}_empty_2", 0, "");
        // Return any dice left on display_battle to supply
        $dice = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        if (count($dice) > 0) {
            $dieKeys = array_map(fn($d) => $d["key"], $dice);
            $this->dbSetTokensLocation($dieKeys, "supply_die_attack", 6, "");
        }
        $hero = $this->game->getHero($owner);
        // 2. Check for upgrade eligibility (spend experience to upgrade hero/abilities)
        // 3. Add mana to cards with mana generation (green icon)

        $cards = $hero->getTableauCards();
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $manaGen = (int) $this->game->material->getRulesFor($cardId, "mana", 0);
            if ($manaGen > 0) {
                $hero->moveCrystals("green", $manaGen, $cardId, [
                    "message" => clienttranslate('${place_name} generates ${inc} mana'),
                ]);
            }
        }
        // 4. Draw 1 event card (if hand < 4, otherwise allow discard first)
        // TODO: if hand >= 4, offer discard-then-draw choice instead of skipping
        if ($hero->getHandSize() < 4) {
            $hero->drawEventCard();
        }
        // 5. Allow cycling top equipment or top ability card
    }
}
