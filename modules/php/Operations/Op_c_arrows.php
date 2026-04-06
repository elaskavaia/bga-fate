<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;

/**
 * c_arrows: Burning Arrows — Deal 1 damage to a monster within attack range, or 2 damage if that monster stands in a forest.
 *
 * Rules: "Deal 1 damage to a monster within attack range, or 2 damage if that monster stands in a forest."
 *
 * Behaviour:
 * - Player selects a monster hex within attack range
 * - Deals 2 damage if monster is on a forest hex, 1 damage otherwise
 * - Delegates to dealDamage with preset target and computed damage
 *
 * Used by: Burning Arrows (card_event_1_28)
 */
class Op_c_arrows extends Op_dealDamage {

    function getPrompt() {
        return clienttranslate('Choose a monster to deal damage to (2 in forest, 1 otherwise)');
    }

    function getPossibleMoves(): array {
        $hero = $this->game->getHero($this->getOwner());
        $hexes = $hero->getMonsterHexesInRange($hero->getAttackRange());
        return $hexes;
    }

    protected function getDamageAmount(string $defenderId): int {
        $defender = $this->game->getCharacter($defenderId);
        $hex = $defender->getHex();
        $terrain = $this->game->hexMap->getHexTerrain($hex);
        return $terrain === "forest" ? 2 : 1;
    }
}
