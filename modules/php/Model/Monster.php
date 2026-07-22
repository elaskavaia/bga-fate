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

namespace Bga\Games\Fate\Model;

/**
 * Monster character: has faction, rank, health, and XP reward.
 */
class Monster extends Character {
    function getFaction(): string {
        return $this->getRulesFor("faction");
    }

    /** True if this monster token currently sits on a board hex (as opposed to supply/limbo). */
    function isOnBoard(): bool {
        return str_starts_with($this->game->tokens->getTokenLocation($this->id) ?? "", "hex_");
    }

    /**
     * Surt II has attack range 3 (himself only); Fire Horde faction has range 2; others default to 1.
     */
    function getAttackRange(): int {
        if ($this->id === "monster_legend_4_2") {
            return 3;
        }
        if ($this->getFaction() === "firehorde") {
            return 2;
        }
        return 1;
    }

    function getXpReward(): int {
        return (int) $this->getRulesFor("xp", "0");
    }

    function getHealth(): int {
        return (int) $this->getRulesFor("health", "0");
    }

    function getEffectiveHealth(): int {
        $health = $this->getHealth();
        // Queen II: every other Dead monster has +1 health while she is on the board.
        if (
            $this->getFaction() === "dead" &&
            $this->id !== "monster_legend_1_2" &&
            $this->game->getMonster("monster_legend_1_2")->isOnBoard()
        ) {
            $health += 1;
        }
        return $health;
    }

    /**
     * Base attack strength before support/die modifiers.
     * Wyrm (Nidhuggr) attacks with its remaining health instead of a fixed value.
     */
    function getBaseAttackStrength(): int {
        if ($this->getFaction() === "wyrm") {
            return $this->getRemainingHealth();
        }
        return (int) $this->getRulesFor("strength", 1);
    }

    /**
     * Kill cleanup. Runs from Op_finishKill, after TMonsterKilled has
     * dispatched, so trigger handlers see the monster still on its hex with
     * its bonus crystals intact.
     */
    function finalizeDamage(int $amount, string $attackerId, bool $noXp = false): void {
        $xp = $this->game->countMonsterXp(null, $this->id);
        $this->game->effect_clearCrystals($this->id, $this->id);

        // Remove monster from map
        $this->moveTo("supply_monster", clienttranslate('${token_name2} kills ${token_name}'), ["token_name2" => $attackerId]);

        if (!$noXp) {
            $hero = $this->game->getHeroById($attackerId);
            $hero->gainXp($xp);
        }
    }

    function countHit(string $rule, ?string $defenderHex = null): int {
        // Grendel II: each rune counts as two hits.
        if ($rule === "rune" && $this->id === "monster_legend_3_2") {
            return 2;
        }
        $isHit = parent::countHit($rule, $defenderHex);
        if (!$isHit && $rule === "rune") {
            $attackerFaction = $this->game->material->getRulesFor($this->id, "faction", "");
            if ($attackerFaction === "dead") {
                $isHit = true;
            } elseif ($attackerFaction === "firehorde" && $this->game->getMonster("monster_legend_4_1")->isOnBoard()) {
                // Surt I: runes count as hits for all Fire Horde while he is on the board.
                $isHit = true;
            }
        }
        return $isHit ? 1 : 0;
    }
}
