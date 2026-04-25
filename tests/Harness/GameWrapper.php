<?php

declare(strict_types=1);

use Bga\Games\Fate\Game;
use Bga\Games\Fate\OpCommon\OpMachine;
use Bga\Games\Fate\StateConstants;
use Bga\Games\Fate\Stubs\MachineInMem;
use Bga\Games\Fate\Stubs\TokensInMem;

/**
 * Game-specific wrapper for Fate harness.
 * Extends Game with in-memory DB stubs, debug setup functions,
 * and the harness contract (getGameName, saveDbState, loadDbState, getAllDatas).
 */
class GameWrapper extends Game implements HarnessGameInterface {
    var $xtable;

    /** When set, getHeroOrder() returns this fixed list instead of shuffling. */
    private ?array $heroOrder = null;

    function __construct() {
        parent::__construct();
        $this->xtable = [];
        $this->machine = new OpMachine(new MachineInMem($this, $this->xtable));
        $this->tokens = new TokensInMem($this);
    }

    function _getAvailColors() {
        return $this->getAvailColors();
    }

    public array $randQueue = [];

    function bgaRand(int $min, int $max): int {
        if ($this->randQueue) {
            return array_shift($this->randQueue);
        }
        return $min;
    }

    function setPlayerColor(int $playerId, string $color): void {
        $players = $this->loadPlayersBasicInfos();
        if (isset($players[$playerId])) {
            $players[$playerId]["player_color"] = $color;
            $this->_setPlayerBasicInfo($players);
        }
    }

    function setPlayersNumber(int $num) {
        $colors = array_slice($this->getAvailColors(), 0, $num);
        $this->_setPlayerBasicInfoFromColors($colors);
    }

    public function setHeroOrder(array $order): void {
        $this->heroOrder = $order;
    }

    protected function getHeroOrder(): array {
        return $this->heroOrder ?? parent::getHeroOrder();
    }

    // ── Debug functions ──────────────────────────────────────────────────────

    /** Reset and set up a 1-player game with hero 1 (Bjorn). */
    public function debug_setupGame_h1(): void {
        $this->setupGameWithHero(1);
    }

    /** Reset and set up a 1-player game with hero 4 (Boldur). */
    public function debug_setupGame_h4(): void {
        $this->setupGameWithHero(4);
    }

    private function setupGameWithHero(int $heroNum): void {
        $this->setPlayersNumber(1);
        $rest = array_values(array_diff([1, 2, 3, 4], [$heroNum]));
        $this->heroOrder = array_merge([$heroNum], $rest);
        $this->tokens->deleteAll();
        $this->machine->db->loadRows([]);
        $this->setupGameTables();
        $this->heroOrder = null;
        $this->notify->all("message", "setup h$heroNum done", []);
        $this->sendReloadAllNotification();
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
        $this->machine->dispatchAll();
        $this->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
    }

    public function getAllDatas(): array {
        return parent::getAllDatas();
    }

    // ── Harness contract ─────────────────────────────────────────────────────

    public function getGameName(): string {
        return "Fate";
    }

    public function saveDbState(): array {
        return [
            "tokens" => $this->tokens->toJson(),
            "machine" => $this->machine->db->toJson(),
        ];
    }

    public function loadDbState(array $db, bool $reset = true): void {
        if ($reset) {
            $this->tokens->deleteAll();
            $this->machine->db->loadRows([]);
        }
        $this->tokens->fromJson($db["tokens"] ?? []);
        $this->machine->db->fromJson($db["machine"] ?? []);
    }
}
