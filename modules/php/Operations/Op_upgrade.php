<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

use function Bga\Games\Fate\getPart;

/**
 * upgrade: End-of-turn upgrade - spend XP for a new ability or card improvement.
 *
 * Rules:
 * - Pay the experience (yellow) printed on the next step of your upgrade cost track,
 *   then move the upgrade cost marker there.
 * - Choose: A) Gain a new ability from the ability pile, or B) Flip an existing card to Level II.
 * - A player cannot upgrade more than once per turn.
 * - If you choose an ability that generates mana, mana is added after upgrading.
 * - When you reach the red square on the upgrade track, all future upgrades cost 10.
 *
 * Behaviour:
 * - getPossibleMoves() returns: top card of ability deck (gain) + flippable L1 cards on tableau (improve)
 * - Auto-skips if not enough XP or no valid targets
 * - End-of-turn mana generation (step 3 of the End-of-Turn sequence) runs here, after the upgrade
 *   resolves or is skipped, so a gained/improved card generates its mana the same turn.
 *
 * Used by: Op_turnEnd (queued at end of each turn)
 */
class Op_upgrade extends Operation {
    private function getUpgradeCost(): int {
        $owner = $this->getOwner();
        return (int) $this->game->tokens->getTokenState("marker_{$owner}_3");
    }

    private static function getLevel2Id(string $cardId): string {
        $parts = explode("_", $cardId);
        $last = (int) end($parts);
        if ($last % 2 === 0) {
            return $cardId; // already Level II
        }
        $parts[count($parts) - 1] = $last + 1;
        return implode("_", $parts);
    }

    private function getFlippableCards(): array {
        $owner = $this->getOwner();
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card_hero", "tableau_$owner");
        $cards += $this->game->tokens->getTokensOfTypeInLocation("card_ability", "tableau_$owner");
        $flippable = [];
        foreach ($cards as $cardId => $info) {
            $level2Id = self::getLevel2Id($cardId);
            if ($cardId != $level2Id) {
                $flippable[$cardId] = $level2Id;
            }
        }
        return $flippable;
    }

    function getPrompt() {
        return clienttranslate('Upgrade: choose a new ability or a card to improve (cost ${cost} XP)');
    }

    function getPossibleMoves() {
        $owner = $this->getOwner();
        $cost = $this->getUpgradeCost();
        $xp = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_$owner"));
        if ($xp < $cost) {
            return ["q" => Material::ERR_COST];
        }

        $result = [];
        // Flippable cards on tableau = improve
        foreach ($this->getFlippableCards() as $cardId => $level2Id) {
            $result[$cardId] = ["q" => Material::RET_OK, "tokenIdUi" => $level2Id];
        }
        // Top card of ability deck = gain new ability
        $topCard = $this->game->tokens->getTokenOnTop("deck_ability_$owner");
        if ($topCard !== null) {
            $key = $topCard["key"];
            $result[$key] = ["q" => Material::RET_OK, "tokenIdUi" => $key];
        }

        return $result;
    }

    function resolve(): void {
        $target = $this->getCheckedArg();
        $owner = $this->getOwner();
        $cost = $this->getUpgradeCost();

        // Pay XP
        $this->instantiateOperation("{$cost}spendXp")->resolve(); // instant resolve

        // Advance upgrade cost marker
        $markerId = "marker_{$owner}_3";
        $newCost = min($cost + 1, 10);
        $this->dbSetTokenState($markerId, $newCost, "", ["nod" => true]);

        $targetLoc = $this->game->tokens->getTokenLocation($target);
        if (str_starts_with($targetLoc, "deck_ability_")) {
            $this->resolveGain($target, $owner);
        } else {
            $this->resolveImprove($target, $owner);
        }
        // Generate after upgrading so a gained/improved card produces its mana this same turn.
        $this->generateTurnMana($owner);
        $this->game->getHero($owner)->recalcTrackers();
    }

    function skip() {
        // No upgrade taken, but end-of-turn mana generation still happens.
        $this->generateTurnMana($this->getOwner());
    }

    /** End-of-turn mana generation: each tableau card with a mana icon adds that many green crystals. */
    private function generateTurnMana(string $owner): void {
        $hero = $this->game->getHero($owner);
        foreach ($hero->getTableauCards() as $card) {
            $manaGen = (int) $this->game->material->getRulesFor($card["key"], "mana", 0);
            if ($manaGen > 0) {
                $hero->moveCrystals("green", $manaGen, $card["key"], [
                    "message" => clienttranslate('${char_name} adds ${count} [MANA] onto ${place_name}'),
                ]);
            }
        }
    }

    private function resolveGain(string $cardId, string $owner): void {
        // Move card from deck to tableau
        $heroId = $this->game->getHeroTokenId($owner);
        $this->dbSetTokenLocation($cardId, "tableau_$owner", 0, clienttranslate('${char_name} gains a new ability: ${token_name}'), [
            "char_name" => $heroId,
        ]);

        // Mana for the new card is generated by generateTurnMana() after this returns.

        // Reveal the new top of the ability deck so the client can render it.
        $newTop = $this->game->tokens->getTokenOnTop("deck_ability_$owner");
        if ($newTop !== null) {
            $this->dbSetTokenLocation($newTop["key"], "deck_ability_$owner", (int) $newTop["state"], "");
        }
    }

    private function resolveImprove(string $cardId, string $owner): void {
        $level2Id = $this->getArgsInfo()[$cardId]["tokenIdUi"];
        $heroId = $this->game->getHeroTokenId($owner);

        $suppress = ["noa" => true];
        // Move L1 to limbo (suppress slide - flip animation runs on L2 below)
        $this->dbSetTokenLocation($cardId, "limbo", 0, "", $suppress);

        // Move L2 to tableau; client plays a 3D flip from L1's sprite to L2's at this slot
        $this->dbSetTokenLocation(
            $level2Id,
            "tableau_$owner",
            0,
            clienttranslate('${char_name} upgrades to ${token_name}'),
            [
                "char_name" => $heroId,
                "flip_from" => $cardId,
            ] + $suppress
        );

        // Transfer everything
        $tokens = $this->game->tokens->getTokensOfTypeInLocation(null, $cardId);
        if (count($tokens) > 0) {
            $this->dbSetTokensLocation($tokens, $level2Id, 0, "", $suppress);
        }
    }

    function canSkip() {
        return true;
    }

    function getUiArgs() {
        return ["buttons" => false];
    }

    function getExtraArgs() {
        return ["cost" => $this->getUpgradeCost()];
    }
}
