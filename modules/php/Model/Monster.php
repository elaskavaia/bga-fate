<?php

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
     * If total damage >= health, the monster is killed and removed from map.
     * @param string $attackerId token id of whoever killed it (for log message)
     * @return bool true if the monster was killed
     */
    function applyDamageEffects(int $amount, string $attackerId): bool {
        $this->game->systemAssert("cannot be negative amount", $amount >= 0);
        $totalDamage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $this->id));
        $health = $this->getHealth();

        $this->game->notifyMessage(clienttranslate('${char_name} takes ${amount} damage (${totalDamage}/${health})'), [
            "char_name" => $this->id,
            "amount" => $amount,
            "totalDamage" => $totalDamage,
            "health" => $health,
        ]);

        if ($totalDamage >= $health) {
            // Remove red crystals from monster back to supply
            $this->moveCrystals("red", -$totalDamage, $this->id, ["message" => ""]);
            // Remove monster from map
            $this->moveTo("supply_monster", clienttranslate('${token_name2} kills ${token_name}'), ["token_name2" => $attackerId]);
            return true;
        }
        return false;
    }
}
