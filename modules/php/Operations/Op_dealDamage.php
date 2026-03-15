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
use Bga\Games\Fate\Model\Hero;
use Bga\Games\Fate\Model\Monster;
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * dealDamage: Deal X direct damage (red crystals, no dice) to target character.
 *
 * Params:
 * - param(0): range specifier passed to getRangeFromParam() — e.g. "adj", "inRange", "inRange3" (default "adj")
 * - param(1): optional filter expression evaluated per monster — e.g. "'rank<=2'", "'rank==3 or legend'" (default "true")
 *
 * Data Fields:
 * - target: preset hex target (skips getPossibleMoves() selection)
 * - attacker: token id of the attacker (defaults to the owner's hero)
 *
 * Behaviour:
 * - Normal case: player selects a monster hex in range matching the filter; deal count damage; if killed, hero gains XP.
 * - Can target heroes too (attacker field set by caller).
 *
 * Used by: Kick, Courage, Lightning Bolt, Retaliation, Vigilance, Heels, etc.
 */
class Op_dealDamage extends CountableOperation {
    function getPrompt() {
        return clienttranslate('Choose a monster to deal ${count} damage to');
    }

    private function getRange(): int {
        $hero = $this->game->getHero($this->getOwner());
        return $hero->getRangeFromParam($this->getParam(0, "adj"));
    }

    private function matchesFilter(string $monsterId): bool {
        $filter = $this->getParam(1, "true");
        $filter = trim($filter, "'");
        return !!$this->game->evaluateExpression($filter, $this->getOwner(), $monsterId);
    }

    function getPossibleMoves(): array {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget => ["q" => Material::RET_OK]];
        }
        $hero = $this->game->getHero($this->getOwner());
        $hexes = $hero->getMonsterHexesInRange($this->getRange(), fn($mId) => $this->matchesFilter($mId));
        $targets = [];
        foreach ($hexes as $hexId) {
            $targets[$hexId] = ["q" => Material::RET_OK];
        }
        return $targets;
    }

    function resolve(): void {
        $targetHex = $this->getCheckedArg();
        $attackerId = $this->getDataField("attacker");
        if ($attackerId === null) {
            $attackerId = $this->game->getHeroTokenId($this->getOwner());
        }
        $amount = (int) $this->getCount();

        $defenderId = $this->game->hexMap->getCharacterOnHex($targetHex, null);
        $this->game->systemAssert("ERR:dealDamage:noCharacterOnHex:$targetHex", $defenderId !== null);

        $this->game->effect_moveCrystals($attackerId, "red", $amount, $defenderId, [
            "message" => "",
        ]);

        $defender = $this->game->getCharacter($defenderId);
        if ($defender instanceof Monster) {
            $killed = $defender->applyDamageEffects($amount, $attackerId);
            if ($killed) {
                $hero = $this->game->getHero($this->getOwner());
                $hero->gainXp($defender->getXpReward());
            }
        } else {
            /** @var Hero $defender */
            $defender->applyDamageEffects($amount);
        }
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
