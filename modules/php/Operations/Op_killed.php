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
 * killed(filter): gate predicate for TMonsterKilled handler chains. Voids
 * (ERR_PREREQ) when the just-killed monster doesn't match the filter.
 *
 * Used inside `quest_r` / `r` expressions wired to `on=TMonsterKilled` cards
 * to scope the chain to specific kills:
 *
 *   killed(trollkin):gainEquip,2spawn(brute,adj)     // Leather Purse
 *   killed('rank>=3'):gainEquip:blockXp              // Quiver
 *   killed('rank==3 or legend'):gainTracker          // hypothetical
 *
 * Reads the killed monster id from `marker_attack`'s location: Op_applyDamage
 * places the marker on the dying monster's hex BEFORE queueing TMonsterKilled
 * + Op_finishKill, so the monster is still on the hex when the trigger
 * dispatches and we can find it via getCharacterOnHex.
 *
 * Filter: param(0) is passed to evaluateExpression with the monster id as
 * context, so it can use `trollkin`/`firehorde`/`dead` (faction predicates),
 * `legend`/`not_legend`, `rank`, `healthRem`, etc. — same vocabulary as
 * `dealDamage(adj, 'rank==3 or legend')`. Bare keyword forms work (e.g.
 * `killed(trollkin)`) thanks to evaluateTerm's existing keyword handling.
 *
 * Like `Op_in`, this gate must be the leftmost element of its chain so the
 * void state propagates before any sub-op runs.
 */
class Op_killed extends Operation {
    private function getKilledMonster(): ?string {
        $killedHex = $this->game->getAttackHex();
        if ($killedHex === null) {
            return null;
        }
        $charId = $this->game->hexMap->getCharacterOnHex($killedHex);
        if ($charId === null || !str_starts_with($charId, "monster_")) {
            return null;
        }
        return $charId;
    }

    function getPossibleMoves() {
        $monster = $this->getKilledMonster();
        if ($monster === null) {
            return ["q" => Material::ERR_PREREQ, "err" => "No killed monster on attack hex"];
        }
        $filter = $this->getParam(0, "");
        if ($filter === "") {
            return parent::getPossibleMoves(); // a monster killed
        }
        $matches = !!$this->game->evaluateExpression($filter, $this->getOwner(), $monster);
        if (!$matches) {
            return ["q" => Material::ERR_PREREQ, "err" => "Killed monster does not match filter"];
        }
        return parent::getPossibleMoves();
    }

    function resolve(): void {
        // gate-only: passing the predicate is its own effect
    }
}
