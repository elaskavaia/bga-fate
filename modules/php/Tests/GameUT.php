<?php

declare(strict_types=1);
namespace Bga\Games\Fate\Tests;

use Bga\GameFramework\Notify;
use Bga\GameFramework\UserException;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\OpCommon\OpMachine;
use Bga\Games\Fate\StateConstants;

//       "player_colors" => ["ff0000", "ffcc02", "6cd0f6", "982fff"],
if (!defined("PCOLOR")) {
    define("PCOLOR", "6cd0f6");
}
if (!defined("BCOLOR")) {
    define("BCOLOR", "982fff");
}
if (!defined("CCOLOR")) {
    define("CCOLOR", "ff0000");
}
if (!defined("ACOLOR")) {
    define("ACOLOR", "ffffff");
} // automa
if (!defined("PCOLOR_ID")) {
    define("PCOLOR_ID", 10);
}

class GameUT extends Game {
    var $multimachine;
    var $xtable;
    var $gameap_number = 0;
    var $var_colonies = 0;
    var $_colors = []; // redeclaration of same variable in Game stub
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

    public function _getCurrentPlayerId() {
        return $this->curid;
    }

    function fakeUserAction(Operation $op, $target = null) {
        return $op->action_resolve([Operation::ARG_TARGET => $target]);
    }

    // override/stub methods here that access db and stuff
}
