<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * c_orebiter: Orebiter (card_equip_4_19) — attack an adjacent mountain hex.
 *
 * Rule: "You may attack adjacent mountain areas. For each damage dealt, gain 1 gold [XP]."
 *
 * Design (see DESIGN.md §"Orebiter — you attack the mountain, not a monster"):
 *   Wired in via Op_actionAttack: when Orebiter is on the hero's tableau, the attack
 *   target list includes the card itself; picking it dispatches here. Target is an
 *   adjacent mountain hex, not a monster. Strategy: place a synthetic `monster_goldvein` token on the chosen hex
 *   and run the standard attack pipeline (Op_roll → Op_resolveHits → Op_dealDamage). The
 *   GoldVein subclass overrides applyDamageEffects to convert each damage point into 1 XP
 *   for the attacker, then despawns. Routing through the real pipeline is intentional so
 *   amplifying cards (Berserk, Magic Runes, Quiver, etc.) work correctly.
 *
 * Used by: card_equip_4_19 Orebiter.
 */
class Op_c_orebiter extends Operation {
    function getPrompt() {
        return clienttranslate("Choose an adjacent mountain area to mine");
    }

    function getPossibleMoves() {
        $hero = $this->game->getHero($this->getOwner());
        $heroHex = $hero->getHex();
        $targets = [];
        foreach ($this->game->hexMap->getAdjacentHexes($heroHex) as $hex) {
            if ($this->game->hexMap->getHexTerrain($hex) === "mountain") {
                $targets[$hex] = ["q" => Material::RET_OK];
            }
        }
        if (empty($targets)) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No adjacent mountain area")];
        }
        return $targets;
    }

    function getUiArgs() {
        return ["buttons" => false];
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        $strength = $hero->getAttackStrength();
        $this->game->systemAssert("ERR:c_orebiter:noStrength", $strength > 0);

        $this->game->getMonster("monster_goldvein")->moveTo($targetHex, "");
        $this->game->tokens->dbSetTokenLocation("marker_attack", $targetHex, 0, "");

        $this->queue("roll", null, [
            "target" => $targetHex,
            "count" => $strength,
            "reason" => $this->getReason(), // reason is inherited from parent, i.e. Op_actionAttack
            "card" => $this->getDataField("card", null),
        ]);
    }
}
