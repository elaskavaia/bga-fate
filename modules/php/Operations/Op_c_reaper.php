<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

use function Bga\Games\Fate\getPart;

/**
 * c_reaper: Reaper Swing — divide attack damage between primary target and an adjacent monster.
 *
 * Rules (RULES.md): "Cards that refer to 'this attack action' may be played after the dice are
 * rolled to alter the outcome."
 *
 * This op runs as a useCard child during Trigger::ActionAttack — i.e. after Op_roll has rolled
 * the dice but before Op_resolveHits has consumed them. Its only job is to pick the secondary
 * monster and write `secondary` onto the pending Op_resolveHits frame; resolveHits then handles
 * the split-prompt + two dealDamage queues itself (single source of truth for damage routing).
 *
 * Used by:
 *   - card_ability_3_9 Reaper Swing I  — "divide damage between target and another adjacent monster"
 *   - card_ability_3_10 Reaper Swing II — same, +3 strength
 */
class Op_c_reaper extends Operation {
    function getPrompt() {
        return clienttranslate("Select an adjacent monster to share damage with");
    }

    function getPossibleMoves() {
        $primaryHex = $this->game->getAttackHex();
        if ($primaryHex === null) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Not during an attack")];
        }

        // Any monster on a hex adjacent to the primary target hex (may be out of the
        // hero's reach — same convention as Op_c_nailed).
        $targets = [];
        foreach ($this->game->hexMap->getAdjacentHexes($primaryHex) as $hex) {
            $charId = $this->game->hexMap->getCharacterOnHex($hex);
            if ($charId !== null && getPart($charId, 0) === "monster") {
                $targets[$hex] = ["q" => Material::RET_OK];
            }
        }
        if (empty($targets)) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No adjacent monster to share damage with")];
        }
        return $targets;
    }

    public function canSkip() {
        if ($this->noValidTargets()) {
            return true;
        }
        return parent::canSkip();
    }

    function resolve(): void {
        $secondaryHex = $this->getCheckedArg();

        // Reach into the pending Op_resolveHits and add `secondary`. resolveHits is the
        // next op queued by Op_roll (FIFO within roll's frame: trigger fires first, then
        // resolveHits — so at this point resolveHits is still pending in the same frame).
        $resolveHitsRow = $this->game->machine->findOperation($this->getOwner(), "resolveHits");

        $this->game->systemAssert("ERR:c_reaper:noPendingResolveHits", $resolveHitsRow !== null);

        $op = $this->game->machine->instantiateOperationFromDbRow($resolveHitsRow);
        $op->withDataField("secondary", $secondaryHex);
        $this->game->machine->db->updateData($op->getId(), $op->getDataForDb());
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
