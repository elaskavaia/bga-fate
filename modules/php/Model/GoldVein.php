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

namespace Bga\Games\Fate\Model;

/**
 * Gold Vein — synthetic "monster" used by Orebiter (card_equip_4_19) to make mountain
 * mining flow through the standard attack pipeline (Op_roll → resolveHits → dealDamage).
 * Always dies after one attack, regardless of damage rolled. Damage dealt is converted
 * 1:1 into XP for the attacker.
 */
class GoldVein extends Monster {
    /** GoldVein dies after one attack regardless of damage. */
    function evaluateDamage(int $amount, string $attackerId): array {
        return [
            "killed" => true,
            "remaining" => 0,
            "totalDamage" => $this->getDamage(),
        ];
    }

    function finalizeDamage(int $amount, string $attackerId, bool $noXp = false): void {
        $this->moveTo("supply_monster", "");
        if ($amount) {
            $this->moveCrystals("red", -$amount, $this->id, ["message" => ""]);
            $this->game
                ->getHeroById($attackerId)
                ->gainXp($amount, clienttranslate('${char_name} gains ${count} [XP] (Gold extracted from Mountain)'));
        }
    }
}
