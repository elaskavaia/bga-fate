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

use Bga\Games\Fate\Material;

/**
 * c_queen: Queen of the Hill — a maneuver: move into the monster's hex (appearing
 * behind them), which is what deals the damage thematically. The movement is part
 * of the effect, not conditional on the monster's survival.
 *
 * Rule text:
 * - Queen of the Hill I (card_ability_3_11): "Deal 2 damage to an adjacent monster and switch places with it."
 * - Queen of the Hill II (card_ability_3_12): "Deal 4 damage to an adjacent monster and switch places with it."
 *
 * Designer notes:
 * - Hero always moves to the target hex (even if the damage kills the monster).
 * - Target must be a hex the hero could legally enter — so mountain-adjacent monsters
 *   are not valid targets (unless Fleetfoot II; handled elsewhere when it exists).
 *
 * Extends Op_dealDamage: inherits damage application. Overrides getPossibleMoves()
 * to add the passable-terrain filter, and resolve() to do the push + hero step.
 *
 * Used by: Queen of the Hill I (`r=2c_queen`), Queen of the Hill II (`r=4c_queen`).
 */
class Op_c_queen extends Op_dealDamage {
    function getPossibleMoves(): array {
        $hexes = parent::getPossibleMoves();
        $targets = [];
        foreach ($hexes as $hex) {
            if ($this->game->hexMap->isImpassable($hex, "hero")) {
                $targets[$hex] = [
                    "q" => Material::ERR_PREREQ,
                    "err" => clienttranslate("You cannot enter that terrain (cannot swap)"),
                ];
            } else {
                $targets[$hex] = ["q" => Material::RET_OK];
            }
        }
        return $targets;
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        $heroOldHex = $hero->getHex();
        $this->game->systemAssert("ERR:c_queen:heroNotOnMap", $heroOldHex !== null);

        $monsterId = $this->game->hexMap->getCharacterOnHex($targetHex, "monster");
        $this->game->systemAssert("ERR:c_queen:noMonsterOnHex:$targetHex", $monsterId !== null);

        parent::resolve();

        // If the monster survived the damage, push it to hero's old hex.
        // If it died, it's already off the map; nothing to push.
        if ($this->game->hexMap->getCharacterHex($monsterId) === $targetHex) {
            $this->game
                ->getMonster($monsterId)
                ->moveTo($heroOldHex, clienttranslate('${char_name} is swapped with ${char_name2}'), ["char_name2" => $hero->getId()]);
        }

        // Hero always moves into the target hex — the movement is the tactic.
        $this->queue("step", null, [
            "hex" => $targetHex,
            "final" => true,
            "reason" => $this->getReason(),
        ]);
    }
}
