<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Model;

/**
 * Gold Vein — synthetic "monster" used by Orebiter (card_equip_4_19) to make mountain
 * mining flow through the standard attack pipeline (Op_roll → resolveHits → dealDamage).
 * Always dies after one attack, regardless of damage rolled. Damage dealt is converted
 * 1:1 into XP for the attacker.
 */
class GoldVein extends Monster {
    function applyDamageEffects(int $amount, string $attackerId): int {
        $this->game->systemAssert("cannot be negative amount", $amount >= 0);

        $this->moveTo("supply_monster", "");
        if ($amount > 0) {
            $this->game
                ->getHeroById($attackerId)
                ->gainXp($amount, clienttranslate('${char_name} gains ${count} [XP] (Gold extracted from Mountain)'));
        }
        return 0;
    }
}
