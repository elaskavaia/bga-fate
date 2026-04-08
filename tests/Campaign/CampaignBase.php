<?php

declare(strict_types=1);

use Bga\Games\Fate\StateConstants;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . "/../Harness/HarnessGameInterface.php";
require_once __DIR__ . "/../Harness/GameWrapper.php";
require_once __DIR__ . "/../Harness/GameDriver.php";

/**
 * Base class for campaign integration tests.
 * Uses GameDriver in-process (no CLI subprocess, no renderer).
 * Subclasses call setupGame() then script player actions via respond()/skip().
 */
abstract class CampaignBaseTest extends TestCase {
    protected GameWrapper $game;
    protected GameDriver $driver;
    protected string $outputDir;

    protected function setUp(): void {
        $this->outputDir = sys_get_temp_dir() . "/campaign_test_" . getmypid();
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
        $this->game = new GameWrapper();
        $this->driver = new GameDriver($this->game, $this->outputDir);
        $this->driver->setVerbose(0);
    }

    /**
     * Set up a game with specified heroes.
     * @param array $heroOrder hero numbers in player order, e.g. [1] for solo Bjorn, [2,4] for Alva+Boldur
     */
    protected function setupGame(array $heroOrder = [1]): void {
        $this->game->setPlayersNumber(count($heroOrder));
        $this->game->loadDbState([]);
        $this->game->setHeroOrder($heroOrder);
        $this->game->setupGameTables();
        $this->game->gamestate->jumpToState(StateConstants::STATE_GAME_DISPATCH);
        $this->driver->runDispatchLoop();
        $this->driver->emitGameStateChange();
    }

    /** Send a player response (action_resolve with target) */
    protected function respond(string $target): void {
        $this->driver->runStep("action_resolve", ["data" => ["target" => $target]]);
    }

    /** Send action_skip */
    protected function skip(): void {
        $this->driver->runStep("action_skip", []);
    }

    /** Get current game state (id, name, active_player, args) */
    protected function getStateArgs(): array {
        $stateId = $this->game->gamestate->getCurrentMainStateId();
        return $this->driver->getGameState($stateId);
    }

    /** Get current operation args (targets, prompt, etc.) */
    protected function getOpArgs(): array {
        $state = $this->getStateArgs();
        $private = $state["args"]["_private"] ?? null;
        if (is_array($private)) {
            // _private is keyed by player ID — return the first (active) player's args
            return reset($private) ?: [];
        }
        return $state["args"] ?? [];
    }

    /** Dump current operation type, prompt, and valid targets for debugging */
    protected function dumpState(string $label = ""): void {
        $args = $this->getOpArgs();
        $type = $args["type"] ?? "?";
        $prompt = $args["prompt"] ?? "?";
        $targets = $args["target"] ?? [];
        $info = $label ? "[$label] " : "";
        fwrite(STDERR, "{$info}op={$type} prompt=\"{$prompt}\" targets=[" . implode(", ", $targets) . "]\n");
    }

    /** Assert that a target is valid (appears in target list with q=0) */
    protected function assertValidTarget(string $target, string $message = ""): void {
        $args = $this->getOpArgs();
        $this->assertContains($target, $args["target"] ?? [], $message ?: "$target should be a valid target");
    }

    /** Assert that a target is NOT valid (not in target list) */
    protected function assertNotValidTarget(string $target, string $message = ""): void {
        $args = $this->getOpArgs();
        $this->assertNotContains($target, $args["target"] ?? [], $message ?: "$target should not be a valid target");
    }

    /** Get the color of the current player */
    protected function playerColor(): string {
        return $this->game->getPlayerColorById((int) $this->game->getCurrentPlayerId());
    }

    /** Get token location */
    protected function tokenLocation(string $tokenId): string {
        return $this->game->tokens->getTokenLocation($tokenId);
    }

    /** Count tokens of type at location */
    protected function countTokens(string $type, string $location): int {
        return count($this->game->tokens->getTokensOfTypeInLocation($type, $location));
    }

    /** Count damage (red crystals) on a character */
    protected function countDamage(string $charId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $charId));
    }

    /** Count XP (yellow crystals) on current player's tableau */
    protected function countXp(): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . $this->playerColor()));
    }

    /** Place specific cards on top of a deck (first in array = top of deck) */
    protected function seedDeck(string $deckLocation, array $cardIds): void {
        // Use state 9000+ to ensure seeded cards are above any existing deck cards
        $state = 9000 + count($cardIds);
        foreach ($cardIds as $cardId) {
            $this->game->tokens->moveToken($cardId, $deckLocation, $state--);
        }
    }

    /** Place a specific card in hand */
    protected function seedHand(string $cardId, string $color = PCOLOR): void {
        $this->game->tokens->moveToken($cardId, "hand_$color");
    }

    /** Remove all monsters from the map so tests are deterministic */
    protected function clearMonstersFromMap(): void {
        $monsters = $this->game->hexMap->getMonstersOnMap();
        foreach ($monsters as $m) {
            $this->game->tokens->moveToken($m["id"], "supply_monster");
        }
        $this->game->hexMap->invalidateOccupancy();
    }

    /** Skip any pending trigger operations (e.g. on=roll reactions) */
    protected function skipTriggers(): void {
        $args = $this->getOpArgs();
        while (($args["type"] ?? "") === "trigger") {
            $this->skip();
            $args = $this->getOpArgs();
        }
    }

    protected function skipIfOp(string $optype): bool {
        $args = $this->getOpArgs();
        if (($args["type"] ?? "") === $optype) {
            $this->skip();
            return true;
        }
        return false;
    }

    /** Seed upcoming bgaRand results (e.g. dice rolls: 5=hit, 1=miss) */
    protected function seedRand(array $values): void {
        $this->game->randQueue = array_merge($this->game->randQueue, $values);
    }

    protected function tearDown(): void {
        array_map("unlink", glob("$this->outputDir/*"));
        if (is_dir($this->outputDir)) {
            @rmdir($this->outputDir);
        }
    }
}
