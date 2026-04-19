<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * endOfAttack: Cleanup after the attack pipeline completes and trigger events.
 *
 */
class Op_endOfAttack extends Operation {
    function resolve(): void {
        $trigger = Trigger::AfterActionAttack;
        $this->queueTrigger($trigger);
        $this->game->tokens->dbSetTokenLocation("marker_attack", "limbo", 0, "");
    }
}
