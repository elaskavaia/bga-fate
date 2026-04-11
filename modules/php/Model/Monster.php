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

    /**
     * Apply damage to this monster.
     * If total damage >= health, the monster is killed, removed from map, and the attacker gains XP.
     * @param string $attackerId hero token id of whoever killed it (for log message and XP award)
     * @return int health - totalDamage: positive if survived, <= 0 if killed (abs = overkill)
     */
    function applyDamageEffects(int $amount, string $attackerId): int {
        $this->game->systemAssert("cannot be negative amount", $amount >= 0);
        $totalDamage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $this->id));
        $health = $this->getHealth();

        $remaining = $health - $totalDamage;
        $this->game->notifyMessage(clienttranslate('${char_name2} deals ${amount} damage to ${char_name} (${remaining} left)'), [
            "char_name" => $this->id,
            "char_name2" => $attackerId,
            "amount" => $amount,
            "remaining" => $remaining,
        ]);

        if ($totalDamage >= $health) {
            // Remove red crystals from monster back to supply
            $this->moveCrystals("red", -$totalDamage, $this->id, ["message" => ""]);

            // Bonus XP from markers placed on the monster (e.g. Prey). Counted before moveTo
            // so the source location is unambiguous.
            $bonusXp = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", $this->id));
            if ($bonusXp > 0) {
                $this->moveCrystals("yellow", -$bonusXp, $this->id, ["message" => ""]);
            }

            // Remove monster from map
            $this->moveTo("supply_monster", clienttranslate('${token_name2} kills ${token_name}'), ["token_name2" => $attackerId]);

            // Award base XP reward, then bonus XP separately so the log makes the source clear.
            $hero = $this->game->getHeroById($attackerId);
            $hero->gainXp($this->getXpReward());
            if ($bonusXp > 0) {
                $hero->gainXp($bonusXp);
            }
        }
        return $remaining;
    }
}
