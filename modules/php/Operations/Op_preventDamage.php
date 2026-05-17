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
    private function findDealDamageOp(bool $incoming): ?Op_dealDamage {
        if ($incoming) {
            $owner = null;
        } else {
            $owner = $this->getOwner();
        }

        return $this->game->machine->findOperationOp($owner, null, function ($op) use ($incoming) {
            if ($op->getType() !== "dealDamage") {
                return false;
            }
            if (!$incoming) {
                return true;
            }

            $attacker = $op->getDataField("attacker");
            // Counter-damage (e.g. Riposte's `:2dealDamage`) leaves `attacker` unset until resolve.
            return $attacker !== null;
        });
    }

    function getPossibleMoves() {
        if (!$this->findDealDamageOp(true)) {
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

    function getCurrentDamage(): int {
        $dealDamageOp = $this->findDealDamageOp(true);
        return $dealDamageOp ? (int) $dealDamageOp->getCount() : 0;
    }

    #[Override]
    function resolve(): void {
        $dealDamageOp = $this->findDealDamageOp(true);
        $this->game->systemAssert("ERR:preventDamage:noDealDamageOnStack", $dealDamageOp);

        $currentCount = (int) $dealDamageOp->getCount();
        $prevented = min((int) $this->getCount(), $currentCount);
        $newCount = $currentCount - $prevented;

        $this->game->machine->setCounts($dealDamageOp, $newCount);
        $this->retargetCounterAttackDamage($dealDamageOp);

        if ($newCount <= 0) {
            $dealDamageOp->destroy();
        }
        $this->notifyPrevented($prevented, $newCount <= 0);
    }

    /**
     * A counter-damage dealDamage queued by the same r-expression (e.g. Riposte's
     * `2spendMana:(2preventDamage:2dealDamage)`) should auto-target the attacker.
     * The incoming dealDamage carries `attacker` = monster id; resolve to its current hex.
     */
    private function retargetCounterAttackDamage(Op_dealDamage $incoming): void {
        $follow = $this->findDealDamageOp(false);
        if (!$follow) {
            return;
        }
        $attackerId = $incoming->getDataField("attacker");
        $this->game->systemAssert("ERR:preventDamage:noAttackeeId", $attackerId);
        $attackerHex = $this->game->hexMap->getCharacterHex($attackerId);
        $this->game->machine->setOpDataField($follow, "target", $attackerHex);
    }

    private function notifyPrevented(int $prevented, bool $allPrevented): void {
        $msg = $allPrevented
            ? clienttranslate('${char_name} prevents ${count} [DAMAGE] (all [DAMAGE] is prevented)')
            : clienttranslate('${char_name} prevents ${count} [DAMAGE]');
        $this->notifyMessage($msg, [
            "count" => $prevented,
            "char_name" => $this->game->getHeroTokenId($this->getOwner()),
        ]);
    }
}
