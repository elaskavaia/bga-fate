<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\CountableOperation;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * gainEquip: Draw the top equipment card from the player's deck and place it on their tableau.
 *
 * Behaviour:
 * - Automated: picks top card from deck_equip_{owner}, places on tableau via effect_gainEquipment,
 *   which fires onCardEnter (e.g. Black Arrows seeds 3 arrows, Tiara seeds 6 gold).
 * - If deck is empty, auto-skips silently.
 *
 * Used by: quest completion, upgrade flow, debug_equip.
 */
class Op_gainEquip extends CountableOperation {
    private function getTargetCard(): ?string {
        return $this->getDataField("target");
    }

    public function getPossibleMoves() {
        $card = $this->getTargetCard();
        if ($card) {
            return [$card];
        }
        return ["confirm"];
    }
    function resolve(): void {
        if ($this->getCount() == 0) {
            return; // 0 means it was canceled automatically, likely by counter op
        }
        $owner = $this->getOwner();
        $cardId = $this->getTargetCard();
        if (!$cardId) {
            $top = $this->game->tokens->pickTokensForLocation(1, "deck_equip_{$owner}", "limbo");
            if (empty($top)) {
                return; // deck empty — nothing to gain
            }
            $card = reset($top);
        } else {
            $card = $this->game->tokens->getTokenInfo($cardId);
        }
        $this->effect_gainEquipment($card);
    }

    /**
     * Place an equipment card on a player's tableau and fire its onCardEnter hook.
     * If the card is a Main Weapon, the player's existing Main Weapon (if any) is
     * displaced to limbo first; any crystals parked on it are returned to supply.
     *
     * @param array $card  Token info row (e.g. from getTokenInfo) — must include "key".
     */
    function effect_gainEquipment(array $card): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $cardId = $card["key"];

        $isMainWeapon = $this->game->getRulesFor($cardId, "mw");
        if ($isMainWeapon) {
            $existing = array_keys($this->game->tokens->getTokensOfTypeInLocation("card_equip", "tableau_$owner"));
            foreach ($existing as $existingId) {
                if ($existingId === $cardId) {
                    continue;
                }
                $existingMW = $this->game->getRulesFor($existingId, "mw");
                if ($existingMW) {
                    // Return crystals parked on the discarded card (e.g. Smiterbiter's stored
                    // damage) to their supply so they don't linger in the DB.
                    foreach (["red", "yellow", "green"] as $color) {
                        $count = count($this->game->tokens->getTokensOfTypeInLocation("crystal_$color", $existingId));
                        if ($count > 0) {
                            $this->game->effect_moveCrystals($heroId, $color, -$count, $existingId, ["message" => ""]);
                        }
                    }
                    $this->dbSetTokenLocation(
                        $existingId,
                        "limbo",
                        0,
                        clienttranslate('${char_name} replaces ${token_name} with ${token_name2} (Main Weapon)'),
                        [
                            "char_name" => $heroId,
                            "token_name2" => $cardId,
                        ]
                    );
                }
            }
        }

        // Sweep quest-progress crystals (red) parented to the card back to supply
        // before it lands on the tableau. Crystals are accumulated by gainTracker
        // while the card is on deck-top; they have no meaning once the equip is claimed.
        $progress = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
        if ($progress > 0) {
            $this->game->effect_moveCrystals($heroId, "red", -$progress, $cardId, ["message" => ""]);
        }

        $this->dbSetTokenLocation($cardId, "tableau_$owner", 0, clienttranslate('${char_name} gains ${token_name}'), [
            "char_name" => $heroId,
        ]);

        // Reveal the new top of the deck so the client can render it.
        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$owner");
        if ($newTop !== null) {
            $this->dbSetTokenLocation(
                $newTop["key"],
                "deck_equip_$owner",
                0,
                clienttranslate('${char_name} starts new quest for ${token_name}'),
                ["char_name" => $heroId]
            );
        }

        $cardObj = $this->game->instantiateCard($card, $this);
        $cardObj->onTrigger(Trigger::CardEnter);
        // equip changes attributes
        $this->game->getHero($owner)->recalcTrackers();
    }
}
