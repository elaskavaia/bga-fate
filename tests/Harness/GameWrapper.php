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
    private const PLAYER_COLORS = ["6cd0f6", "982fff", "ff0000", "ef58a2"];

    var $xtable;
    var $_colors = [];

    /** When set, getHeroOrder() returns this fixed list instead of shuffling. */
    private ?array $heroOrder = null;

    function __construct() {
        parent::__construct();
        $this->xtable = [];
        $this->machine = new OpMachine(new MachineInMem($this, $this->xtable));
        $this->_colors = array_slice(self::PLAYER_COLORS, 0, 2);
        $this->tokens = new TokensInMem($this);
    }

    public array $randQueue = [];

    function bgaRand(int $min, int $max): int {
        if ($this->randQueue) {
            return array_shift($this->randQueue);
        }
        return $min;
    }

    function setPlayersNumber(int $num) {
        $this->_colors = array_slice(self::PLAYER_COLORS, 0, $num);
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
        $this->setPlayersNumber(1);
        $this->heroOrder = [1, 2, 3, 4];
        $this->tokens->deleteAll();
        $this->machine->db->loadRows([]);
        $this->setupGameTables();
        $this->heroOrder = null;
        $this->notify->all("message", "setup h1 done", []);
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
