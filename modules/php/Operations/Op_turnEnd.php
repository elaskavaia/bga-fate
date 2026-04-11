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
 * End of turn sequence.
 * Runs automatically after a player has taken their 2 actions (or ended turn early).
 */
class Op_turnEnd extends Operation {
    function resolve(): void {
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
        $this->queueTrigger();
        $hero = $this->game->getHero($owner);
        // 2. Check for upgrade eligibility (spend experience to upgrade hero/abilities)
        $this->queue("upgrade");
        // 3. Add mana to cards with mana generation (green icon)

        $cards = $hero->getTableauCards();
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $manaGen = (int) $this->game->material->getRulesFor($cardId, "mana", 0);
            if ($manaGen > 0) {
                $hero->moveCrystals("green", $manaGen, $cardId, [
                    "message" => clienttranslate('${place_name} generates ${count} mana'),
                ]);
            }
        }

        // Reset "used" flag on ability/equipment cards (state 1 → 0)
        foreach ($cards as $card) {
            if ($card["state"] == 1) {
                $this->dbSetTokenState($card["key"], 0, "");
            }
        }

        // 4. Draw 1 event card (handles hand limit internally)
        $this->queue("drawEvent");

        // 5. Allow cycling top equipment or top ability card
        // TODO

        // Reset attribute trackers to base values
        $hero->recalcTrackers();
    }
}
