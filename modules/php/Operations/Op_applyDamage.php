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

use Bga\Games\Fate\Model\GoldVein;
use Bga\Games\Fate\Model\Hero;
use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * applyDamage: single entry point for damage application. Owns the red-crystal
 * placement, the "deals N damage" notification, marker_attack overkill, and the
 * trigger emission for kills/knockouts. Cleanup (move-to-supply, XP award,
 * knockout-to-Grimheim) happens in a follow-up Op_finishKill so trigger handlers
 * see the dying character on its pre-cleanup hex.
 *
 * Data Fields:
 *  - target: defender token id (required)
 *  - attacker: attacker token id; defaults to the owner's hero
 *  - amount: damage amount to apply (required)
 */
class Op_applyDamage extends Operation {
    public function getPossibleMoves() {
        $target = $this->getDataField("target");
        return $target ? [$target] : [];
    }

    function resolve(): void {
        $defenderId = $this->getDataField("target");
        $attackerId = $this->getDataField("attacker");
        if ($attackerId === null) {
            $attackerId = $this->game->getHeroTokenId($this->getOwner());
        }
        $amount = (int) $this->getDataField("amount", 0);

        $defender = $this->game->getCharacter($defenderId);
        $targetHex = $defender->getHex();

        // 1. Place the red crystals on the defender (was each caller's job).
        $this->game->effect_moveCrystals($attackerId, "red", $amount, $defenderId, ["message" => ""]);

        // 2. Pure detection — no side effects.
        $result = $defender->evaluateDamage($amount, $attackerId);

        // 3. "deals N damage" notification.
        if ($defender instanceof Hero) {
            $this->game->notifyMessage(clienttranslate('${char_name} takes ${amount} [DAMAGE] (${totalDamage}/${health})'), [
                "char_name" => $defenderId,
                "amount" => $amount,
                "totalDamage" => $result["totalDamage"],
                "health" => $defender->getEffectiveHealth(),
            ]);
        } else {
            $this->game->notifyMessage(
                clienttranslate('${char_name2} deals ${amount} [DAMAGE] to ${char_name} (${remaining} health left)'),
                [
                    "char_name" => $defenderId,
                    "char_name2" => $attackerId,
                    "amount" => $amount,
                    "remaining" => $result["remaining"],
                ]
            );
        }

        // 4. Update marker_attack with overkill (positive) or remaining health (negative on kill).
        $this->game->tokens->dbSetTokenLocation("marker_attack", $targetHex, -$result["remaining"], "");

        // 5. Kill path: trigger first, finishKill second — so handlers see the dying char on its hex.
        if ($result["killed"]) {
            $trigger = $defender instanceof Hero ? Trigger::HeroKnockedOut : Trigger::MonsterKilled;
            if (!($defender instanceof GoldVein)) {
                $this->queueTrigger($trigger);
            }
            $this->queue("finishKill", null, [
                "attacker" => $attackerId,
                "target" => $defenderId,
                "amount" => $amount,
            ]);
        }
    }

    public function canSkip() {
        if ($this->noValidTargets()) {
            return parent::canSkip();
        }
        return false;
    }
}
