<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
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
 * Attack action: hero attacks a monster within range.
 * Player selects a target monster, then delegates to the roll pipeline:
 * roll → resolveHits → dealDamage.
 */
class Op_actionAttack extends Operation {
    function getPossibleMoves(): array {
        $target = $this->getDataField("target", "");
        if ($target) {
            return [$target];
        }
        $hero = $this->game->getHero($this->getOwner());
        return $hero->getMonsterHexesInRange($hero->getAttackRange());
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

        $this->queue("{$strength}roll", null, [
            "target" => $targetHex,
        ]);
    }
}
