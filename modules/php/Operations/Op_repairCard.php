<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
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

/**
 * Remove up to X damage from a target card on the player's tableau.
 * Count = max damage to remove (use 99 for "all").
 *
 * Params:
 * - param(0): "all" — apply to every damaged card on the tableau (no user prompt).
 *   Each damaged card has up to Count damage removed (capped by its current damage).
 *
 * Used by: Durability (99repairCard), Mend in Grimheim (5repairCard), Sewing (1repairCard(all)).
 */
class Op_repairCard extends CountableOperation {
    function getPrompt() {
        return clienttranslate("Choose a card to repair");
    }

    private function isRepairAll(): bool {
        return $this->getParam(0, "") === "all";
    }

    function getPossibleMoves(): array {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget];
        }
        $owner = $this->getOwner();
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$owner");
        $targets = [];
        $total = 0;
        foreach (array_keys($cards) as $cardId) {
            $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
            $total += $damage;
            $targets[$cardId] =
                $damage > 0
                    ? ["q" => Material::RET_OK]
                    : ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No damage to repair")];
        }
        if ($this->isRepairAll()) {
            if ($total > 0) {
                return ["confirm"];
            } else {
                return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No damage to repair")];
            }
        }
        return $targets;
    }

    function resolve(): void {
        $heroId = $this->game->getHeroTokenId($this->getOwner());
        $count = (int) $this->getCount();

        if ($this->isRepairAll()) {
            $owner = $this->getOwner();
            $cards = $this->game->tokens->getTokensOfTypeInLocation("card", "tableau_$owner");
            foreach (array_keys($cards) as $cardId) {
                $this->repairOne($heroId, $cardId, $count);
            }
            return;
        }

        $cardId = $this->getCheckedArg();
        $this->repairOne($heroId, $cardId, $count);
    }

    private function repairOne(string $heroId, string $cardId, int $count): void {
        $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $cardId));
        $amount = min($count, $damage);
        if ($amount > 0) {
            $this->game->effect_moveCrystals($heroId, "red", -$amount, $cardId, [
                "message" => clienttranslate('${char_name} repairs ${token_name}'),
                "token_name" => $cardId,
            ]);
        }
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
