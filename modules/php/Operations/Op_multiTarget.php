<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\CountableOperation;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * multiTarget: Selects multiple monsters; queues a copy of the next operation
 * per selected target (passed via the "target" data field on each copy).
 *
 * Data Fields:
 * - card: context card id
 *
 * Used by:
 * - card_event_2_26 Multi-Shot — r=2multiTarget(inRange):2roll
 */
class Op_multiTarget extends CountableOperation {
    protected function getCard(): ?string {
        return $this->getDataField("card");
    }

    function getPrompt() {
        return clienttranslate('Select up to ${count} targets');
    }

    function getSubTitle() {
        return $this->game->getTokenName($this->getCard());
    }

    function getMinCount() {
        $hexes = $this->getPossibleMoves();
        return min($this->getCount(), count($hexes));
    }

    function getArgType() {
        return Operation::TTYPE_TOKEN_ARRAY;
    }

    private function getRange(): int {
        $hero = $this->game->getHero($this->getOwner());
        return $hero->getRangeFromParam($this->getParam(0, "adj"));
    }

    private function matchesFilter(string $monsterId): bool {
        $filter = $this->getParam(1, "true");
        return !!$this->game->evaluateExpression($filter, $this->getOwner(), $monsterId);
    }

    function getPossibleMoves(): array {
        $hero = $this->game->getHero($this->getOwner());
        $hexes = $hero->getMonsterHexesInRange($this->getRange(), fn($mId) => $this->matchesFilter($mId));
        return $hexes;
    }

    function resolve(): void {
        $targets = $this->getCheckedArg(true, true);
        if (!is_array($targets)) {
            $targets = [$targets];
        }
        $owner = $this->getOwner();

        $this->destroy(); // cannot be part of top
        $op = $this->game->machine->createTopOperationFromDbForOwner($owner);
        $this->game->systemAssert("ERR:multiTarget:noNextOp", $op);
        $op->destroy();

        foreach ($targets as $hexId) {
            $copy = $op->copy();
            $copy->withDataField("target", $hexId);
            $this->queueOp($copy);
        }
    }

    public function getUiArgs() {
        return ["buttons" => false, "replicate" => false];
    }
}
