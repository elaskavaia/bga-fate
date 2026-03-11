<?php

declare(strict_types=1);
namespace Bga\Games\Fate\Tests;

use Bga\GameFramework\NotificationMessage;
use Bga\GameFramework\Notify;
use Bga\GameFramework\UserException;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\OpCommon\OpMachine;
use Bga\Games\Fate\StateConstants;

//       "player_colors" => ["ff0000", "ffcc02", "6cd0f6", "982fff"],
if (!defined("PCOLOR")) define("PCOLOR", "6cd0f6");
if (!defined("BCOLOR")) define("BCOLOR", "982fff");
if (!defined("CCOLOR")) define("CCOLOR", "ff0000");
if (!defined("ACOLOR")) define("ACOLOR", "ffffff"); // automa
if (!defined("PCOLOR_ID")) define("PCOLOR_ID", 10);

class RecordingNotify extends Notify {
    public array $log = [];
    private array $decorators = [];

    public function addDecorator(callable $fn): void {
        $this->decorators[] = $fn;
    }

    private function applyDecorators(string $message, array $args): array {
        foreach ($this->decorators as $fn) {
            $args = $fn($message, $args);
        }
        return $args;
    }

    public function all(string $notifName, string|NotificationMessage $message = "", array $args = []): void {
        $args = $this->applyDecorators((string)$message, $args);
        $this->log[] = ["type" => $notifName, "log" => (string)$message, "args" => $args, "channel" => "broadcast"];
    }

    public function player(int $playerId, string $notifName, string|NotificationMessage $message = "", array $args = []): void {
        $args = $this->applyDecorators((string)$message, $args);
        $this->log[] = ["type" => $notifName, "log" => (string)$message, "args" => $args, "channel" => "player", "player_id" => $playerId];
    }
}

class GameUT extends Game {
    var $multimachine;
    var $xtable;
    var $gameap_number = 0;
    var $var_colonies = 0;
    var $_colors = [];
    /** @var int[] Predetermined values for bgaRand(). Consumed in order; falls back to $min when empty. */
    public array $randQueue = [];

    function bgaRand(int $min, int $max): int {
        if ($this->randQueue) {
            return array_shift($this->randQueue);
        }
        return $min;
    }

    function __construct() {
        parent::__construct();
        $this->notify = new RecordingNotify();
        $this->registerNotifyDecorators(); // re-register on RecordingNotify (parent::__construct set a stub Notify)

        $this->xtable = [];
        $this->machine = new OpMachine(new MachineInMem($this, $this->xtable));
        $this->curid = 1;
        $this->_colors = [PCOLOR, BCOLOR];

        $this->tokens = new TokensInMem($this);
    }

    function setPlayersNumber(int $num) {
        switch ($num) {
            case 1:
                $this->_colors = [PCOLOR];
                break;
            case 2:
                $this->_colors = [PCOLOR, BCOLOR];
                break;
            case 3:
                $this->_colors = [PCOLOR, BCOLOR, CCOLOR];
                break;
            case 4:
                $this->_colors = [PCOLOR, BCOLOR, CCOLOR, "ef58a2"];
                break;
            default:
                throw new UserException("Invalid number of players");
        }
    }

    function getUserPreference(int $player_id, int $code): int {
        return 0;
    }

    function init(int $x = 0) {
        //$this->adjustedMaterial(true);
        //$this->createAllTokens();
        $this->gamestate->changeActivePlayer(10);
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
        return $this;
    }

    function clean_cache() {}

    function getMultiMachine() {
        return $this->multimachine;
    }

    public $curid;

    function _getCurrentPlayerId() {
        return $this->curid;
    }

    public function getCurrentPlayerId() {
        return $this->curid;
    }

    function _getColors() {
        return $this->_colors;
    }

    function fakeUserAction(Operation $op, $target = null) {
        return $op->action_resolve([Operation::ARG_TARGET => $target]);
    }

    public function setupGameTables() {
        return parent::setupGameTables();
    }

    public function getAllDatas(): array {
        $result = $this->tokens->getAllDatas();
        $players = $this->loadPlayersBasicInfos();
        // Normalize: add "color" alias for "player_color" (real BGA framework does this)
        foreach ($players as $id => &$p) {
            $p["color"] = $p["player_color"];
        }
        unset($p);
        $result["players"] = $players;
        // Add heroNo and counters (same as Game::getAllDatas)
        foreach ($result["players"] as $player_id => &$pdata) {
            $heroCardKey = $this->tokens->getTokensOfTypeInLocationSingleKey("card_hero", "tableau_" . $pdata["color"]);
            $pdata["heroNo"] = $heroCardKey ? (int)\Bga\Games\Fate\getPart($heroCardKey, 2) : null;
        }
        unset($pdata);
        foreach ($result["players"] as $player_id => &$pdata2) {
            $heroNo = $pdata2["heroNo"] ?? null;
            if ($heroNo === null) continue;
            $hero = $this->getHeroById("hero_$heroNo");
            $heroId = $hero->getId();
            $result["counters"]["counter_strength_$heroId"] = ["value" => $hero->getAttackStrength(), "name" => "counter_strength_$heroId"];
            $result["counters"]["counter_health_$heroId"] = ["value" => $hero->getMaxHealth(), "name" => "counter_health_$heroId"];
            $result["counters"]["counter_range_$heroId"] = ["value" => $hero->getAttackRange(), "name" => "counter_range_$heroId"];
        }
        unset($pdata2);
        $gameStage = $this->tokens->getTokenState(Game::GAME_STAGE);
        $result["gameEnded"] = $gameStage >= 5;
        $result["lastTurn"] = $gameStage >= 1 && $gameStage <= 4;
        return $result;
    }

    // override/stub methods here that access db and stuff
}
