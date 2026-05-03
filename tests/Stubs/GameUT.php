<?php

declare(strict_types=1);
namespace Bga\Games\Fate\Stubs;

use Bga\GameFramework\UserException;
use Bga\Games\Fate\Game;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\OpCommon\OpMachine;
use Bga\Games\Fate\StateConstants;
use Bga\Games\Fate\Stubs\MachineInMem;
use Bga\Games\Fate\Stubs\TokensInMem;

// Hero colors: Bjorn=green, Alva=blue, Embla=orange, Boldur=red
if (!defined("PCOLOR")) {
    define("PCOLOR", "2e7d32");
}
if (!defined("BCOLOR")) {
    define("BCOLOR", "1565c0");
}
if (!defined("CCOLOR")) {
    define("CCOLOR", "bf360c");
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
        $this->tokens = new TokensInMem($this);
        $this->setPlayersNumber(2);
    }

    function _getAvailColors() {
        return $this->getAvailColors();
    }

    function setPlayersNumber(int $num) {
        $colors = array_slice($this->_getAvailColors(), 0, $num);
        $this->_setPlayerBasicInfoFromColors($colors);
    }

    function getUserPreference(int $player_id, int $code): int {
        return 0;
    }

    private ?array $heroOrder = [1, 2, 3, 4]; // deterministic by default in tests

    public function setHeroOrder(array $order): void {
        $this->heroOrder = $order;
    }

    protected function getHeroOrder(): array {
        return $this->heroOrder ?? parent::getHeroOrder();
    }

    function init(int $x = 0) {
        //$this->adjustedMaterial(true);
        //$this->createAllTokens();
        $this->gamestate->changeActivePlayer(10);
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
        return $this;
    }

    function initWithHero(int $hnum = 1) {
        $this->gamestate->changeActivePlayer(10);
        $this->setPlayersNumber(1);
        $this->setHeroOrder([$hnum]);
        $this->game->setupGameTables();
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
        return $this;
    }
    function initWithHeros(array $order = [1, 2, 3, 4]) {
        $this->gamestate->changeActivePlayer(10);
        $this->setPlayersNumber(count($order));
        $this->setHeroOrder($order);
        $this->game->setupGameTables();
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
        return $this;
    }

    /** Move every card out of the player's hand into limbo. */
    function clearHand(): void {
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation(null, "hand_" . PCOLOR)) as $key) {
            $this->game->tokens->moveToken($key, "limbo");
        }
    }

    /** Remove every operation from the machine stack. */
    function clearMachine(): void {
        $this->machine->db->loadRows([]);
    }

    /**
     * Limbo every equipment card sitting in any equip deck so quests on
     * deck-top cards don't leak into unrelated tests (e.g. a Helmet on top
     * auto-claiming any brute kill). Tests that need specific equipment
     * can move/seedDeck the cards they want back.
     */
    function clearEquipDecks(): void {
        foreach (array_keys($this->tokens->getTokensOfTypeInLocation("card_equip", "deck_equip%")) as $tokenId) {
            $this->tokens->moveToken($tokenId, "limbo");
        }
    }

    function getMultiMachine() {
        return $this->multimachine;
    }

    function fakeUserAction(Operation $op, $target = null) {
        return $op->action_resolve([Operation::ARG_TARGET => $target]);
    }

    function setPlayerColor(int $playerId, string $color): void {
        $players = $this->loadPlayersBasicInfos();
        if (isset($players[$playerId])) {
            $players[$playerId]["player_color"] = $color;
            $this->_setPlayerBasicInfo($players);
        }
    }

    // override/stub methods here that access db and stuff
}
