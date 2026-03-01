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
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $currentHex = $this->game->tokens->getTokenLocation($heroId);
        // TODO: support attack range 2+ from equipment (e.g. bow)
        $adjacentHexes = $this->game->hexMap->getAdjacentHexes($currentHex);
        $moves = [];
        foreach ($adjacentHexes as $hexId) {
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
        $strength = $this->game->getHeroAttackStrength($owner);
        $this->game->systemAssert("Hero has no attack strength", $strength > 0);

        // Clean up any leftover dice on display from a previous attack
        $leftover = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        if (count($leftover) > 0) {
            $leftoverKeys = array_map(fn($d) => $d["key"], $leftover);
            $this->dbSetTokensLocation($leftoverKeys, "supply_die_attack", 6, "");
        }

        // TODO: attack dice sides are wrong — log does not show the side that was rolled
        // Roll attack dice — pick from supply (silent bulk move), then notify each with its roll result for animation
        $diceResults = [];
        $diceTokens = $this->game->tokens->pickTokensForLocation($strength, "supply_die_attack", "display_battle");
        foreach ($diceTokens as $die) {
            $dieId = $die["key"];
            $roll = $this->game->bgaRand(1, 6);
            $this->dbSetTokenLocation($dieId, "display_battle", $roll, clienttranslate('${player_name} rolls ${token_name}'));
            $diceResults[] = ["id" => $dieId, "roll" => $roll];
        }

        // Check cover: is monster on a forest hex?
        $hasCover = $this->game->hexMap->getHexTerrain($targetHex) === "forest";

        // Count hits
        $hits = 0;
        foreach ($diceResults as $d) {
            $rule = $this->game->material->getRulesFor("side_die_attack_" . $d["roll"], "rule", "miss");
            if ($rule === "hit") {
                $hits++;
            } elseif ($rule === "hitcov" && !$hasCover) {
                $hits++;
            }
            // TODO: handle rune effects (some cards trigger on rune)
            // TODO: handle armor (draugr — reduce hits)
        }

        // Apply damage — dice stay on display_battle so the player can see them
        $this->game->effect_applyDamage($monsterId, $targetHex, $hits, $owner);
    }
}
