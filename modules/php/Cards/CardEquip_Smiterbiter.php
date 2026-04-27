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
use Bga\Games\Fate\Model\Trigger;

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
 * Storage on monster kill is automatic and bypasses useCard entirely:
 * pulls min(overkill, 3 - stored) red crystals from supply onto this card.
 */
class CardEquip_Smiterbiter extends CardGeneric {
    private const MAX_STORED = 3;

    public function onMonsterKilled(Trigger $event): void {
        $overkill = $this->game->tokens->getTokenState("marker_attack", 0);
        if ($overkill <= 0) {
            return;
        }
        $stored = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $this->id));
        $room = self::MAX_STORED - $stored;
        if ($room <= 0) {
            return;
        }
        $toStore = min($overkill, $room);
        $heroId = $this->game->getHeroTokenId($this->getOwner());
        $this->game->effect_moveCrystals($heroId, "red", $toStore, $this->id, [
            "message" => clienttranslate('${char_name} stores ${count} damage on ${place_name}'),
        ]);
    }
}
