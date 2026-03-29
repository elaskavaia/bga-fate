<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * endOfAttack: Cleanup after the attack pipeline completes.
 *
 * Moves marker_attack back to limbo so it only exists on the map during an attack.
 * Automated — no user interaction.
 */
class Op_endOfAttack extends Operation {
    function resolve(): void {
        $this->game->tokens->dbSetTokenLocation("marker_attack", "limbo", 0, "");
    }
}
