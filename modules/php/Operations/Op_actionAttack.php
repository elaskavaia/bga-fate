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
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Attack action: hero attacks a monster within range.
 * Player selects a target monster, then delegates to the roll pipeline:
 * roll → resolveHits → dealDamage.
 */
class Op_actionAttack extends Operation {
    private const OREBITER = "card_equip_4_19";

    function getPossibleMoves(): array {
        $target = $this->getDataField("target", "");
        if ($target) {
            return [$target];
        }
        $hero = $this->game->getHero($this->getOwner());
        $hexes = $hero->getMonsterHexesInRange($hero->getAttackRange());

        // Orebiter equipment: extends the attack target list with a "mine a mountain" choice.
        // Picking it dispatches to Op_c_orebiter which prompts for the actual mountain hex.
        if ($hero->heroHasCardsOnTableau(self::OREBITER)) {
            $op = $this->instantiateOperation("c_orebiter", $this->getOwner(), ["card" => self::OREBITER]);
            if (!$op->isVoid()) {
                $hexes[self::OREBITER] = ["q" => 0, "buttons" => true];
            }
        }

        return $hexes;
    }

    public function getIconicName(): string {
        return clienttranslate("Attack [Op_actionAttack]");
    }

    public function getPrompt() {
        return clienttranslate("Select a monster to attack");
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }

    function resolve(): void {
        $target = $this->getDataField("target", "");
        $targetHex = $target ?: $this->getCheckedArg();
        $hero = $this->game->getHero($this->getOwner());
        $strength = $hero->getAttackStrength();
        $this->game->systemAssert("Hero has no attack strength", $strength > 0);

        if ($targetHex === self::OREBITER) {
            // Orebiter sub-op picks a mountain hex and places monster_goldvein on it,
            // then seeds back its hex via data field "target" for roll/endOfAttack below.
            $this->queue("c_orebiter", null, ["card" => self::OREBITER]);
        } else {
            $this->game->tokens->dbSetTokenLocation("marker_attack", $targetHex, 0, "");
            $this->queue("roll", null, [
                "target" => $targetHex,
                "count" => $strength,
            ]);
        }

        $this->queue("endOfAttack");
    }
}
