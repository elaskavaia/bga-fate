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

/**
 * spendManaAny: Remove X mana (green crystals) from tableau cards, one mana at a time.
 * Unlike spendMana (which requires all X from the same card), the player may pick each
 * crystal from a different card.
 *
 * Count = total amount of mana to spend. The op prompts for a source card, spends 1 mana,
 * then re-queues itself with (count - 1) if more mana is still due.
 *
 * Location preconditions are expressed via `in(Grimheim):` prefix in the rule.
 *
 * Used by: Inspire Defense (in(Grimheim):2spendManaAny:addTownPiece) and other event cards
 * whose rules require spending mana but have no specific source card context.
 */
class Op_spendManaAny extends Op_spendMana {
    function getPrompt() {
        return clienttranslate("Choose a card to spend 1 mana from");
    }

    function getPossibleMoves() {
        $hero = $this->game->getHero($this->getOwner());
        $targets = [];
        foreach ($hero->getTableauCards() as $card) {
            $cardId = $card["key"];
            $mana = count($this->game->tokens->getTokensOfTypeInLocation("crystal_green", $cardId));
            if ($mana > 0) {
                $targets[$cardId] = ["q" => Material::RET_OK];
            }
        }
        if (empty($targets)) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No mana available to spend")];
        }
        return $targets;
    }

    function resolve(): void {
        $cardId = $this->getCheckedArg();
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $this->game->effect_moveCrystals($heroId, "green", -1, $cardId, [
            "message" => clienttranslate('${char_name} spends 1 mana from ${place_name}'),
        ]);
        $remaining = (int) $this->getCount() - 1;
        if ($remaining > 0) {
            $this->withDataField("count", $remaining);
            $this->withDataField("mcount", $remaining);
            $this->queueOp($this);
        }
    }
}
