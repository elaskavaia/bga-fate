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

use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

use function Bga\Games\Fate\getPart;

/**
 * c_wrecking: Wrecking Ball pendulum loop. Drives an interactive iterative move
 * action where Boldur may step into occupied hexes, deal 1 damage to the
 * occupant, and shove them into any adjacent valid hex (including the one
 * Boldur just came from — the "pendulum swap").
 *
 * Used by:
 *   - card_ability_4_7 Wrecking Ball I
 *   - card_ability_4_8 Wrecking Ball II (also passive +1 move)
 *
 * Wired in via Op_move::getPossibleMoves (Orebiter pattern): when the card is
 * on the tableau and at least one adjacent hex is occupied, the card id is
 * offered as an extra move target. Picking it dispatches here.
 *
 * Two phases per iteration, gated by whether the `displaced` data field is set:
 *   - displaced unset: pick next destination hex (any non-impassable adjacent hex)
 *   - displaced set:   pick where the displaced character goes (any hex it can enter)
 *
 * Re-queues itself with budget-1 between iterations until budget hits 0 or no
 * valid targets remain. On exit, emits Trigger::ActionMove.
 *
 * Designer rule clarifications (DESIGN.md §"Wrecking Ball"):
 *   - May push the displaced character into the hex Boldur just came from
 *     (swap places — enables the pendulum).
 *   - "Character" includes both monsters and heroes.
 *   - Cannot push a character out of Grimheim (Grimheim isn't an "occupied area").
 */
class Op_c_wrecking extends Operation {
    private function getDisplaced(): string {
        return (string) $this->getDataField("displaced", "");
    }

    private function getBudget(): int {
        return (int) $this->getDataField("budget", 0);
    }

    function getPrompt() {
        if ($this->getDisplaced() !== "") {
            return clienttranslate('Choose where to push ${char2_name}');
        }
        return clienttranslate('Wrecking Ball: choose where to move (${count} step(s) left)');
    }

    function getExtraArgs() {
        $displaced = $this->getDisplaced();
        if ($displaced !== "") {
            return ["char2_name" => $displaced];
        }
        return ["count" => $this->getBudget()];
    }

    function getPossibleMoves() {
        $hero = $this->game->getHero($this->getOwner());
        $boldurHex = $hero->getHex();
        $this->game->systemAssert("ERR:c_wrecking:noHeroHex:" . $this->getOwner(), $boldurHex !== null);

        $displaced = $this->getDisplaced();
        if ($displaced) {
            // push phase — must always pick a destination (no early-stop here)
            $charType = getPart($displaced, 0);
            $targets = [];
            foreach ($this->game->hexMap->getAdjacentHexes($boldurHex) as $hex) {
                if ($this->game->hexMap->canEnterHex($hex, $charType)) {
                    $targets[] = $hex;
                }
            }
            return $targets;
        }

        // Destination phase: list adjacent hexes plus an "endOfMove" sentinel
        // so the player can stop the pendulum early.
        $targets = ["endOfMove" => ["q" => 0, clienttranslate("End Move")]];
        if ($this->getBudget() > 0) {
            foreach ($this->game->hexMap->getAdjacentHexes($boldurHex) as $hex) {
                if ($this->game->hexMap->isImpassable($hex, "hero")) {
                    continue;
                }
                $targets[] = $hex;
            }
        }
        return $targets;
    }

    function resolve(): void {
        $displaced = $this->getDisplaced();

        if ($displaced) {
            $pushHex = $this->getCheckedArg();
            $attackerId = $this->game->getHeroTokenId($this->getOwner());
            $character = $this->game->getCharacter($displaced);

            // Push first so the damage pipeline sees a single-occupant hex.
            $character->moveTo($pushHex, clienttranslate('${char_name} pushes ${char2_name} with Wrecking Ball'), [
                "char_name" => $attackerId,
                "char2_name" => $displaced,
            ]);

            // Run damage through the proper pipeline (cover, armor, damage effects, kill trigger).
            $this->queue("dealDamage", null, [
                "attacker" => $attackerId,
                "target" => $pushHex,
                "count" => 1,
            ]);

            // Return to destination phase. Budget was already decremented when the
            // step into the occupied hex was queued.
            $this->queue("c_wrecking", null, ["budget" => $this->getBudget()]);
            return;
        }

        // Destination phase
        $arg = $this->getCheckedArg();
        if ($arg === "endOfMove") {
            $trigger = $this->getReason() == "Op_actionMove" ? Trigger::ActionMove : Trigger::Move;
            $this->queueTrigger($trigger);
            return;
        }

        $destHex = $arg;
        $occupant = $this->game->hexMap->getCharacterOnHex($destHex);

        // Delegate the actual move + trigger emission to Op_step. Always queue
        // it as a non-final step (TStep); the player ends the action explicitly
        // via the "endOfMove" sentinel, which fires TActionMove.
        $this->queue("step", null, [
            "hex" => $destHex,
            "final" => false,
            "reason" => $this->getReason(),
        ]);

        $newBudget = $this->getBudget() - 1;
        if ($occupant !== null) {
            $this->queue("c_wrecking", null, [
                "displaced" => $occupant,
                "budget" => $newBudget,
                "reason" => $this->getReason(),
            ]);
        } else {
            $this->queue("c_wrecking", null, ["budget" => $newBudget, "reason" => $this->getReason()]);
        }
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }
}
