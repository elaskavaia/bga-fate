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

use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;
use Override;

/**
 * gainEquip: Draw the top equipment card from the player's deck and place it on their tableau.
 *
 * Behaviour:
 * - Player confirms ("Complete quest"), then the top card of deck_equip_{owner} goes on the tableau via
 *   effect_gainEquipment, which fires onCardEnter (e.g. Black Arrows seeds 3 arrows, Tiara seeds 6 gold).
 * - The gain reveals the next quest, so it is an undo barrier (BGA #251077).
 * - If deck is empty, resolves as a no-op.
 *
 * Used by: quest completion, debug_equip.
 */
class Op_gainEquip extends Operation {
    private function getCardToGain(): ?string {
        return $this->getDataField("target") ?: $this->game->tokens->getTokenOnTop("deck_equip_" . $this->getOwner())["key"] ?? null;
    }

    function getPrompt() {
        return clienttranslate('Complete quest ${token_name}? This cannot be undone');
    }

    function getExtraArgs() {
        return ["token_name" => $this->getCardToGain()];
    }

    public function getPossibleMoves() {
        $cardId = $this->getCardToGain();
        if (!$cardId) {
            return ["confirm"];
        }
        return [$cardId => ["q" => 0, "name" => clienttranslate("Complete quest")]];
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        if ($cardId === "confirm") {
            return;
        }
        $this->effect_gainEquipment($this->game->tokens->getTokenInfo($cardId));
    }

    function requireConfirmation() {
        return $this->getCardToGain() !== null;
    }

    #[Override]
    public function canSkip() {
        if (!$this->noValidTargets()) {
            return false; // mandatory if possible
        }
        return parent::canSkip();
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
                    $this->game->effect_clearCrystals($existingId, $heroId);
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

        // Sweep any crystals parented to the card back to supply before it lands on
        // the tableau — accumulated quest progress has no meaning once the equip is claimed.
        $this->game->effect_clearCrystals($cardId, $heroId);

        $this->dbSetTokenLocation($cardId, "tableau_$owner", 0, clienttranslate('${char_name} gains ${token_name}'), [
            "char_name" => $heroId,
        ]);
        // gainEquip is only reached from quest rewards
        $this->game->incPlayerStat($owner, "game_quests_completed");

        // Reveal the new top of the deck so the client can render it.
        $newTop = $this->game->tokens->getTokenOnTop("deck_equip_$owner");
        if ($newTop !== null) {
            $this->dbSetTokenLocation(
                $newTop["key"],
                "deck_equip_$owner",
                null, // preserve state so it keep top status
                clienttranslate('${char_name} starts new quest for ${token_name}'),
                ["char_name" => $heroId]
            );
        }

        $cardObj = $this->game->instantiateCard($card, $this);
        $cardObj->onTrigger(Trigger::CardEnter);
        // equip changes attributes
        $this->game->getHero($owner)->recalcTrackers();
        $this->game->customUndoSavepoint($this->getPlayerId(), 1, "quest");
    }
}
