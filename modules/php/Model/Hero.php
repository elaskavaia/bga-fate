<?php

declare(strict_types=1);

namespace Bga\Games\Fate\Model;

use Bga\Games\Fate\Game;

/**
 * Hero character: has an owner (player color), tableau with cards, and derived stats.
 */
class Hero extends Character {
    private string $owner;

    public function __construct(Game $game, string $heroId) {
        parent::__construct($game, $heroId);
        $this->owner = $this->game->getHeroOwner($heroId);
    }

    function getOwner(): string {
        return $this->owner;
    }

    /**
     * Returns the total attack strength: base hero card + equipment + abilities on tableau.
     * Hero card has an integer strength (e.g. 2), equipment/abilities use "+N" format.
     */
    function getAttackStrength(): int {
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_{$this->owner}");
        $total = 0;
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $strength = $this->game->material->getRulesFor($cardId, "strength", "");
            if ($strength === "" || $strength === null) {
                continue;
            }
            $strength = (string) $strength;
            if (str_starts_with($strength, "+")) {
                $total += (int) substr($strength, 1);
            } else {
                $total += (int) $strength;
            }
        }
        return max($total, 0);
    }

    /**
     * Returns the max health from the hero card on tableau.
     */
    function getMaxHealth(): int {
        $heroCardKey = $this->game->tokens->getTokensOfTypeInLocationSingleKey("card_hero", "tableau_{$this->owner}");
        return (int) $this->game->material->getRulesFor($heroCardKey, "health", 9);
    }

    /**
     * Returns attack range based on equipment cards. Default is 1.
     */
    function getAttackRange(): int {
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_{$this->owner}");
        $maxRange = 1;
        foreach ($cards as $card => $info) {
            $range = (int) $this->game->material->getRulesFor($card, "attack_range", 0);
            if ($range > $maxRange) {
                $maxRange = $range;
            }
        }
        return $maxRange;
    }

    /**
     * Award XP (yellow crystals) from supply to tableau.
     */
    function gainXp(int $amount): void {
        if ($amount <= 0) {
            return;
        }
        $this->moveCrystals("yellow", $amount, "tableau_{$this->owner}");
    }

    /**
     * Apply damage to this hero after monster attack.
     * If total damage >= health, hero is knocked out:
     * - Moved to Grimheim, damage set to 5, 2 town pieces destroyed.
     * @return bool true if the hero was knocked out
     */
    function applyDamageEffects(int $amount): bool {
        $this->game->systemAssert("cannot be negative amount", $amount >= 0);
        $totalDamage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $this->id));
        $health = $this->getMaxHealth();

        $this->game->notifyMessage(clienttranslate('${char_name} takes ${amount} damage (${totalDamage}/${health})'), [
            "char_name" => $this->id,
            "amount" => $amount,
            "totalDamage" => $totalDamage,
            "health" => $health,
        ]);

        if ($totalDamage >= $health) {
            // Adjust damage to exactly 5: remove excess or add missing
            $diff = $totalDamage - 5;
            if ($diff !== 0) {
                $this->moveCrystals("red", -$diff, $this->id, ["message" => ""]);
            }

            // Move hero to their starting hex in Grimheim
            $startHex = $this->getRulesFor("location");
            $this->moveTo(
                $startHex,
                clienttranslate('${char_name} is knocked out and carried back to Grimheim. By their mother. She is not happy.')
            );

            // "Some villagers panic and flee, leaving their houses undefended"
            $this->game->effect_destroyHouses(2, $this->id, clienttranslate('Villagers panic and flee, ${token_name} is left undefended!'));

            if ($this->game->isEndOfGame()) {
                $this->game->handleEndOfGame();
            }
            return true;
        }
        return false;
    }
}
