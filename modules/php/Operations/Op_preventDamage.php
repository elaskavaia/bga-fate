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

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\CountableOperation;
use Override;

/**
 * preventDamage: Prevent up to X incoming damage.
 * Count = max damage to prevent (default 1).
 *
 * Behaviour:
 * - Finds the pending dealDamage operation on the machine stack
 * - Reduces its count by up to this operation's count
 * - If dealDamage count reaches 0, hides it from the stack
 *
 * Rules: "Cards that prevent damage may be used once each time you receive damage."
 * Used by: Dodge, Stoneskin, Riposte, Dreadnought (1spendMana:1preventDamage).
 */
class Op_preventDamage extends CountableOperation {
    private function findDealDamage(): ?Op_dealDamage {
        $dealDamageRow = $this->game->machine->findOperation(
            null,
            null,
            fn($row) => $this->game->machine->instantiateOperationFromDbRow($row)->getType() === "dealDamage"
        );
        if (!$dealDamageRow) {
            return null;
        }
        /** @var Op_dealDamage */
        $dealDamageOp = $this->game->machine->instantiateOperationFromDbRow($dealDamageRow);
        return $dealDamageOp;
    }

    function getPossibleMoves() {
        if (!$this->findDealDamage()) {
            return ["q" => Material::ERR_NOT_APPLICABLE];
        }
        return parent::getPossibleMoves();
    }
    #[Override]
    function getPrompt() {
        return clienttranslate('Prevent ${count} of ${max} damage?');
    }

    #[Override]
    public function getExtraArgs() {
        return ["max" => $this->getCurrentDamage()];
    }

    function getCurrentDamage() {
        $dealDamageOp = $this->findDealDamage();
        $currentCount = $dealDamageOp ? (int) $dealDamageOp->getCount() : 0;
        return $currentCount;
    }
    #[Override]
    function resolve(): void {
        $amount = (int) $this->getCount();
        $dealDamageOp = $this->findDealDamage();
        $this->game->systemAssert("ERR:preventDamage:noDealDamageOnStack", $dealDamageOp);

        $currentCount = (int) $dealDamageOp->getCount();
        $prevented = min($amount, $currentCount);
        $newCount = $currentCount - $prevented;

        // Update the dealDamage operation's count
        $this->game->machine->setCounts($dealDamageOp, $newCount);

        if ($newCount <= 0) {
            $dealDamageOp->destroy();
            $this->game->notifyMessage(clienttranslate('${player_name} prevents ${count} [DAMAGE] (all [DAMAGE] is prevented)'), [
                "count" => $prevented,
            ]);
        } else {
            $this->game->notifyMessage(clienttranslate('${player_name} prevents ${count} [DAMAGE]'), [
                "count" => $prevented,
            ]);
        }
    }
}
