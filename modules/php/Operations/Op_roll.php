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

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * roll: Roll X attack dice against a target monster.
 * Count = number of dice to roll.
 * Parameter: range filter — "adj" (adjacent), "inRange" (hero attack range), "inRangeN" (fixed range N).
 * After rolling, queues resolveHits to convert dice into damage.
 * Used by: Snipe, Hard Rock, Fire Spark, Throwing Axes/Darts/Knives, etc.
 */
class Op_roll extends CountableOperation {
    function getPrompt() {
        return clienttranslate('Choose a monster to attack with ${count} dice');
    }

    private function getRange(): int {
        $hero = $this->game->getHero($this->getOwner());
        return $hero->getRangeFromParam($this->getParam(0, "inRange"));
    }

    function getPossibleMoves(): array {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget => ["q" => Material::RET_OK]];
        }
        $hero = $this->game->getHero($this->getOwner());
        $hexes = $hero->getMonsterHexesInRange($this->getRange());
        return $hexes;
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();
        $defenderId = $this->game->hexMap->getCharacterOnHex($targetHex);
        $this->game->systemAssert("ERR:roll:noCharOnHex:$targetHex", $defenderId !== null);

        $attackerId = $this->getDataField("attacker") ?? $this->game->getHeroTokenId($this->getOwner());
        $diceCount = (int) $this->getCount();

        // Roll dice onto display_battle
        $this->game->effect_rollAttackDice($attackerId, $defenderId, $diceCount);

        // Queue resolveHits to convert dice into dealDamage
        $this->queue("resolveHits", null, [
            "attacker" => $attackerId,
            "target" => $targetHex,
        ]);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
