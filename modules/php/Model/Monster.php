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

    /**
     * Fire Horde faction has range 2, others default to 1.
     */
    function getAttackRange(): int {
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
        return $this->getHealth();
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
}
