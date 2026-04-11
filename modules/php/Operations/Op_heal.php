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
use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * Heal: Remove X damage (red crystals) from target hero.
 * Count = amount of damage to remove.
 * Target is determined by param: "self" = acting hero, "adj" = adjacent hero (including self).
 * Used by: Rest (2heal(self)), Stitching (1heal(adj)), Belt of Youth, etc.
 */
class Op_heal extends CountableOperation {
    function getPrompt() {
        return clienttranslate("Choose a hero to heal");
    }

    private function getHeroCandidates(): array {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $target = $this->getParam(0, "self");
        if ($target === "self") {
            return [$heroId];
        }
        // adj: all heroes on same or adjacent hexes
        $heroHex = $this->game->hexMap->getCharacterHex($heroId);
        $hexes = array_merge([$heroHex], $this->game->hexMap->getHexesInRange($heroHex, 1));
        $candidates = [];
        foreach ($hexes as $hex) {
            $otherHeroId = $this->game->hexMap->getCharacterOnHex($hex, "hero");
            if ($otherHeroId) {
                $candidates[] = $otherHeroId;
            }
        }
        return $candidates;
    }

    function getPossibleMoves() {
        $presetTarget = $this->getDataField("target");
        if ($presetTarget) {
            return [$presetTarget];
        }
        $targets = [];
        foreach ($this->getHeroCandidates() as $heroId) {
            $hex = $this->game->hexMap->getCharacterHex($heroId);
            $this->game->systemAssert("ERR:heal:noHex:$heroId", $hex !== null);
            $damage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $heroId));
            $targets[$hex] =
                $damage > 0
                    ? ["q" => Material::RET_OK]
                    : ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No damage to heal")];
        }
        return $targets;
    }

    function resolve(): void {
        $hexId = $this->getCheckedArg();
        $heroId = $this->game->hexMap->getCharacterOnHex($hexId, "hero");
        $this->game->systemAssert("ERR:heal:noHeroOnHex:$hexId", $heroId !== null);
        $actingHeroId = $this->game->getHeroTokenId($this->getOwner());
        $amount = $this->getCount();
        $currentDamage = count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $heroId));
        $amount = min($amount, $currentDamage);

        $this->game->effect_moveCrystals($actingHeroId, "red", -$amount, $heroId, [
            "message" => clienttranslate('${char_name} heals ${count} damage from ${token_name}'),
            "token_name" => $heroId,
        ]);
    }
}
