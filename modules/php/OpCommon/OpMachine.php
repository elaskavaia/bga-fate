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

namespace Bga\Games\Fate\OpCommon;

use Bga\GameFramework\SystemException;
use Bga\GameFramework\UserException;
use Bga\Games\Fate\OpCommon\OpExpression;
use Bga\Games\Fate\OpCommon\OpExpressionRanged;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\StateConstants;
use Bga\Games\Fate\Db\DbMachine;
use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\ComplexOperation;
use Bga\Games\Fate\OpCommon\CountableOperation;
use Bga\Games\Fate\OpCommon\UnresolvedOperation;
use Bga\Games\Fate\States\GameDispatchForced;
use Bga\Games\Fate\States\GameDispatch;
use Bga\Games\Fate\States\MultiPlayerMaster;
use Bga\Games\Fate\States\MultiPlayerTurnPrivate;
use Bga\Games\Fate\States\MultiPlayerWaitPrivate;
use Bga\Games\Fate\States\PlayerTurnConfirm;

use Exception;
use ReflectionClass;
use Throwable;

class OpMachine {
    const MA_GAME_DISPATCH_MAX = 1000;
    const GAME_MULTI_COLOR = "000000";
    const GAME_BARIER_COLOR = "";
    protected Game $game;

    public function __construct(public DbMachine $db = new DbMachine()) {
        $this->game = Game::$instance;
    }

    function createTopOperationFromDb($player_id): ?Operation {
        if ($player_id === 0) {
            $owner = null;
        } else {
            $owner = $this->game->custom_getPlayerColorById($player_id);
        }
        return $this->createTopOperationFromDbForOwner($owner);
    }

    function createTopOperationFromDbForOwner(?string $owner = null): ?Operation {
        $ops = $this->db->getTopOperations($owner);
        if (count($ops) == 0) {
            return null;
        }
        $dop = reset($ops);

        return $this->instanciateOperationFromDbRow($dop);
    }

    function instanciateOperationFromDbRow(mixed $dop): Operation {
        if (is_string($dop["data"])) {
            $data = Operation::decodeData($dop["data"]);
        } else {
            $data = $dop["data"];
        }
        $args = $data["args"] ?? [];
        if ($args) {
            unset($data["args"]);
        }
        $op = $this->instanciateOperation($dop["type"], $dop["owner"], $data, $dop["id"] ?? 0);
        if ($op instanceof ComplexOperation) {
            foreach ($args as $sub) {
                $subOp = $this->instanciateOperationFromDbRow(["owner" => $dop["owner"]] + $sub);
                $op->withDelegate($subOp);
            }
        }
        return $op;
    }

    function instanciateOperation(string $type, ?string $owner = null, mixed $data = null, mixed $id = 0): Operation {
        try {
            if ($id) {
                $id = (int) $id;
            } else {
                $id = 0;
            }

            $expr = OpExpression::parseExpression($type);
            $op = $this->exprToOperation($expr, $owner)->withId($id)->withData($data);
            return $op;
        } catch (Exception $e) {
            throw new SystemException("Cannot instantiate '$type': " . $e->getMessage());
        }
    }
    function exprToOperation(OpExpression $expr, ?string $owner) {
        $operand = OpExpression::getop($expr);
        //[op min max arg1 arg2 arg3]...

        if (!$expr->isSimple()) {
            $mnemonic = self::opToMnemonic($operand);
            /** @var ComplexOperation */
            $op = $this->instanciateCommonOperation($mnemonic, $owner);
            foreach ($expr->args as $arg) {
                $sub = $this->exprToOperation($arg, $owner);
                $op->withDelegate($sub);
            }
            $op->withCounts($expr);
            return $op;
        }

        $unrangedType = OpExpression::str($expr->toUnranged());
        $matches = null;
        $params = null;
        if (preg_match("/^(\w+)\((.*)\)$/", $unrangedType, $matches)) {
            // function call
            $params = $matches[2];
            $unrangedType = $matches[1];
        }
        $sub = $this->instanciateSimpleOperation($unrangedType, $owner)->withParams($params);
        if ($expr instanceof OpExpressionRanged) {
            if ($sub instanceof CountableOperation) {
                $sub->withCounts($expr);
                return $sub;
            } else {
                /** @var ComplexOperation */
                $op = $this->instanciateCommonOperation("seq", $owner);
                $op->withDelegate($sub)->withCounts($expr);
                return $op;
            }
        } else {
            return $sub;
        }
    }
    static function opToMnemonic(string $operand) {
        return match ($operand) {
            "!" => "atomic",
            "+" => "order",
            "," => "seq",
            ":" => "paygain",
            ";" => "seq",
            "^" => "unique",
            "/" => "or",
            default => throw new SystemException("Unknown operator $operand"),
        };
    }
    function instanciateCommonOperation(string $type, ?string $owner = null, mixed $data = null): Operation {
        $reflectionClass = new ReflectionClass("Bga\\Games\\Fate\\Operations\\Op_$type");
        $instance = $reflectionClass->newInstance($type, $owner, $data);
        return $instance;
    }

    function instanciateSimpleOperation(string $type, ?string $owner = null, mixed $data = null): Operation {
        if (strlen($type) > 80) {
            throw new SystemException("Cannot instantiate op");
        }

        $operandclass = $this->game->getRulesFor("Op_$type", "class", "Op_$type");

        // Instantiate the class with constructor arguments
        try {
            $reflectionClass = new ReflectionClass("Bga\\Games\\Fate\\Operations\\$operandclass");
            $instance = $reflectionClass->newInstance($type, $owner, $data);
        } catch (Throwable $e) {
            throw new SystemException("Cannot instantiate $type: " . $e->getMessage());
        }

        return $instance;
    }

    function getTopOperations($owner) {
        $ops = $this->db->getTopOperations($owner);
        return $ops;
    }

    function hide(int $id) {
        $this->db->hide($id);
    }

    function interrupt(int $rank = 0, int $count = 1) {
        $this->db->interrupt($rank, $count);
    }

    function push(string $type, ?string $owner = null, mixed $data = null) {
        $this->interrupt();
        return $this->put($type, $owner, $data, 1);
    }

    function queue(string $type, ?string $owner = null, mixed $data = null) {
        $rank = $this->db->getExtremeRank(true);
        $rank++;
        return $this->put($type, $owner, $data, $rank);
    }

    function put(string $type, ?string $owner = null, mixed $data = null, int $rank = 1) {
        if ($owner === null) {
            $owner = "";
        }
        $op = $this->db->createRow($type, $owner, $data);
        return $this->db->insertRow($rank, $op);
    }

    function insertRow(mixed $row, int $rank = 1) {
        return $this->db->insertRow($rank, $row);
    }

    function insert(string $type, ?string $owner = null, mixed $data = null, ?int &$rank = null) {
        if ($rank === null) {
            $rank = 1;
        }
        $this->interrupt($rank);
        $this->put($type, $owner, $data, $rank);
        $rank++;
    }

    function setCounts($opOrRow, mixed $count, mixed $mincount = null): void {
        if ($opOrRow instanceof Operation) {
            $op = $opOrRow;
        } else {
            $op = $this->instanciateOperationFromDbRow($opOrRow);
        }
        $op->withDataField("count", $count);
        if ($mincount === null) {
            $mincount = $count;
        }
        $op->withDataField("mcount", $mincount);
        $this->db->updateData($op->getId(), $op->getDataForDb());
    }

    //DISPATCH

    function dispatchAll(int $n = OpMachine::MA_GAME_DISPATCH_MAX) {
        // dispatch does mulple rounds without switching state, need to watch for notif limit
        for ($i = 0; $i < $n; $i++) {
            if ($this->isMultiplayerOperationMode()) {
                return MultiPlayerMaster::class;
            }
            $state = $this->dispatchOne();
            if ($state && $state !== GameDispatch::class) {
                return $state;
            }
        }
        return PlayerTurnConfirm::class;
    }
    function dispatchOne() {
        //$this->game->debugLog("- SINGLE: machine top: " . toJson(array_values($this->getTopOperations(null))));
        $op = $this->createTopOperationFromDbForOwner(null); // null means any
        if (!$op) {
            return StateConstants::STATE_MACHINE_HALTED;
        }
        // $this->game->debugLog("starting op " . $op->getType());
        return $op->onEnteringGameState();
    }

    /** This only runs when state first entered, after that every action has to run personal dispatch */
    function multiplayerDistpatch() {
        //$this->game->debugLog("multiplayerDistpatch", array_values($this->getAllOperationsMulti()));
        if (!$this->isMultiplayerOperationMode()) {
            // fall out of multiplayer state
            $this->game->gamestate->unsetPrivateStateForAllPlayers();
            return GameDispatchForced::class;
        }
        $this->game->gamestate->setAllPlayersMultiactive();
        $this->game->gamestate->initializePrivateStateForAllActivePlayers();
        return $this->multiplayerDistpatchContinue();
    }

    function multiplayerDistpatchContinue(int $n = OpMachine::MA_GAME_DISPATCH_MAX) {
        //$this->game->debugLog("multiplayerDistpatchContinue");
        $results = [
            OpMachine::GAME_MULTI_COLOR => [
                "instate" => 0,
                "changestate" => 0,
                "hit" => false,
            ],
        ];

        $players = $this->game->loadPlayersBasicInfos();
        foreach ($players as $player_id => $player_info) {
            $pstate = $this->game->getPrivateStateId($player_id);
            $color = $this->game->custom_getPlayerColorById($player_id);
            $results[$color]["instate"] = $pstate;
            $results[$color]["hit"] = false;
            $results[$color]["changestate"] = $pstate;
            $results[$color]["finalstate"] = $pstate;
        }
        $lastId = -1;
        $playersloop = false;
        for ($i = 0; $i < $n; $i++) {
            $operations = $this->getAllOperationsMulti();

            $isMulti = count($operations) > 0;
            if (!$isMulti) {
                // fall out of multiplayer state
                //$this->game->debugLog("- MULTI $i: FALL OUT");
                $this->game->gamestate->unsetPrivateStateForAllPlayers();
                return GameDispatchForced::class;
            }
            $lastId = $this->db->getLastId();

            // reset hit state
            foreach ($players as $player_id => $player_info) {
                $color = $this->game->custom_getPlayerColorById($player_id);
                $results[$color]["hit"] = false;
            }
            $results[OpMachine::GAME_MULTI_COLOR]["hit"] = false;
            $playersloop = true;

            //$this->game->debugLog("- MULTI $i: RESET machine top", $operations);
            while (count($operations) > 0 && $playersloop) {
                $dop = array_shift($operations);
                $op = $this->instanciateOperationFromDbRow($dop);
                $color = $op->getOwner();
                $this->game->systemAssert("not set result", isset($results[$color]));
                $prevresult = &$results[$color];
                if ($prevresult["hit"]) {
                    continue;
                    // $opcode = $prevresult["opcode"] ?? -1;
                    // if ($opcode == $op->getId()) {
                    //     continue;
                    // }
                }
                $prevresult["hit"] = true;
                $prevresult["opcode"] = $op->getId();
                $state = $op->onEnteringGameState();
                $type = $op->getType();
                //$this->game->debugLog("- MULTI $i: switching state $color: $type => $state");
                if ($state === null) {
                    $state = MultiPlayerWaitPrivate::class;
                    $playersloop = false; // have to reset
                }
                if ($state != $prevresult["instate"]) {
                    $prevresult["changestate"] = $state;
                }
                $prevresult["finalstate"] = $state;

                $currLastId = $this->db->getLastId();
                if ($currLastId != $lastId) {
                    $playersloop = false; // have to reset
                }
            }
            if ($playersloop) {
                // we finish dispatch loop without reset
                break;
            }
        }
        if ($i >= OpMachine::MA_GAME_DISPATCH_MAX) {
            return PlayerTurnConfirm::class;
        }
        //$this->game->dbSetPlayerMultiactive(null, 1);

        $changedState = null;
        $keepMultiplayer = 0;
        $deactivate = [];
        foreach ($players as $player_id => $player_info) {
            $color = $player_info["player_color"];
            $this->game->systemAssert("not set result", isset($results[$color]));
            $prevresult = &$results[$color];

            $state = $prevresult["finalstate"];
            //$this->game->debugLog("- MULTI FINAL  $color switching state => $state");
            if ($prevresult["changestate"] != $state) {
                $this->game->gamestate->setPrivateState($player_id, StateConstants::STATE_MULTI_PLAYER_WAIT_PRIVATE);
            }
            if ($state === MultiPlayerTurnPrivate::class || $state == StateConstants::STATE_MULTI_PLAYER_TURN_PRIVATE) {
                $pstate = $prevresult["instate"];
                if ($pstate != StateConstants::STATE_MULTI_PLAYER_TURN_PRIVATE) {
                    $this->game->gamestate->setPrivateState($player_id, StateConstants::STATE_MULTI_PLAYER_TURN_PRIVATE);
                }

                $keepMultiplayer = 1;
            } elseif ($state == MultiPlayerWaitPrivate::class || $state == StateConstants::STATE_MULTI_PLAYER_WAIT_PRIVATE) {
                $this->game->gamestate->setPrivateState($player_id, StateConstants::STATE_MULTI_PLAYER_WAIT_PRIVATE);

                if ($this->game->gamestate->isPlayerActive($player_id)) {
                    $deactivate[] = $player_id;
                }
            } else {
                $changedState = $state;
            }
        }

        // deactivate players
        foreach ($deactivate as $player_id) {
            $this->game->gamestate->setPlayerNonMultiactive($player_id, "loopback");
        }

        //if (!$this->game->gamestate->updateMultiactiveOrNextState($changedState))
        if (count($this->game->gamestate->getActivePlayerList()) == 0) {
            // transition already happen
            return null;
        }
        if ($keepMultiplayer > 0) {
            // not switching yet
            return null;
        }
        if ($changedState) {
            $this->game->gamestate->unsetPrivateStateForAllPlayers();
        }
        return $changedState;
    }
    function multiplayerDistpatchAfterAction(int $player_id, int $n = OpMachine::MA_GAME_DISPATCH_MAX) {
        //$this->game->debugLog("multiplayerDistpatchAfterAction");
        //$this->game->gamestate->setPlayerNonMultiactive($player_id, "loopback");
        $this->game->gamestate->setPrivateState($player_id, StateConstants::STATE_MULTI_PLAYER_WAIT_PRIVATE);
        return $this->multiplayerDistpatchContinue();
    }

    function getAllOperationsMulti() {
        $operations = $this->db->getOperations();
        $result = [];
        while (count($operations) > 0) {
            $op = array_shift($operations);
            if ($op["owner"] === null || $op["owner"] === OpMachine::GAME_BARIER_COLOR) {
                return $result;
            }
            $result[$op["id"]] = $op;
        }
        return [];
    }

    function getAllOperations(?string $owner = null) {
        $operations = $this->db->getOperations();
        $result = [];
        while (count($operations) > 0) {
            $op = array_shift($operations);
            if ($op["owner"] === null || $op["owner"] === $owner) {
                $result[$op["id"]] = $op;
            }
        }
        return $result;
    }

    function findOperation($owner = null, $type = null, ?callable $filter = null) {
        $ops = $this->findOperations($owner, $type, $filter);
        $op = reset($ops);
        return $op;
    }

    function findOperations($owner = null, $type = null, ?callable $filter = null): array {
        $ops = $this->db->getOperations($owner, $type);
        if ($filter !== null) {
            $ops = array_filter($ops, $filter);
        }
        return $ops;
    }

    function isMultiplayerOperationMode() {
        if (!$this->isMultiplayerSupported()) {
            return false;
        }
        return count($this->getAllOperationsMulti()) > 0;
    }

    function isMultiplayerSupported() {
        return false;
    }

    /** Debug functions */

    function gettablearr() {
        return $this->db->gettablearr();
    }

    // STATE FUNCTIONS

    function getArgs(int $player_id) {
        $op = $this->createTopOperationFromDb($player_id);
        if ($op === null) {
            return [];
        }
        return $op->getArgs();
    }

    function onEnteringPlayerState(int $player_id) {
        $op = $this->createTopOperationFromDb($player_id);
        if ($op === null) {
            return GameDispatch::class;
        }
        return $op->onEnteringPlayerState();
    }

    function action_resolve(int $player_id, mixed $data) {
        $op = $this->createTopOperationFromDb($player_id);
        return $op->action_resolve($data);
    }

    function action_skip(int $player_id) {
        $op = $this->createTopOperationFromDb($player_id);
        return $op->action_skip();
    }
    function action_whatever(int $player_id) {
        $op = $this->createTopOperationFromDb($player_id);
        return $op->action_whatever();
    }

    function action_undo(int $player_id, int $move_id = 0) {
        try {
            $this->game->undoRestorePoint();
        } catch (Exception $e) {
            $this->game->userAssert($e->getMessage());
        }
        return GameDispatchForced::class;
    }
}
