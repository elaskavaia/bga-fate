<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\OpCommon;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\SystemException;
use Bga\GameFramework\UserException;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\Material;
use Bga\Games\Fate\States\GameDispatch;
use Bga\Games\Fate\States\PlayerTurn;
use Bga\Games\Fate\OpCommon\OpExpression;
use Bga\Games\Fate\OpCommon\OpExpressionRanged;
use Bga\Games\Fate\OpCommon\OpParser;
use Bga\Games\Fate\States\MultiPlayerTurnPrivate;
use Exception;

use function Bga\Games\Fate\toJson;

abstract class Operation {
    const ARG_TARGET = "target";
    const TTYPE_TOKEN = "token";
    const TTYPE_TOKEN_ARRAY = "token_array";
    const TTYPE_TOKEN_COUNT = "token_count";
    const TARGET_AUTO = "auto";
    const TARGET_CONFIRM = "confirm";
    protected Game $game;
    protected int $player_id = 0;
    private mixed $data = null;
    private $cachedArgs = null;
    protected $userArgs = null;

    protected $queueRank = 1;

    public function __construct(private string $type, private ?string $owner = null, mixed $data = null, private int $id = 0) {
        $this->game = Game::$instance;

        $this->withData($data);
    }

    function getType() {
        return $this->type;
    }
    final function getOpId() {
        return "Op_" . $this->getType();
    }
    final function getOwner() {
        return $this->owner;
    }
    final function getData() {
        return $this->data;
    }
    function withId(int $id) {
        $this->id = $id;
        return $this;
    }
    function withOwner(string $owner) {
        $this->owner = $owner;
        return $this;
    }
    function withData($data) {
        $xdata = self::decodeData($data);
        if ($this->data === null) {
            $this->data = $xdata;
        } elseif ($xdata) {
            $this->data = array_merge($this->data, $xdata);
        }
        return $this;
    }
    static function decodeData($data) {
        if (is_string($data)) {
            $data = json_decode($data, true, 20, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } elseif (is_numeric($data)) {
            throw new SystemException("Unsupported data format number");
        }
        return $data;
    }
    final function getId() {
        return $this->id;
    }
    final function getPlayerId() {
        if ($this->player_id == 0) {
            $this->player_id = $this->game->custom_getPlayerIdByColor($this->getOwner());
        }

        return $this->player_id;
    }

    function getTypeFullExpr() {
        $params = $this->getParams();
        if ($params) {
            return $this->getType() . "($params)";
        }
        return $this->getType();
    }

    public static function str(mixed $expr, $topop = ";") {
        if ($expr instanceof Operation) {
            $res = $expr->getTypeFullExpr();
            $op = $expr->getOperator();
            if ($op && OpParser::compareOperationRank($topop, $op) > 0) {
                $res = "($res)";
            }
        } else {
            $res = $expr;
        }
        return $res;
    }

    function getOperator() {
        return "!";
    }

    function withDataField(string $field, mixed $value) {
        if ($this->data === null) {
            $this->data = [];
        }
        if ($value === null) {
            unset($this->data[$field]);
        } else {
            $this->data[$field] = $value;
        }
        return $this;
    }
    final function getDataField(string $field, mixed $def = null) {
        if ($this->data === null || !array_key_exists($field, $this->data)) {
            return $def;
        }
        return $this->data[$field];
    }

    function withCounts(OpExpression $expr) {
        if ($expr instanceof OpExpressionRanged) {
            $count = $expr->to;
            $mcount = $expr->from;
            if ($count != 1) {
                $this->withDataField("count", $count);
            }
            if ($mcount != 1) {
                $this->withDataField("mcount", $mcount);
            }
        }
        return $this;
    }

    function withParams(?string $params) {
        return $this->withDataField("params", $params);
    }

    function getParams(): ?string {
        return (string) $this->getDataField("params", null);
    }

    function getParam(int $index = 0, ?string $default = "") {
        $params = $this->getParams();
        if (!$params) {
            return $default;
        }
        $pargs = explode(",", $params);
        return $pargs[$index] ?? $default;
    }

    final function isTrancient() {
        return $this->id <= 0;
    }

    function expandOperation() {
        if ($this->isTrancient()) {
            $this->saveToDb();
            return true;
        }
        return false;
    }

    function getDataForDb() {
        $data = $this->getData() ?? [];
        return $data;
    }

    function saveToDb($rank = 1, bool $interrupt = true) {
        if ($interrupt) {
            $this->game->machine->interrupt($rank);
        }
        $this->game->machine->put($this->getType(), $this->getOwner(), $this->getDataForDb(), $rank);
    }

    function destroy() {
        $id = $this->getId();
        if ($id > 0) {
            $this->game->machine->hide($id);
            $this->withId(0);
        }
        return GameDispatch::class;
    }
    function instanciateOperation($type, $owner = null, $data = null) {
        if ($owner === null) {
            $owner = $this->getOwner();
        }
        if ($data === null) {
            $data = [];
        }
        if (!isset($data["reason"])) {
            $data["reason"] = $this->getOpId();
        }
        return $this->game->machine->instanciateOperation($type, $owner, $data);
    }
    function queue($type, $owner = null, $data = null) {
        $this->game->systemAssert("empty op pushed", $type);
        if ($owner === null) {
            $owner = $this->getOwner();
        }

        if ($data === null) {
            $data = [];
        }
        if (!isset($data["reason"])) {
            $data["reason"] = $this->getOpId();
        }
        $this->game->machine->insert($type, $owner, $data, $this->queueRank);
        //$this->game->debugConsole("queue $type");
    }

    /**
     * wrapper for dbSetTokenLocation to pass proper player that owns the action
     */
    function dbSetTokenLocation(string $tokenId, string $placeId, int|null $state = null, string $notif = "*", mixed $args = []) {
        $this->game->tokens->dbSetTokenLocation($tokenId, $placeId, $state, $notif, $args, $this->getPlayerId());
    }

    function dbSetTokensLocation(array $tokenArr, string $placeId, int|null $state = null, string $notif = "*", mixed $args = []) {
        $this->game->tokens->dbSetTokensLocation($tokenArr, $placeId, $state, $notif, $args, $this->getPlayerId());
    }

    function dbSetTokenState($tokenId, $state = null, $notif = "*", $args = []) {
        $this->game->tokens->dbSetTokenState($tokenId, $state, $notif, $args, $this->getPlayerId());
    }

    protected function getCheckedArg(bool $checkMaxCount = false, bool $checkMinCount = false) {
        if ($this->userArgs === null) {
            throw new SystemException("No user args set");
        }
        $arg = $this->_getCheckedArg();
        $ttype = $this->getArgType();
        if ($ttype == Operation::TTYPE_TOKEN_ARRAY) {
            if (!is_array($arg)) {
                $arg = [$arg];
            }
        } elseif ($ttype == Operation::TTYPE_TOKEN_COUNT) {
            if (!is_array($arg)) {
                $arg = [$arg => 1];
            }
        }
        $total = $this->getUserArgCount($arg);

        if ($checkMaxCount) {
            $args = $this->getArgs();
            $max = $args["count"] ?? $this->getDataField("count", 1);

            $this->game->userAssert(
                clienttranslate("Cannot use this action because superfluous amount of elements is selected"),
                $total <= $max
            );
        }
        if ($checkMinCount) {
            $args = $this->getArgs();
            $min = $args["mcount"] ?? $this->getDataField("mcount", 1);

            $this->game->userAssert(
                clienttranslate("Cannot use this action because insufficient amount of elements is selected"),
                $total >= $min
            );
        }

        return $arg;
    }
    protected function getUserArgCount($arg) {
        $ttype = $this->getArgType();
        $total = 1;
        if ($ttype == Operation::TTYPE_TOKEN_ARRAY) {
            if (!is_array($arg)) {
                $arg = [$arg];
            }
            $total = count($arg);
        } elseif ($ttype == Operation::TTYPE_TOKEN_COUNT) {
            if (!is_array($arg)) {
                $arg = [$arg => 1];
            }
            $total = 0;
            foreach ($arg as $a => $c) {
                $this->game->systemAssert("ERR:getUserArgCount:1" . toJson($arg), is_numeric($c));
                $total += $c;
            }
        }
        return $total;
    }
    protected function _getCheckedArg() {
        $args = $this->userArgs;
        $key = Operation::ARG_TARGET;
        $possible_targets = $this->getArgs()[$key];
        $info = $this->getArgs()["info"];
        //$this->game->userAssert("args " . toJson($args));
        $this->game->systemAssert("ERR:getCheckedArg:1", is_array($possible_targets));
        $this->game->systemAssert("ERR:getCheckedArg:1", is_array($info));

        $ttype = $this->getArgType();
        $target = $args[$key] ?? null;
        if ($target !== null) {
            if (is_array($target)) {
                $multi = $target;
                $res = [];

                if ($ttype == Operation::TTYPE_TOKEN_ARRAY) {
                    foreach ($multi as $target) {
                        $this->checkUserTargetSelection($target, $info[$target] ?? false);
                        $res[] = $target;
                    }
                    return $res;
                } elseif ($ttype == Operation::TTYPE_TOKEN_COUNT) {
                    foreach ($multi as $target => $count) {
                        $this->checkUserTargetSelection($target, $info[$target] ?? false);
                        $res[(string) $target] = (int) $count;
                    }
                    return $res;
                }

                $this->game->systemAssert("Array is passed for $ttype, but it is not supported", false);
            } else {
                if (count($possible_targets) == 1) {
                    return $possible_targets[0];
                }

                $this->checkUserTargetSelection($target, $info[$target] ?? false);
                return $target;
            }
        } else {
            if (count($possible_targets) == 1) {
                return $possible_targets[0];
            }
        }

        $this->game->userAssert(clienttranslate("Operation is not allowed by the rules"));
        return null;
    }
    private function checkUserTargetSelection($target, $info) {
        $this->game->systemAssert("checkUserTargetSelection:01", $info);
        $this->game->systemAssert("checkUserTargetSelection:02", is_array($info));
        $q = $info["q"] ?? null;
        $this->game->systemAssert("checkUserTargetSelection:03", $q !== null);
        if ($q === 0) {
            return $target;
        }
        $err = $info["err"] ?? $this->game->getRulesFor("err_$q", "name", "code $q");
        $this->game->userAssert($err);
    }

    protected function getUncheckedArg($args, $key = Operation::ARG_TARGET, $def = null) {
        $this->userArgs = $args;
        $target = $args[$key] ?? $def;
        return $target;
    }

    /** Get state arguments if we go to player's state */
    function getArgs() {
        if ($this->cachedArgs !== null) {
            return $this->cachedArgs;
        }
        $this->cachedArgs = [];
        $res = &$this->cachedArgs;

        $res["id"] = $this->getId();
        $res["owner"] = $this->getOwner();
        $res["data"] = $this->getData();
        $res["type"] = $this->getType();
        $res["ttype"] = $this->getArgType();

        $movesInfo = $this->getPossibleMoves();
        $this->extractPossibleMoves($res, $movesInfo);
        $res = array_merge($res, $this->getExtraArgs());
        $res["description"] = $this->getDescription();
        $res["prompt"] ??= $this->getPrompt();
        $res["subtitle"] ??= $this->getSubTitle();

        if ($this->canSkip()) {
            $res["info"]["skip"] ??= $this->getSkipArgs();
        }

        $res["ui"] = $this->getUiArgs();

        // cleanup nulls to optimize of data transfer
        foreach ($res as $key => $value) {
            if ($key == Operation::ARG_TARGET) {
                continue;
            }
            if ($value === null || $value === false || $value === "") {
                unset($res[$key]);
                continue;
            }
            if ($value instanceof NotificationMessage) {
                $res[$key] = ["log" => $value->message, "args" => $value->args];
            }
        }

        return $res;
    }

    function getSkipArgs() {
        return [
            "name" => $this->getSkipName(),
            "o" => 1000,
            "sec" => true,
            "q" => 0,
            "color" => "alert",
        ];
    }

    function getSkipName() {
        return clienttranslate("Skip");
    }

    function getIconicName() {
        return $this->getOpName();
    }

    function getOpName() {
        return $this->game->getTokenName($this->getOpId(), $this->getType());
    }
    private function extractPossibleMoves(array &$res, array $details) {
        $targets = [];
        $error = "";
        $errCode = 0;
        foreach ($details as $target => $info) {
            if ($target == "err") {
                // top level error
                $error = $info;
                if ($errCode == 0) {
                    $errCode = Material::ERR_PREREQ;
                }
                unset($details[$target]);
                continue;
            }
            if ($target == "q") {
                // top level error code
                $errCode = (int) $info;
                $error = $this->game->getRulesFor("err_$info", "name", "code $info");
                unset($details[$target]);
                continue;
            }

            if ($target == "prompt") {
                // top level prompt skips
                $res["prompt"] = $info;
                unset($details[$target]);
                continue;
            }

            if (is_array($info)) {
                $q = $info["q"] ?? 0;
                if ($q == 0) {
                    $info["q"] = 0;
                    if ($info["sec"] ?? false) {
                        // secondary targets are not listed in main target list
                        continue;
                    }
                    $targets[] = $target;
                } elseif ($errCode == 0) {
                    // capture first per-entry error as fallback
                    $errCode = (int) $q;
                    $error = $info["err"] ?? $this->game->getRulesFor("err_$q", "name", "?$q");
                }
            } elseif (is_numeric($info) && is_string($target)) {
                // error code
                $details[$target] = ["q" => $info];
                if ($info == 0) {
                    $targets[] = $target;
                } elseif ($errCode == 0) {
                    $errCode = (int) $info;
                    $error = $this->game->getRulesFor("err_$info", "name", "?$info");
                }
            } elseif (is_string($info) && is_numeric($target)) {
                // array value directly
                $targets[] = $info;
                unset($details[$target]);
                $details[$info] = ["q" => 0];
            } else {
                $info = json_encode($info);
                $target = json_encode($target);
                throw new Exception("invalid value $info for $target key");
            }
        }

        if (count($targets) == 0 && !$error) {
            $errCode = Material::ERR_PREREQ;
            $error = $this->getNoValidTargetError();
        }

        $res[Operation::ARG_TARGET] = $targets;
        $res["info"] = $details;
        if (count($targets) > 0) {
            $errCode = 0;
            $error = "";
        }
        $res["err"] = $error ?? null;
        $res["q"] = $errCode;
    }

    function getError(): mixed {
        $arg = $this->getArgs();
        return $arg["err"] ?? "";
    }

    function getErrorCode(): int {
        $arg = $this->getArgs();
        return (int) ($arg["q"] ?? 0);
    }

    function getErrorInfo(): array {
        $q = $this->getErrorCode();
        $err = $this->getError();
        if ($q == 0) {
            return ["q" => $q];
        }
        if ($err) {
            $qerr = $this->game->getRulesFor("err_$q", "name", "?$q");
            if ($qerr == $err) {
                return ["q" => $q];
            }
        }

        return ["q" => $q, "err" => $err];
    }

    function noValidTargets(): bool {
        $args = $this->getArgs();
        return count($args[Operation::ARG_TARGET]) == 0;
    }

    function isOneChoice(): bool {
        $args = $this->getArgs();
        return count($args[Operation::ARG_TARGET]) == 1;
    }

    function getNoValidTargetError(): string {
        return clienttranslate("No valid targets");
    }

    function notifyMessage($message = "", $args = []) {
        $this->game->notify->all("message", $message, ["player_id" => $this->getPlayerId()] + $args);
    }
    // overridable stuff
    function getArgType() {
        return Operation::TTYPE_TOKEN;
    }

    /** If operation require confirmation it will be sent to user and not auto-resolved */
    function requireConfirmation() {
        return false;
    }

    function getUiArgs() {
        return [];
    }
    function getPrompt() {
        return $this->getDescription() ?: $this->getType() . "?";
    }
    function getDescription() {
        return "";
    }
    function getSubTitle() {
        return "";
    }

    function getExtraArgs() {
        return [];
    }

    /**
     * Return possible move targets. Accepted formats:
     * - Flat array: `string[]` of target IDs (e.g. `["hex_12_8", "hex_11_7"]`)
     *   — normalized to `["q" => 0]` by `extractPossibleMoves()`
     * - Annotated assoc array: `[target => ["q" => int, ...]]`
     *   where `"q"` is `Material::RET_OK` (0) for valid or an error code to disable
     *   Value is of type `ParamInfo` (see TypeScript definition)
     * - Special top-level keys: `"err"`, `"q"`, `"prompt"` are meta-data, not targets
     */
    function getPossibleMoves() {
        return ["confirm"];
    }

    function getReason() {
        return $this->getDataField("reason", "");
    }

    /** Operation is void is it has no valid target, however skippable operation is never void */
    function isVoid(): bool {
        if ($this->canSkip()) {
            return false;
        }

        if ($this->noValidTargets()) {
            return true;
        }
        return false;
    }

    /** Called on game state to see if we can do this one automatically and if not change players and return state we want to be in */
    function onEnteringGameState() {
        if ($this->expandOperation()) {
            $this->destroy();
            return;
        }
        $isAuto = $this->auto();

        if (!$isAuto) {
            if ($this->getPlayerId() == Game::PLAYER_AUTOMA) {
                throw new UserException("Operation does not implement automata " . $this->getTypeFullExpr());
            }
            // switch to player state
            if ($this->game->machine->isMultiplayerOperationMode()) {
                return MultiPlayerTurnPrivate::class;
            }
            return PlayerTurn::class;
        }
        $this->destroy();
        return;
    }

    /** Automatic action perform in game state, if cannot be done automatically turn one of player's states
     * Return false to enter player state
     */
    function auto(): bool {
        if (!$this->canResolveAutomatically()) {
            return false;
        }
        $this->checkVoid();
        if ($this->noValidTargets()) {
            if ($this->canSkip()) {
                $this->action_skip();
                return true;
            }
        }
        $this->action_resolve([]);
        return true;
    }

    function checkVoid() {
        if ($this->isVoid()) {
            $this->game->userAssert($this->getError());
        }
    }

    function canResolveAutomatically() {
        if ($this->requireConfirmation()) {
            return false;
        }
        if ($this->noValidTargets()) {
            if ($this->canSkip()) {
                return true;
            }
            return false;
        }
        if ($this->canSkip()) {
            return false;
        }
        // if ($this->getArgType() == Operation::TTYPE_AUTO) {
        //     return true;
        // }
        if ($this->isOneChoice()) {
            return true;
        }
        return false;
    }

    /** Call onEnteringPlauerState if we go to player's state*/
    function onEnteringPlayerState() {
        return;
    }

    function action_resolve(mixed $data) {
        if (!is_array($data)) {
            throw new SystemException("data encoding issues");
        }

        $this->userArgs = $data;
        return $this->resolve($data) ?: $this->destroy();
    }

    /** User does the action. If this return false or void or 0 we will end the operation, and return the state */
    function resolve(): void {
        return;
    }

    function action_skip() {
        return $this->skip() ?: $this->destroy();
    }

    /** Called on operation to see if we can skip this one */
    function canSkip() {
        return false;
    }
    /** Called on operation to skip this one */
    function skip() {
        if (!$this->canSkip()) {
            throw new UserException(clienttranslate("Cannot skip this action"));
        }
    }

    function undo() {
        $this->game->dbMultiUndo->undoRestorePoint();
    }

    function action_whatever() {
        return $this->whatever() ?: $this->destroy();
    }

    function whatever() {
        $args = $this->getArgs();
        $targets = $args[Operation::ARG_TARGET];
        $num = count($targets);
        if ($num == 0) {
            $state = $this->skip();
        } else {
            // TODO: support multi-select
            $state = $this->resolve([Operation::ARG_TARGET => $targets[$this->game->bgaRand(0, $num - 1)]]);
        }
        return $state;
    }

    function getPossibleMovesDelegate(string $operationType) {
        $moves = $this->instanciateOperation($operationType)->getPossibleMoves();
        foreach ($moves as &$move) {
            $move["delegate"] = $operationType;
        }
        return $moves;
    }
}
