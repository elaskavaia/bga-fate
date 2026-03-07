<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
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

use function Bga\Games\Fate\getPart;

/**
 * Monster attack: a single monster attacks an adjacent hero.
 * Queued from Op_turnMonster with data ["char" => $monsterId].
 */
class Op_monsterAttack extends Operation {
    function resolve(): void {
        $monsterId = $this->getDataField("char", "");
        $this->game->systemAssert("Missing monster ID in monsterAttack", $monsterId);

        // Check monster is still alive (on the map)
        $monster = $this->game->getMonster($monsterId);
        $monsterHex = $monster->getHex();
        if ($monsterHex === null) {
            return; // Monster was killed or removed
        }

        // Find heroes in attack range
        $heroesInRange = $this->getHeroesInRange($monster, $monsterHex);
        if (empty($heroesInRange)) {
            return; // No heroes to attack
        }

        // TODO: Hero selection — currently picks weakest (most damaged relative to health).
        // Rules may require different targeting logic (e.g. closest, random, player choice).
        $heroId = $this->pickTarget($heroesInRange);

        // Calculate monster strength with faction bonus
        $strength = $this->getMonsterStrength($monsterId, $heroId);

        // Roll attack dice (places red crystals on hero)
        $hits = $this->game->rollAttackDice($monsterId, $heroId, $strength);

        // Check if hero is knocked out
        if ($hits > 0) {
            $hero = $this->game->getHeroById($heroId);
            $hero->applyDamageEffects($hits);
        }
    }

    /**
     * Find all heroes within attack range of the monster.
     * @return string[] array of hero token IDs (e.g. ["hero_1", "hero_2"])
     */
    private function getHeroesInRange(\Bga\Games\Fate\Model\Monster $monster, string $monsterHex): array {
        $hexesInRange = $this->game->hexMap->getHexesInRange($monsterHex, $monster->getAttackRange());
        $heroes = [];
        foreach ($hexesInRange as $hex) {
            $heroId = $this->game->hexMap->isOccupiedByCharacterType($hex, "hero");
            if ($heroId !== null) {
                $heroes[] = $heroId;
            }
        }
        return $heroes;
    }

    /**
     * Pick the weakest adjacent hero (most damaged relative to health).
     * TODO: Refine hero selection logic based on actual game rules.
     */
    private function pickTarget(array $heroes): string {
        if (count($heroes) === 1) {
            return $heroes[0];
        }

        $weakest = null;
        $lowestEffectiveHp = PHP_INT_MAX;
        foreach ($heroes as $heroId) {
            $hero = $this->game->getHeroById($heroId);
            $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $heroId));
            $effectiveHp = $hero->getMaxHealth() - $damage;
            if ($effectiveHp < $lowestEffectiveHp) {
                $lowestEffectiveHp = $effectiveHp;
                $weakest = $heroId;
            }
        }
        return $weakest;
    }

    /**
     * Get monster attack strength including Trollkin faction bonus.
     * Trollkin monsters get +1 for each other adjacent Trollkin monster near the target hero.
     */
    private function getMonsterStrength(string $monsterId, string $heroId): int {
        $strength = (int) $this->game->getRulesFor($monsterId, "strength", 1);
        $faction = $this->game->getRulesFor($monsterId, "faction", "");

        if ($faction === "trollkin") {
            $heroHex = $this->game->hexMap->getCharacterHex($heroId);
            if ($heroHex !== null) {
                $occ = $this->game->hexMap->getOccupancyMap();
                $adjacentHexes = $this->game->hexMap->getAdjacentHexes($heroHex);
                foreach ($adjacentHexes as $hex) {
                    $char = $occ[$hex]["character"] ?? null;
                    if ($char !== null && $char !== $monsterId && getPart($char, 0) === "monster") {
                        $otherFaction = $this->game->getRulesFor($char, "faction", "");
                        if ($otherFaction === "trollkin") {
                            $strength++;
                        }
                    }
                }
            }
        }

        return $strength;
    }
}
