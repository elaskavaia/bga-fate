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

use Bga\Games\Fate\OpCommon\CountableOperation;

/**
 * gainXp: Gain X gold/XP (move yellow crystals from supply to player tableau).
 * Count = amount of XP to gain (default 1).
 * Automated operation — no user choice needed.
 *
 * Rules: "Throughout the game, you will collect gold and experience (yellow),
 * which are treated as the same resource."
 *
 * Location preconditions are expressed via `in(Location):` (Op_in) or `adj(Terrain):`
 * (Op_adj) prefixes in the rule, not via a param on this op.
 *
 * Used by: Miner (adj(mountain):2gainXp), Popular (in(Grimheim):2gainXp), Discipline.
 */
class Op_gainXp extends CountableOperation {
    function resolve(): void {
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        $amount = (int) $this->getCount();
        $this->game->effect_moveCrystals($heroId, "yellow", $amount, "tableau_$owner");
    }

    public function isTrivial(): bool {
        return true;
    }
}
