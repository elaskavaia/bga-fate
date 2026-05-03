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

use Bga\Games\Fate\OpCommon\Operation;

/**
 * blockXp: suppress the XP award (base + bonus yellow markers) for the kill
 * currently being processed. Used inside TMonsterKilled handler chains for
 * "take this card INSTEAD of the gold" quests:
 *
 *   killed('rank>=3'):?(blockXp:gainEquip)               // Quiver
 *   killed('brute or skeleton'):?(blockXp:gainEquip)     // Helmet
 *
 * No params — the targeted kill is whatever marker_attack points at, the
 * same monster `Op_killed` filtered. Identifies the matching Op_finishKill
 * by its `target` data field and patches `noXp = true` onto its data via
 * OpMachine::updateData. Op_finishKill reads the flag at resolve time and
 * passes it to Monster::finalizeDamage, which skips both base + bonus XP.
 *
 * No-op (silent) if there's no kill in flight or no matching finishKill —
 * this can happen if the chain runs outside a TMonsterKilled context, which
 * is a card-author footgun rather than a system invariant.
 */
class Op_blockXp extends Operation {
    function getPossibleMoves() {
        return ["confirm"];
    }

    function resolve(): void {
        $killedHex = $this->game->getAttackHex();
        if ($killedHex === null) {
            return;
        }
        $killedId = $this->game->hexMap->getCharacterOnHex($killedHex);
        if ($killedId === null) {
            return;
        }

        $finishKillRow = $this->game->machine->findOperation(null, "finishKill", function ($row) use ($killedId) {
            $op = $this->game->machine->instantiateOperationFromDbRow($row);
            return $op->getDataField("target") === $killedId;
        });
        if ($finishKillRow === null) {
            return;
        }

        $op = $this->game->machine->instantiateOperationFromDbRow($finishKillRow);
        $op->withDataField("noXp", true);
        $this->game->machine->db->updateData($op->getId(), $op->getDataForDb());
    }
}
