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
 * spawn(<type>): place N monster tokens of the given type from supply_monster
 * onto free hexes adjacent to the acting hero. The player chooses each hex
 * (RULES.md "Ambush": heroes place the spawned monster themselves). Placing one
 * monster re-queues the remainder so the player picks each hex in turn.
 * Mandatory while a free adjacent hex exists; auto-skips when the supply pool
 * or the free-hex ring is exhausted (silent partial spawn).
 *
 * Used by quest side-effects:
 *   killed(trollkin):2spawn(brute):...      // Leather Purse
 *   in(TrollCaves):spawn(troll):gainEquip   // Elven Arrows
 * and by Op_monsterDieAmbush (spawn(goblin) per hero).
 *
 * Params:
 *  - count (Countable prefix): number of monsters to spawn (default 1)
 *  - param(0): monster type stem, e.g. "brute" -> matches monster_brute_*
 */
class Op_spawn extends CountableOperation {
    private function getMonsterType(): string {
        $type = $this->getParam(0, "");
        $this->game->systemAssert("ERR:spawn:noType", $type !== "");
        return $type;
    }

    private function supplyHasMonster(string $type): bool {
        return count($this->game->tokens->getTokensOfTypeInLocation("monster_$type", "supply_monster")) > 0;
    }

    private function getFreeAdjacentHexes(): array {
        $heroId = $this->game->getHeroTokenId($this->getOwner());
        $heroHex = $this->game->hexMap->getCharacterHex($heroId);
        $this->game->systemAssert("ERR:spawn:noHeroHex:$heroId", $heroHex !== null);
        $hexes = [];
        foreach ($this->game->hexMap->getAdjacentHexes($heroHex) as $hex) {
            // Grimheim is off-limits for adjacent spawns (moving/pushing a monster INTO Grimheim is a
            // separate, designer-allowed path - see R.22.6 - and is not routed through here).
            if ($this->game->hexMap->getCharacterOnHex($hex) === null && !$this->game->hexMap->isInGrimheim($hex)) {
                $hexes[$hex] = ["q" => Material::RET_OK];
            }
        }
        return $hexes;
    }

    function getPrompt() {
        return clienttranslate("Choose an adjacent area to place the spawned monster");
    }

    function getPossibleMoves(): array {
        $type = $this->getParam(0, "");
        if ($type === "" || !$this->supplyHasMonster($type)) {
            return []; // no type (generic probe) or supply exhausted - nothing to place
        }
        return $this->getFreeAdjacentHexes();
    }

    function resolve(): void {
        $type = $this->getMonsterType();
        $hex = $this->getCheckedArg();
        $heroId = $this->game->getHeroTokenId($this->getOwner());

        $supply = $this->game->tokens->getTokensOfTypeInLocation("monster_$type", "supply_monster");
        $monsterId = array_key_first($supply);
        $this->game->systemAssert("ERR:spawn:noSupply:$type", $monsterId !== null);

        $this->game->getMonster($monsterId)->moveTo($hex, clienttranslate('${char_name} spawned adjacent to ${char_name2}'), [
            "char_name2" => $heroId,
        ]);

        $remaining = (int) $this->getCount() - 1;
        if ($remaining > 0) {
            $this->queue("{$remaining}spawn($type)");
        }
    }

    public function canSkip() {
        // Placement is mandatory while a free adjacent hex exists; an exhausted supply or
        // fully-blocked ring (no valid targets) must skip silently so it never hangs the
        // machine -- e.g. a surrounded hero on Ambush, or a multi-spawn whose ring fills mid-chain.
        return $this->isOptional() || $this->noValidTargets();
    }

    public function isTrivial(): bool {
        return $this->isOneChoice();
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
