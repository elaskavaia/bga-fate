<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Monster Movement Phase — move all monsters toward Grimheim.
 *
 * Data Fields:
 * - charge: bool — whether this is a charge (skull) turn (+1 movement step)
 */
class Op_monsterMoveAll extends Operation {
    function resolve(): void {
        $isChargeTurn = (bool) $this->getDataField("charge", false);
        $this->moveAllMonsters($isChargeTurn);
    }

    /**
     * Move each monster toward Grimheim along the shortest path.
     * Order: closest to Grimheim first.
     * Each monster moves a number of steps equal to its move value (default 1).
     * Charge: on skull turns, all monsters get +1 additional step.
     * Charge rule C: any monster that can reach a hero with 1 extra step charges.
     * Rules: skip if adjacent to a hero; skip if target hex is occupied
     * (Legends swap places with a blocking non-Legend monster instead of skipping).
     * If a monster enters Grimheim, it is removed and town pieces are destroyed.
     */
    private function moveAllMonsters(bool $isChargeTurn): void {
        $monsters = $this->game->hexMap->getMonstersOnMap();

        if ($isChargeTurn) {
            $this->game->notify->all("message", clienttranslate("Skull turn! All monsters charge toward Grimheim!"));
        } else {
            $this->game->notify->all("message", clienttranslate("All monsters slowly walk toward Grimheim"));
        }

        foreach ($monsters as $m) {
            $monsterId = $m["key"];
            $currentHex = $m["hex"];

            // state=0 markers block movement this turn; bump to state=1 (spent) afterwards so
            // they no longer block movement but still block re-targeting by the same supfire card.
            $stunMarkers = $this->game->tokens->getTokensOfTypeInLocation("stunmarker", $monsterId, 0);
            if (count($stunMarkers) > 0) {
                $this->game->notifyMessage(clienttranslate('${token_name} is suppressed and cannot move this turn'), [
                    "token_name" => $monsterId,
                ]);
                foreach ($stunMarkers as $marker) {
                    $this->dbSetTokenState($marker["key"], 1, "");
                }
                continue;
            }

            // Monster Die `charge` side gives rank-1 monsters +1 step (RULES.md §2).
            $isChargeForMonster = $isChargeTurn;
            if (!$isChargeForMonster && $this->game->getMonsterDieSide() === "charge") {
                $rank = (int) $this->game->getRulesFor($monsterId, "rank", 0);
                if ($rank === 1) {
                    $isChargeForMonster = true;
                }
            }
            $this->moveMonster($monsterId, $currentHex, $isChargeForMonster);

            // Stop immediately if the last house was destroyed
            if ($this->game->isWellDestroyed()) {
                return;
            }
        }
    }

    /**
     * Move a single monster its full movement toward Grimheim.
     * Charge adds +1 step (monsters never take more than 1 extra charge step).
     */
    private function moveMonster(string $monsterId, string $currentHex, bool $charge): void {
        $moveSteps = (int) $this->game->getRulesFor($monsterId, "move", 1);
        if ($charge) {
            $moveSteps += 1;
        }

        for ($step = 0; $step < $moveSteps; $step++) {
            $currentHex = $this->moveMonsterOneStep($monsterId, $currentHex);
            if ($currentHex === null) {
                return; // monster was removed (entered Grimheim) or couldn't move
            }
        }

        // Charge rule C: if monster finished normal move and cannot attack a hero,
        // but would be able to after 1 extra step, it charges to get into attack range.
        // Uses the monster's attack range (Fire Horde reaches 2), not just adjacency.
        if (!$charge) {
            $range = $this->game->getMonster($monsterId)->getAttackRange();
            if (!$this->game->hexMap->isCharacterTypeInRange($currentHex, $range, "hero")) {
                $nextHex = $this->game->hexMap->getMonsterNextHex($currentHex);
                if ($nextHex !== null && $this->game->hexMap->isCharacterTypeInRange($nextHex, $range, "hero")) {
                    $this->moveMonsterOneStep($monsterId, $currentHex, clienttranslate('${token_name} charges toward the nearest hero!'));
                }
            }
        }
    }

    /**
     * Move a single monster one step toward Grimheim. No message by default so it does not pollute the log
     *
     * @return string|null The new hex the monster is on, or null if it couldn't move or entered Grimheim.
     */
    private function moveMonsterOneStep(string $monsterId, string $currentHex, string $message = ""): ?string {
        // Already in Grimheim — shouldn't happen, but skip
        if ($this->game->hexMap->isInGrimheim($currentHex)) {
            return null;
        }

        // Don't move if adjacent to a hero
        if ($this->game->hexMap->isHeroAdjacentTo($currentHex)) {
            return null;
        }

        $nextHex = $this->game->hexMap->getMonsterNextHex($currentHex);
        if ($nextHex === null) {
            return null; // no path
        }

        // Can't enter an occupied hex (by another monster or hero)
        // Exception: Legends swap places with a blocking non-Legend monster (RULES.md §3).
        if ($this->game->hexMap->isOccupied($nextHex)) {
            if (
                str_contains($monsterId, "legend") &&
                ($blockerId = $this->game->hexMap->isOccupiedByCharacterType($nextHex, "monster")) !== null &&
                !str_contains($blockerId, "legend")
            ) {
                $this->game
                    ->getMonster($blockerId)
                    ->moveTo($currentHex, clienttranslate('${token_name} is pushed aside by ${token_name2}'), [
                        "token_name2" => $monsterId,
                    ]);
                $this->game->getMonster($monsterId)->moveTo($nextHex, $message);
                return $nextHex;
            }
            return null;
        }

        if ($this->game->hexMap->isInGrimheim($nextHex)) {
            // Monster reaches Grimheim — remove monster and destroy town pieces
            $this->monsterEntersGrimheim($monsterId, $currentHex);
            return null;
        }

        $this->game->getMonster($monsterId)->moveTo($nextHex, $message);
        return $nextHex;
    }

    private function monsterEntersGrimheim(string $monsterId, string $fromHex): void {
        $this->game->effect_monsterEntersGrimheim($monsterId);
    }
}
