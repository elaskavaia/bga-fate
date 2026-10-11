<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

use Bga\Games\Fate\StateConstants;
use PHPUnit\Framework\Attributes\Group;

use function Bga\Games\Fate\toJson;

/**
 * Drives every seat of a seeded game as a zombie, once per hero lineup. A zombie picks a random valid target
 * (Operation::action_whatever), so across seeds it walks into states no hand-written scenario would script.
 *
 * On demand only - `npm run tests:fuzz`. It is a search, not a check, so it stays out of `npm run tests`.
 *
 * The set of failing operation types is asserted EXACTLY, so a new failure fails the test and fixing a
 * listed one fails it too - which forces KNOWN_BLOCKED to shrink rather than rot.
 * Running out of steps is not a failure; it is only counted in the failure message.
 *
 * Env: FUZZ_LOG=1 logs every step (seed, step, state, player, op, picked target), FUZZ_LOG=2 adds driver
 * verbose output; FUZZ_SEED=N replays only lineup N (its seed is its 1-based position).
 */
#[Group("fuzz")]
class Campaign_ZombieFuzzTest extends CampaignBaseTest {
    /** Every hero solo, then mixed lineups so each hero appears in every player count. */
    private const LINEUPS = [[1], [2], [3], [4], [1, 2], [3, 4], [2, 3], [1, 3, 4], [2, 4, 1], [1, 2, 3, 4]];
    /** Budget, not coverage. Raise by hand when hunting. */
    private const MAX_STEPS = 200;

    /** Operation types known to break a zombie. Empty is the goal. */
    private const KNOWN_BLOCKED = [];

    public function testZombiesNeverReachAnOperationTheyCannotAnswer(): void {
        $blocked = [];
        $unfinished = 0;
        $seeds = getenv("FUZZ_SEED") ? [(int) getenv("FUZZ_SEED")] : range(1, count(self::LINEUPS));
        foreach ($seeds as $seed) {
            $this->assertArrayHasKey($seed - 1, self::LINEUPS, "FUZZ_SEED out of range 1.." . count(self::LINEUPS));
            $outcome = $this->playOutAsZombies($seed);
            if ($outcome === null) {
                continue;
            }
            if ($outcome["type"] === "") {
                $unfinished++;
                continue;
            }
            $blocked[] = $outcome;
        }

        $types = array_values(array_unique(array_column($blocked, "type")));
        sort($types);
        $report = $this->describe($blocked) . "\nout of steps: $unfinished of " . count($seeds) . " lineups";
        $this->assertEquals(self::KNOWN_BLOCKED, $types, $report);
    }

    /**
     * Returns null when the game ends, an entry with an empty type when it runs out of steps, or the
     * operation the zombie could not answer.
     */
    private function playOutAsZombies(int $seed): ?array {
        mt_srand($seed);
        // harness randomness is fixed by default - here it must be real, but replayable via mt_srand
        $this->game = new class extends GameWrapper {
            function bgaRand(int $min, int $max): int {
                return mt_rand($min, $max);
            }
            function bgaShuffle(array $arr): array {
                shuffle($arr);
                return $arr;
            }
        };
        $this->driver = new GameDriver($this->game, $this->outputDir);
        $logLevel = (int) getenv("FUZZ_LOG");
        $this->driver->setVerbose($logLevel >= 2 ? 1 : 0);
        $this->setupGame(self::LINEUPS[$seed - 1]);

        $over = [StateConstants::STATE_END_GAME, StateConstants::STATE_MACHINE_HALTED];
        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            if (in_array($this->game->gamestate->getCurrentMainStateId(), $over)) {
                return null;
            }
            $playerId = (int) $this->game->getMostlyActivePlayerId();
            $this->game->_setCurrentPlayerId($playerId);
            $op = $this->game->machine->createTopOperationFromDb($playerId);
            $stateId = $this->game->gamestate->getCurrentMainStateId();
            $this->game->zombieTarget = null;
            try {
                $this->driver->runZombie($playerId);
            } catch (Throwable $e) {
                // the throw may come from an automatic op queued after the zombie's pick, still on top
                $failing = $this->game->machine->createTopOperationFromDbForOwner(null);
                return [
                    "seed" => $seed,
                    "step" => $step,
                    "type" => $failing?->getType() ?? "(none)",
                    "expr" => $failing?->getTypeFullExpr() ?? "(none)",
                    "picked_by" => $op?->getTypeFullExpr() ?? "(none)",
                    "error" => $e->getMessage(),
                ];
            }
            if ($logLevel) {
                $picked = $this->game->zombieTarget ?? "(no pick)";
                $expr = $op?->getTypeFullExpr() ?? "(none)";
                fwrite(
                    STDERR,
                    "fuzz seed $seed step $step state $stateId player $playerId: $expr -> " . toJson($picked, JSON_UNESCAPED_SLASHES) . "\n"
                );
            }
        }
        return ["seed" => $seed, "step" => self::MAX_STEPS, "type" => ""];
    }

    private function describe(array $blocked): string {
        return implode(
            "\n",
            array_map(
                fn($b) => "  seed {$b["seed"]} heroes " .
                    toJson(self::LINEUPS[$b["seed"] - 1], JSON_UNESCAPED_SLASHES) .
                    " step {$b["step"]}: {$b["expr"]} (after {$b["picked_by"]}) - {$b["error"]}",
                $blocked
            )
        );
    }
}
