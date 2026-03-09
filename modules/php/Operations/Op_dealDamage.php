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
 * dealDamage: Deal X direct damage (red crystals, no dice) to target monster.
 * Count = amount of damage to deal (default 1).
 * Parameter: target filter — for this iteration we assume "adj" (adjacent monsters only).
 * If total damage >= health, monster is killed and hero gains XP.
 * Used by: Kick, Courage, Lightning Bolt, Retaliation, Vigilance, Heels, etc.
 */
class Op_dealDamage extends CountableOperation {
    function getPrompt() {
        return clienttranslate('Choose a monster to deal ${count} damage to');
    }

    private function getRange(): int {
        // TODO: parse range from param (e.g. "adj"=1, "inRange"=hero attack range, "inRange3"=3)
        return 1;
    }

    private function matchesFilter(string $monsterId): bool {
        // TODO: parse monster filter from param (e.g. "!legend", "rank==3 or legend")
        return true;
    }

    function getPossibleMoves(): array {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget => ["q" => Material::RET_OK]];
        }
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $heroHex = $this->game->hexMap->getCharacterHex($heroId);
        $this->game->systemAssert("ERR:dealDamage:heroNotOnMap:$heroId", $heroHex !== null);
        $hexesInRange = $this->game->hexMap->getHexesInRange($heroHex, $this->getRange());
        $targets = [];
        foreach ($hexesInRange as $hexId) {
            $monsterId = $this->game->hexMap->isOccupiedByCharacterType($hexId, "monster");
            if ($monsterId !== null && $this->matchesFilter($monsterId)) {
                $targets[$hexId] = ["q" => Material::RET_OK];
            }
        }
        return $targets;
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();
        $monsterId = $this->game->hexMap->isOccupiedByCharacterType($targetHex, "monster");
        $this->game->systemAssert("ERR:dealDamage:noMonsterOnHex:$targetHex", $monsterId !== null);

        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $amount = (int) $this->getCount();

        $this->game->effect_moveCrystals($heroId, "red", $amount, $monsterId, [
            "message" => "",
        ]);

        $monster = $this->game->getMonster($monsterId);
        $killed = $monster->applyDamageEffects($amount, $heroId);
        if ($killed) {
            $hero = $this->game->getHero($owner);
            $hero->gainXp($monster->getXpReward());
        }
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
