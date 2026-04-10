<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * rerollMisses: Reroll all dice on display_battle that show a miss (miss or rune).
 *
 * Rules (Perfect Aim, card_event_1_31):
 * - "Reroll all misses."
 * - Miss = sides with rule "miss" or "rune" (sides 1, 2, 3 on attack die)
 *
 * Behaviour:
 * - Automated: finds all dice on display_battle showing miss/rune, rerolls them
 * - No user input needed
 */
class Op_rerollMisses extends Operation {
    function resolve(): void {
        $dice = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $attackerId = $this->game->getHeroTokenId($this->getOwner());
        foreach ($dice as $die) {
            $roll = (int) $die["state"];
            $rule = $this->game->material->getRulesFor("side_die_attack_$roll", "rule", "miss");
            if ($rule === "miss" || $rule === "rune") {
                $this->game->effect_rollAttackDie($attackerId, $die["key"]);
            }
        }
    }
}
