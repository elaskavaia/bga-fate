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
use Bga\Games\Fate\Model\Trigger;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Move action: hero moves up to 3 areas (some abilities may change this).
 * Delegates to the move operation -- normally Op_move (one click = path to destination), or
 * Op_moveStep (budgeted step-by-step) when the hero has an active per-step incentive so the
 * player can route deliberately. See DESIGN.md "Step-by-step Move".
 */
class Op_actionMove extends Operation {
    // Boldur's Wrecking Ball runs its own movement loop (Op_c_wrecking via Op_move); leave that
    // path intact rather than overriding it with step mode.
    private const WRECKING_BALL_I = "card_ability_4_7";
    private const WRECKING_BALL_II = "card_ability_4_8";

    // Custom quests whose triggerQuest reacts to TStep but have no declarative quest_on=TStep
    // to read (their step logic lives in a bespoke override). Add new ones here.
    private const STEP_QUEST_CARDS = ["card_equip_4_16"]; // Shield - "enter Ogre Valley" branch

    function getNumberOfMoves(): int {
        $hero = $this->game->getHero($this->getOwner());
        return $hero->getNumberOfMoves();
    }

    /** [delegateType, extraData] for the move delegate, picking step mode when it helps. */
    function getDelegateInfo(): array {
        $steps = $this->getNumberOfMoves();
        if ($this->hasStepIncentive()) {
            return ["moveStep", ["budget" => $steps, "moved" => 0]];
        }
        return ["[1,{$steps}]move", []];
    }

    function getPossibleMoves(): array {
        $target = $this->getDataField("target", "");
        if ($target) {
            return [$target];
        }
        // If the hero's move tracker has been reduced to 0 (e.g. Seek Shelter played this turn),
        // the move action is not available. Return an error so the turn op filters it out
        // rather than constructing an invalid "[1,0]move" delegate.
        if ($this->getNumberOfMoves() <= 0) {
            return ["q" => Material::ERR_NOT_APPLICABLE, "err" => clienttranslate("No moves remaining this turn")];
        }
        [$type, $data] = $this->getDelegateInfo();
        return $this->getPossibleMovesDelegate($type, $data);
    }

    function resolve(): void {
        // The delegate reads getReason() == "Op_actionMove" and emits Trigger::ActionMove
        // on completion (chains through Trigger::Move). One trigger per move.
        [$type, $data] = $this->getDelegateInfo();
        $data["target"] = $this->getDataField("target", "");
        $this->queue($type, null, $data);
    }

    /**
     * True when a deliberate route matters this move: the active quest fires per step, or a
     * tableau card reacts on each step. Disabled while Wrecking Ball is available (it owns the
     * move loop). See DESIGN.md "Step-by-step Move".
     */
    private function hasStepIncentive(): bool {
        $owner = $this->getOwner();
        $hero = $this->game->getHero($owner);
        if ($hero->heroHasCardsOnTableau(self::WRECKING_BALL_I, self::WRECKING_BALL_II)) {
            return false;
        }
        // Active quest (top of the equipment deck) that advances per step: declarative
        // quest_on=TStep, or a hardcoded custom quest whose step logic is in a bespoke override.
        $top = $this->game->tokens->getTokenOnTop("deck_equip_$owner");
        if ($top !== null) {
            $questOn = (string) $this->game->material->getRulesFor($top["key"], "quest_on", "");
            if ($this->listensOnStep($questOn) || in_array($top["key"], self::STEP_QUEST_CARDS, true)) {
                return true;
            }
        }
        // Tableau card that reacts to each step: a bespoke onStep hook (Treetreader II) or a
        // declarative on=TStep. NOT canTriggerEffectOn(Step) -- that is lenient for on=custom
        // cards (Wrecking Ball, Bloodline Crystal) and would false-positive on them.
        foreach ($hero->getTableauCards() as $card) {
            if (method_exists($this->game->instantiateCard($card, $this), "onStep")) {
                return true;
            }
            if ($this->listensOnStep((string) $this->game->material->getRulesFor($card["key"], "on", ""))) {
                return true;
            }
        }
        return false;
    }

    private function listensOnStep(string $on): bool {
        if ($on === "") {
            return false;
        }
        foreach (Trigger::Step->chain() as $t) {
            if ($on === $t->value) {
                return true;
            }
        }
        return false;
    }

    public function getUiArgs() {
        return ["buttons" => false];
    }

    function getPrompt() {
        return clienttranslate("Select where to move");
    }
}
