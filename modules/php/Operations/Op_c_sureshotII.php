<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * c_sureshotII: Sure Shot II — 2-4 mana: deal that much damage to a monster within attack range.
 *
 * Two-step operation:
 * - Step 1: player selects a monster hex in attack range
 * - Step 2: player chooses mana amount (2 to min(mana_on_card, remaining_health, 4))
 * - Then queues NspendMana:NdealDamage with chosen N and preset target
 *
 * Data Fields:
 * - card: the ability card ID (set by useAbility)
 * - target: (step 2 only) the monster hex selected in step 1
 *
 * Used by: Sure Shot II (card_ability_1_4)
 */
class Op_c_sureshotII extends Operation {
    private function getCard(): ?string {
        return $this->getDataField("card");
    }

    private function getManaOnCard(): int {
        $card = $this->getCard();
        if ($card === null) {
            return 0;
        }
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", $card));
    }

    private function getMonsterHex(): ?string {
        return $this->getDataField("target");
    }

    private function isStep2(): bool {
        return $this->getMonsterHex() !== null;
    }

    private function getMonsterRemainingHealth(): int {
        $hex = $this->getMonsterHex();
        $defenderId = $this->game->hexMap->getCharacterOnHex($hex, null);
        $this->game->systemAssert("ERR:c_sureshotII:noCharOnHex:$hex", $defenderId !== null);
        $health = (int) $this->game->material->getRulesFor($defenderId, "health", "0");
        $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $defenderId));
        return $health - $damage;
    }

    private function getMaxMana(): int {
        $max = min($this->getManaOnCard(), 4);
        if ($this->isStep2()) {
            $max = min($max, $this->getMonsterRemainingHealth());
        }
        return $max;
    }

    function getPrompt() {
        if ($this->isStep2()) {
            return clienttranslate(
                'Choose how much mana to spend (deals that much damage, monster has ${remaining_health} health remaining)'
            );
        }
        return clienttranslate("Choose a monster to deal damage to");
    }

    function getPossibleMoves() {
        $mana = $this->getManaOnCard();
        if ($mana < 2) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Not enough mana")];
        }

        if ($this->isStep2()) {
            $max = $this->getMaxMana();
            $targets = [];
            for ($i = 2; $i <= $max; $i++) {
                $targets[] = "choice_$i";
            }
            return $targets;
        }

        // Step 1: monster selection
        $hero = $this->game->getHero($this->getOwner());
        $hexes = $hero->getMonsterHexesInRange($hero->getAttackRange());
        if (empty($hexes)) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No monsters in range")];
        }
        return $hexes;
    }

    function resolve(): void {
        $target = $this->getCheckedArg();
        $cardId = $this->getCard();

        if (!$this->isStep2()) {
            // Step 1 done — re-queue with selected hex
            $this->queue("c_sureshotII", $this->getOwner(), [
                "card" => $cardId,
                "target" => $target,
                "reason" => $cardId,
            ]);
            return;
        }

        // Step 2 done — parse amount from choice_N
        $amount = (int) substr($target, strlen("choice_"));

        $hex = $this->getMonsterHex();

        $this->queue("{$amount}spendMana:{$amount}dealDamage", $this->getOwner(), [
            "card" => $cardId,
            "target" => $hex,
            "reason" => $cardId,
        ]);
    }

    public function getExtraArgs() {
        if ($this->isStep2()) {
            return ["remaining_health" => $this->getMonsterRemainingHealth()];
        }
        return [];
    }

    public function getUiArgs() {
        if ($this->isStep2()) {
            return [];
        }
        return ["buttons" => false];
    }
}
