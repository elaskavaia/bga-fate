<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Game;
use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

use function Bga\Games\Fate\getPart;

/**
 * Main turn operation - player selects a die, worker, or rest action
 */
class Op_turn extends Operation {
    public function auto(): bool {
        if ($this->getPlayerId() == Game::PLAYER_AUTOMA) {
            $this->queue("ai_turn", $this->game->getAutomaColor());
            return true;
        }

        $this->game->switchActivePlayer($this->getPlayerId(), true);
        $this->game->customUndoSavepoint($this->getPlayerId(), 1);

        // Reset the influence usage flags for this player's turn
        $owner = $this->getOwner();
        $this->game->tokens->db->setTokenState("used_inf_blue_$owner", 0);
        $this->game->tokens->db->setTokenState("used_inf_yellow_$owner", 0);
        $this->game->tokens->db->setTokenState("used_inf_black_$owner", 0);

        return parent::auto();
    }

    /**
     * Get dice in player's supply (tableau)
     */
    function getDiceInPlayerSupply(): array {
        $owner = $this->getOwner();
        return $this->game->tokens->getTokensOfTypeInLocation("dice", "tableau_$owner");
    }

    /**
     * Get workers in player's supply (on their tableau)
     */
    function getWorkersInSupply(): array {
        $owner = $this->getOwner();
        return $this->game->tokens->getTokensOfTypeInLocation("worker", "tableau_$owner");
    }

    /**
     * Check if blue influence has already been spent this turn
     */
    function isBlueInfluenceSpentThisTurn(): bool {
        $owner = $this->getOwner();
        $flagToken = "used_inf_blue_$owner";
        return $this->game->tokens->db->getTokenState($flagToken) > 0;
    }

    public function getPossibleMoves() {
        $res = [];

        // Select a die, worker, or rest

        // Add dice options - group by die value (1-6)
        $player_dice = $this->getDiceInPlayerSupply();
        $diceByValue = [];
        foreach ($player_dice as $dieKey => $dieInfo) {
            $dieValue = (int) $dieInfo["state"];
            if (array_key_exists($dieValue, $diceByValue)) {
                $res[$dieKey] = [
                    "q" => Material::ERR_NOT_APPLICABLE,
                    "err" => clienttranslate("Duplicate die value"),
                ];
            } else {
                $res[$dieKey] = ["q" => Material::RET_OK, "color" => "primary"];
            }
            $diceByValue[$dieValue] = $dieKey;
        }

        // Add worker options - group by color (blue, yellow, green)
        $workers = $this->getWorkersInSupply();
        $workersByColor = [];
        foreach ($workers as $workerKey => $workerInfo) {
            // Worker keys are like worker_blue_1, worker_yellow_2, etc.
            $workersByColor[getPart($workerKey, 1)] = $workerKey;
        }

        // Add one option per unique worker color
        foreach ($workersByColor as $workerKey) {
            $res[$workerKey] = ["q" => Material::RET_OK, "color" => "secondary"];
        }

        /** @var Op_rest */
        $oprest = $this->instanciateOperation("rest");
        if ($oprest->isGoodRest()) {
            $res["rest"] = ["q" => 0, "name" => clienttranslate("Rest") . " [wicon_rest1]", "color" => "secondary"];
        } else {
            $res["rest"] = ["q" => 0, "name" => clienttranslate("Rest"), "color" => "alert"];
        }

        return $res;
    }

    public function getUiArgs() {
        return ["imagebuttons" => true];
    }

    function resolve(): void {
        $owner = $this->getOwner();
        $selected = $this->getCheckedArg();

        if ($selected === "rest") {
            $this->queue("rest");
        } elseif (str_starts_with($selected, "worker_")) {
            $this->queue("placeWorker", $owner, ["worker" => $selected]);
        } elseif (str_starts_with($selected, "dice_")) {
            $this->queue("placeDie", $owner, ["die" => $selected]);
        }
        $this->queue("turnconf");
        $this->game->queueNextTurnOrEnd($this->getPlayerId());
    }

    public function getPrompt() {
        return clienttranslate("Select a die, worker or rest");
    }
}
