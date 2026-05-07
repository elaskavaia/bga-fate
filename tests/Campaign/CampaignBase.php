<?php

declare(strict_types=1);

use Bga\Games\Fate\StateConstants;
use PHPUnit\Framework\TestCase;

use function Bga\Games\Fate\toJson;

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
     * Scan every emitted notification's log string for ${name} placeholders and
     * assert that each one has a matching key in the notification's args. This
     * catches bugs like passing "token_name" when the template expects "char_name".
     */
    private function assertNotificationPlaceholdersResolved(): void {
        if (!isset($this->game) || !isset($this->game->notify)) {
            return;
        }
        $notifs = $this->game->notify->_getNotifications();
        foreach ($notifs as $idx => $notif) {
            $log = $notif["log"] ?? "";
            if ($log === "" || !is_string($log)) {
                continue;
            }
            if (!preg_match_all('/\$\{([a-zA-Z0-9_]+)\}/', $log, $matches)) {
                continue;
            }
            $args = $notif["args"] ?? [];
            foreach ($matches[1] as $name) {
                $this->assertArrayHasKey(
                    $name,
                    $args,
                    "Notification #$idx ({$notif["type"]}) template \"$log\" references \${{$name}} but no matching arg was provided"
                );
            }
        }
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
    protected function respond(mixed $target): void {
        $this->game->hexMap->invalidateOccupancy();
        $this->driver->runStep("action_resolve", ["data" => ["target" => $target]]);
    }

    /** Send action_skip */
    protected function skip(): void {
        $this->driver->runStep("action_skip", []);
    }

    /** Confirm the card effect resolution prompt (Card::useCard queues its r-expression with confirm=true).
     * Sends "confirm" only when the active op is in a single-choice/confirm state — i.e. it has
     * exactly one valid target or "confirm" is explicitly listed. Single-target ops accept
     * "confirm" via target substitution in Operation::_getCheckedArg. No-op otherwise so tests
     * stay agnostic about whether a separate confirm step exists. */
    protected function confirmCardEffect(): void {
        $targets = $this->getOpArgs()["target"] ?? [];
        if (count($targets) !== 1) {
            return;
        }
        $this->respond($targets[0]);
    }

    /** Get current game state (id, name, active_player, args) */
    protected function getStateArgs(bool $merge = true): array {
        $stateId = $this->game->gamestate->getCurrentMainStateId();
        $state = $this->driver->getGameState($stateId);
        if ($merge) {
            $this->driver->privateFilter($state, (int) $this->game->getCurrentPlayerId(), true);
        }
        return $state;
    }

    /** Get current operation args (targets, prompt, etc.) */
    protected function getOpArgs(): array {
        $state = $this->getStateArgs();
        return $state["args"] ?? [];
    }

    function assertOperation(string $type) {
        $this->assertEquals($type, $this->getOpArgs()["type"] ?? "", "Expected operation");
    }

    /** Dump current operation type, prompt, and valid targets for debugging */
    protected function dumpState(string $label = ""): void {
        echo "state: $label ", toJson($this->getStateArgs()), "\n";
    }
    protected function dumpArgsInfo(string $label = ""): void {
        $args = $this->getStateArgs()["args"];
        $prompt = $args["prompt"];
        $type = $args["type"];
        echo "state: $label $type: $prompt: ", toJson($args["info"]), "\n";
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

    /** Get the color of the active player */
    protected function getActivePlayerColor(): string {
        return $this->game->getPlayerColorById((int) $this->game->getCurrentPlayerId()); // XXX use current for now
    }

    /** Get token location */
    protected function tokenLocation(string $tokenId): string {
        return $this->game->tokens->getTokenLocation($tokenId);
    }

    /**
     * Move the active hero out of Grimheim to a known non-Grimheim hex.
     * Heroes inside Grimheim cannot interact with characters or terrain outside it
     * (RULES.md), so combat tests must call this before placing monsters and attacking.
     * Returns the hex the hero now occupies.
     */
    protected function moveHeroOutOfGrimheim(?string $hex = null): string {
        $heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        $target = $hex ?? "hex_7_9";
        $this->game->tokens->moveToken($heroId, $target);
        return $target;
    }

    /**
     * Spawn $count monsters of the given type adjacent to the active hero
     * (uses the in-game spawn op so placement matches real gameplay).
     * Returns the spawned monster ids in placement order.
     */
    protected function spawnMonsterAdjacent(string $type, int $count = 1): array {
        $color = $this->getActivePlayerColor();
        $supplyBefore = $this->game->tokens->getTokensOfTypeInLocation("monster_$type", "supply_monster");
        $expr = $count > 1 ? "{$count}spawn($type)" : "spawn($type)";
        $op = $this->game->machine->instantiateOperation($expr, $color);
        $op->resolve();
        $supplyAfter = $this->game->tokens->getTokensOfTypeInLocation("monster_$type", "supply_monster");
        return array_keys(array_diff_key($supplyBefore, $supplyAfter));
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
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . $this->getActivePlayerColor()));
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

    /**
     * Limbo every card sitting in any equip deck. Useful in setUp when the
     * test doesn't care about random deck contents — TMonsterKilled-quest
     * cards (Helmet, Quiver) on top of a shuffled deck will auto-claim
     * matching kills mid-test and stall the chain. Tests that need specific
     * equipment use seedDeck.
     */
    protected function clearEquipDecks(): void {
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation("card_equip", "deck_equip%")) as $tokenId) {
            $this->game->tokens->moveToken($tokenId, "limbo");
        }
    }

    /** Move every card currently in the player's hand to limbo. */
    protected function clearHand(string $color = PCOLOR): void {
        foreach (array_keys($this->game->tokens->getTokensOfTypeInLocation(null, "hand_$color")) as $key) {
            $this->game->tokens->moveToken($key, "limbo");
        }
    }

    /** Remove all monsters from the map so tests are deterministic */
    protected function clearMonstersFromMap(): void {
        $monsters = $this->game->hexMap->getMonstersOnMap();
        foreach ($monsters as $m) {
            $this->game->tokens->moveToken($m["key"], "supply_monster");
        }
        $this->game->hexMap->invalidateOccupancy();
    }

    protected function skipIfOp(string $optype): bool {
        $args = $this->getOpArgs();
        if (($args["type"] ?? "") === $optype) {
            $this->skip();
            // Op_turnEnd queues drawEvent (step 4) immediately followed by demote (step 5).
            // Tests calling skipIfOp("drawEvent") at a turn boundary are dismissing turn-end
            // housekeeping; auto-swallow the trailing demote so callers don't need a second
            // boilerplate skipIfOp("demote"). Tests that actually exercise demote can still
            // address it directly via respond(); it's only auto-skipped when it follows drawEvent.
            if ($optype === "drawEvent") {
                $next = $this->getOpArgs();
                if (($next["type"] ?? "") === "demote") {
                    $this->skip();
                }
            }
            return true;
        }
        return false;
    }

    protected function skipOp(string $optype): bool {
        $args = $this->getOpArgs();
        $this->assertEquals($args["type"] ?? "", $optype);
        $this->skip();
        // See skipIfOp() — drawEvent and demote are queued back-to-back by Op_turnEnd;
        // auto-swallow trailing demote so callers don't need a second boilerplate skip.
        if ($optype === "drawEvent") {
            $next = $this->getOpArgs();
            if (($next["type"] ?? "") === "demote") {
                $this->skip();
            }
        }
        return true;
    }

    /**
     * Assert the current op is a useCard prompt that offers exactly $expectedCard, then skip it.
     * Use this to explicitly dismiss a chained useCard prompt that legitimately appears after
     * playing one trigger-eligible card and having another still available.
     */
    protected function skipUseCard(string $expectedCard): void {
        $args = $this->getOpArgs();
        $this->assertEquals("useCard", $args["type"] ?? "", "expected chained useCard prompt");
        $targets = $args["target"] ?? [];
        $this->assertContains(
            $expectedCard,
            $targets,
            "expected chained useCard to offer $expectedCard (got: " . implode(",", $targets) . ")"
        );
        $this->skip();
    }

    /** Seed upcoming bgaRand results (e.g. dice rolls: 5=hit, 1=miss) */
    protected function seedRand(array $values): void {
        $this->game->randQueue = array_merge($this->game->randQueue, $values);
    }

    protected function tearDown(): void {
        $this->assertNotificationPlaceholdersResolved();
        array_map("unlink", glob("$this->outputDir/*"));
        if (is_dir($this->outputDir)) {
            @rmdir($this->outputDir);
        }
    }
}
