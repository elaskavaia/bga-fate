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

namespace Bga\Games\Fate\Cards;

use Bga\Games\Fate\Model\CardGeneric;

/**
 * Smiterbiter (card_equip_4_21).
 *
 *   "If you kill a monster in an attack action, any excess damage may be
 *    stored here (max 3 stored). Damage stored here may be added to your
 *    attack action."
 *
 * Spending on attack: routed via the generic on=TActionAttack -> r=c_smiter
 * path. CardGeneric::onTriggerDefault calls promptUseCard, gated by
 * canBePlayed -> Op_c_smiter::noValidTargets (returns ERR_NOT_APPLICABLE
 * when the card holds zero red crystals).
 *
 * Storing does not go through useCard - it is automatic. "Excess" is only
 * knowable once the attack action is over, because a sweep (Sweeping Strike,
 * Nailed Together) may spend the leftover of a kill on the next monster -
 * damage that is dealt was never excess (FORUM.md designer ruling). So a
 * kill's overkill is held here as YELLOW crystals during the attack, a sweep
 * takes back what it actually deals, and onAfterActionAttack commits whatever
 * is left to red, capped at 3.
 *
 * Yellow, because nothing in the game can reach or spend yellow sitting on a
 * card: XP is only counted in tableau_<color> and spendGold is scoped to the
 * host card of the rule invoking it. Green would be handed out as free mana
 * by Op_spendManaAny, which sweeps every tableau card.
 */
class CardEquip_Smiterbiter extends CardGeneric {
    public const CARD_ID = "card_equip_4_21";
    private const MAX_STORED = 3;

    private function isInPlay(): bool {
        return $this->game->tokens->getTokenLocation($this->id) === "tableau_" . $this->getOwner();
    }

    private function getHeroId(): string {
        return $this->game->getHeroTokenId($this->getOwner());
    }

    /** Hold a kill's overkill until the attack action ends and it is known to be excess. */
    public function addPendingExcess(int $amount): void {
        if ($amount <= 0 || !$this->isInPlay()) {
            return;
        }
        $this->game->effect_moveCrystals($this->getHeroId(), "yellow", $amount, $this->id, [
            "message" => clienttranslate('${char_name} sets aside ${count} leftover [DAMAGE] on ${place_name}'),
        ]);
    }

    /** A sweep dealt this damage, so it is no longer excess. */
    public function removePendingExcess(int $amount): void {
        if (!$this->isInPlay()) {
            return;
        }
        $taken = min($amount, $this->getGold());
        if ($taken <= 0) {
            return;
        }
        $this->game->effect_moveCrystals($this->getHeroId(), "yellow", -$taken, $this->id, [
            "message" => clienttranslate('${count} leftover [DAMAGE] on ${place_name} is dealt instead of stored'),
        ]);
    }

    /**
     * Whatever is still pending when the attack action ends is excess. Op_trigger only walks
     * the owner's tableau, so a monster's attack (which runs as the automa colour) never
     * reaches this.
     */
    public function onAfterActionAttack(): void {
        $this->commitPendingExcess();
    }

    /** Store the pending excess, capped at MAX_STORED. */
    public function commitPendingExcess(): void {
        if (!$this->isInPlay()) {
            return;
        }
        $pending = $this->getGold();
        if ($pending <= 0) {
            return;
        }
        $heroId = $this->getHeroId();
        $this->game->effect_moveCrystals($heroId, "yellow", -$pending, $this->id, ["message" => ""]);

        $room = self::MAX_STORED - $this->getDamage();
        $stored = min($pending, $room);
        if ($stored <= 0) {
            return;
        }
        $this->game->effect_moveCrystals($heroId, "red", $stored, $this->id, [
            "message" => clienttranslate('${char_name} stores ${count} damage on ${place_name}'),
        ]);
    }
}
