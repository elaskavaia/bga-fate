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
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Demote the top card of an equipment or ability pile to the bottom.
 *
 * RULES.md §End-of-Turn step 5:
 *   "You may put the top equipment on the bottom of the equipment pile OR
 *    put the top ability on the bottom of the ability pile.
 *    You may not look at the next card beforehand."
 *
 * Queued automatically by Op_turnEnd. Optional (skippable). Surfaces both
 * deck-tops as clickable targets; the player picks one (or skips). On accept,
 * moves the chosen card to the bottom of its pile, sweeps any accumulated
 * quest progress (red crystals) back to supply, and reveals the new top.
 */
class Op_demote extends Operation {
    private const DECKS = ["deck_equip_", "deck_ability_"];

    public function canSkip(): bool {
        return true;
    }

    public function getPrompt() {
        return clienttranslate("You may demote the top equipment or ability to the bottom of its pile");
    }

    public function getPossibleMoves() {
        $owner = $this->getOwner();
        $targets = [];
        foreach (self::DECKS as $prefix) {
            $top = $this->game->tokens->getTokenOnTop($prefix . $owner);
            if ($top !== null) {
                $targets[$top["key"]] = ["q" => Material::RET_OK];
            }
        }
        return $targets;
    }

    public function resolve(): void {
        $cardId = $this->getCheckedArg();
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);

        $deck = $this->game->tokens->getTokenInfo($cardId)["location"];
        $bottomState = ((int) $this->game->tokens->getExtremePosition(false, $deck)) - 1;

        $this->dbSetTokenLocation(
            $cardId,
            $deck,
            $bottomState,
            clienttranslate('${char_name} demotes ${token_name} to the bottom of the pile'),
            ["char_name" => $heroId]
        );

        // Sweep accumulated quest progress (no-op for ability cards — they have no crystals).
        $this->game->effect_clearCrystals($cardId, $heroId);

        // Reveal new top so the client can render it.
        $newTop = $this->game->tokens->getTokenOnTop($deck);
        if ($newTop !== null) {
            $this->dbSetTokenLocation(
                $newTop["key"],
                $deck,
                null, // preserve state so it keeps top status
                clienttranslate('${char_name} reveals new ${token_name}'),
                ["char_name" => $heroId]
            );
        }
    }
}
