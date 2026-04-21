<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\CountableOperation;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * c_hail: Hail of Arrows I — 3[MANA]: Deal 1 damage to up to 3 different monsters within attack range.
 *
 * Data Fields:
 * - card: context card id (card_ability_2_3)
 *
 * Behaviour:
 * - Player selects 1..3 different monster hexes within attack range (multi-select).
 * - Spends 3 mana (fixed cost regardless of how many targets).
 *
 * Used by: Hail of Arrows I (card_ability_2_3).
 */
class Op_c_hail extends CountableOperation {
    protected function getCard(): ?string {
        return $this->getDataField("card");
    }

    protected function getMaxTargets(): int {
        return 3;
    }

    protected function getManaCost(int $selected): int {
        return 3;
    }

    function getPrompt() {
        return clienttranslate('Select up to ${count} monsters to deal 1 damage each (cost: 3 mana)');
    }

    function getCount() {
        return $this->getMaxTargets();
    }

    function getMinCount() {
        $hexes = $this->getPossibleHexes();
        return min(3, count($hexes));
    }

    function getArgType() {
        return Operation::TTYPE_TOKEN_ARRAY;
    }

    function getPossibleHexes() {
        $hero = $this->game->getHero($this->getOwner());
        $hexes = $hero->getMonsterHexesInRange($hero->getAttackRange());
        return $hexes;
    }

    function getPossibleMoves() {
        $card = $this->getCard();
        if (!$card) {
            return ["q" => Material::ERR_PREREQ];
        }

        $manaOp = $this->getPayOp();
        if ($manaOp->isVoid()) {
            return $manaOp->getPossibleMoves();
        }

        $hexes = $this->getPossibleHexes();
        if (count($hexes) === 0) {
            return ["q" => Material::ERR_NOT_APPLICABLE];
        }

        return $hexes;
    }

    /**
     * Build a spendMana op for the precondition check / actual spend.
     * $actualCost=0 means "use the minimum viable cost" (for precondition check).
     */
    function getPayOp(int $actualCost = 0) {
        $cost = $actualCost ?: $this->getManaCost(1);
        $op = $this->instantiateOperation("spendMana");
        $op->withData($this->getData(), merge: true); // strips our count/mcount/confirm
        $op->withDataField("count", $cost);
        return $op;
    }

    function resolve(): void {
        $targets = $this->getCheckedArg(true, true);
        if (!is_array($targets)) {
            $targets = [$targets];
        }
        $owner = $this->getOwner();
        $card = $this->getCard();
        $manaCost = $this->getManaCost(count($targets));
        $op = $this->getPayOp($manaCost);
        $op->checkVoid();
        // Spend mana from the context card first.
        $this->queueOp($op);

        // Then deal 1 damage to each selected hex (pre-set target, attacker = hero).
        $heroId = $this->game->getHeroTokenId($owner);
        foreach ($targets as $hexId) {
            $this->queue("dealDamage", $owner, [
                "target" => $hexId,
                "attacker" => $heroId,
                "card" => $card,
                "reason" => $card,
            ]);
        }
    }

    public function getUiArgs() {
        return ["buttons" => false, "replicate" => false];
    }
}
