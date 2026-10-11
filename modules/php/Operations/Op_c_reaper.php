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
 * c_reaper: Reaper Swing - divide attack damage between primary target and another monster
 * adjacent to the hero (the primary must be adjacent too).
 *
 * Rules (RULES.md): "Cards that refer to 'this attack action' may be played after the dice are
 * rolled to alter the outcome."
 *
 * This op runs as a useCard child during Trigger::ActionAttack, i.e. after Op_roll has rolled
 * the dice but before Op_resolveHits has consumed them. Its only job is to pick the secondary
 * monster and write `secondary` onto the pending Op_resolveHits frame; resolveHits then handles
 * the split-prompt + two dealDamage queues itself (single source of truth for damage routing).
 *
 * Used by:
 *   - card_ability_3_9 Reaper Swing I - "divide damage between target and another adjacent monster"
 *   - card_ability_3_10 Reaper Swing II - same, +3 strength
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

        // Designer ruling (FORUM.md:1194): both targets must be adjacent to Embla
        $adjacent = $this->game->getHero($this->getOwner())->getMonsterHexesInRange(1);
        if (!in_array($primaryHex, $adjacent)) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("Target is not adjacent")];
        }

        $candidates = array_diff($adjacent, [$primaryHex]);
        if (empty($candidates)) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No adjacent monster to share damage with")];
        }
        return array_fill_keys($candidates, ["q" => Material::RET_OK]);
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
        // resolveHits, so at this point resolveHits is still pending in the same frame).
        $resolveHitsOp = $this->game->machine->findOperation($this->getOwner(), "resolveHits");

        $this->game->systemAssert("ERR:c_reaper:noPendingResolveHits", $resolveHitsOp !== null);

        $this->game->machine->setOpDataField($resolveHitsOp, "secondary", $secondaryHex);
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
