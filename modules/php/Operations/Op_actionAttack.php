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
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Attack action: hero attacks a monster within range.
 *
 * == Rules (from RULES.md) ==
 * When attacking, the character chooses a target within its attack range.
 * If nothing else is stated, each character has attack range 1 (adjacent).
 * Sum up strength from hero card + equipment + abilities in play.
 * Roll that many attack dice:
 *   - Hit (crossed axes) — HIT
 *   - Hit with cover (axes with circle) — HIT, except if defender has cover (forest)
 *   - Miss (blank) — MISS
 *   - Rune — MISS, but some effects may apply
 * Place damage (red crystals) on monster. If damage >= health, killed — remove monster, gain XP.
 */
class Op_actionAttack extends Operation {
    function getPossibleMoves(): array {
        $hero = $this->game->getHero($this->getOwner());
        $currentHex = $this->game->tokens->getTokenLocation($hero->getId());
        $hexesInRange = $this->game->hexMap->getHexesInRange($currentHex, $hero->getAttackRange());
        $moves = [];
        foreach ($hexesInRange as $hexId) {
            if ($this->game->hexMap->isOccupiedByCharacterType($hexId, "monster") !== null) {
                $moves[$hexId] = ["q" => Material::RET_OK];
            }
        }
        return $moves;
    }

    public function getPrompt() {
        return clienttranslate("Select a monster to attack");
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }

    function resolve(): void {
        $owner = $this->getOwner();
        $targetHex = $this->getCheckedArg();

        // Find the monster on this hex
        $monsterId = $this->game->hexMap->isOccupiedByCharacterType($targetHex, "monster");
        $this->game->systemAssert("No monster on target hex $targetHex", $monsterId !== null);

        // Calculate attack strength
        // TODO: apply "this attack action" card effects (bonus strength, rerolls, etc.)
        $hero = $this->game->getHero($owner);
        $strength = $hero->getAttackStrength();
        $this->game->systemAssert("Hero has no attack strength", $strength > 0);
        $hits = $this->game->rollAttackDice($hero->getId(), $monsterId, $strength);

        // Apply damage — dice stay on display_battle so the player can see them
        $monster = $this->game->getMonster($monsterId);
        $killed = $monster->applyDamageEffects($hits, $hero->getId());
        if ($killed) {
            $hero->gainXp($monster->getXpReward());
        }
    }
}
