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

use Bga\Games\Fate\Cards\CardEquip_Smiterbiter;
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
 *  - spendsExcess: this damage is drawn from Smiterbiter's pending excess pool. Set by
 *    Op_c_sweep and Op_c_nailed. Landing it takes that much back out of the pool, and it
 *    is passed on to the kill trigger, where CardAbility_SweepingStrikeI and
 *    CardAbility_NailedTogetherI read it to ignore a kill their own effect caused.
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

        // Queen I: may only be damaged by adjacent characters - ranged/remote damage is prevented.
        if ($defenderId === "monster_legend_1_1" && $amount > 0) {
            $attackerHex = $this->game->getCharacter($attackerId)->getHex();
            if ($attackerHex === null || $targetHex === null || $this->game->hexMap->getHexDistance($attackerHex, $targetHex) > 1) {
                $this->game->notifyMessage(clienttranslate('${char_name} may only be damaged by adjacent characters - no damage dealt'), [
                    "char_name" => $defenderId,
                ]);
                return;
            }
        }

        // Withdraw only once the damage is known to land - a refusal above leaves it excess.
        if ($this->getDataField("spendsExcess")) {
            $this->getSmiterbiter()?->removePendingExcess($amount);
        }

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
                clienttranslate('${char_name2} deals ${amount} [DAMAGE] to ${char_name} (${totalDamage}/${health})'),
                [
                    "char_name" => $defenderId,
                    "char_name2" => $attackerId,
                    "amount" => $amount,
                    "totalDamage" => $result["totalDamage"],
                    "health" => $defender->getEffectiveHealth(),
                ]
            );
        }

        // 4. Update marker_attack with overkill (positive) or remaining health (negative on kill).
        $this->game->tokens->dbSetTokenLocation("marker_attack", $targetHex, -$result["remaining"], "");

        // 5. Kill path: trigger first, finishKill second — so handlers see the dying char on its hex.
        if ($result["killed"]) {
            $trigger = $defender instanceof Hero ? Trigger::HeroKnockedOut : Trigger::MonsterKilled;
            if (!($defender instanceof GoldVein)) {
                // Carry the flag so a sweep's own kill does not offer that sweep again.
                $this->queueTrigger($trigger, null, ["spendsExcess" => $this->getDataField("spendsExcess")]);
                if (!($defender instanceof Hero)) {
                    // "If you kill a MONSTER" - overkill on a knocked-out hero is not excess.
                    $this->getSmiterbiter()?->addPendingExcess(-$result["remaining"]);
                }
            }
            $this->queue("finishKill", null, [
                "attacker" => $attackerId,
                "target" => $defenderId,
                "amount" => $amount,
            ]);
        }
    }

    /**
     * Smiterbiter's pending-excess pool, or null when this damage must not touch it:
     * a monster's overkill is not the hero's to bank (monster attacks run this same
     * pipeline and heroes can be overkilled), and outside an attack action nothing
     * would ever commit the pool, so it would sit on the card forever.
     */
    private function getSmiterbiter(): ?CardEquip_Smiterbiter {
        $attackerId = $this->getDataField("attacker");
        if ($attackerId !== null && !str_starts_with($attackerId, "hero_")) {
            return null;
        }
        if (!$this->game->machine->findOperation($this->getOwner(), "endOfAttack")) {
            return null;
        }
        /** @var CardEquip_Smiterbiter $card */
        $card = $this->game->instantiateCard(CardEquip_Smiterbiter::CARD_ID, $this);
        return $card;
    }

    public function canSkip() {
        if ($this->noValidTargets()) {
            return parent::canSkip();
        }
        return false;
    }
}
