<?php
declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\OpCommon\Operation;

/**
 * Monster Movement Phase — move all monsters toward Grimheim.
 *
 * Automated operation extracted from Op_turnMonster.
 * Each monster moves toward Grimheim along the shortest path.
 * Order: closest to Grimheim first.
 * Charge: on skull turns, all monsters get +1 movement step.
 * Charge rule C: any monster that can reach a hero with 1 extra step charges.
 * Monsters with a green crystal (stunned by Suppressive Fire) skip movement.
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
     * Rules: skip if adjacent to a hero; skip if target hex is occupied.
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
            $monsterId = $m["id"];
            $currentHex = $m["hex"];

            // Check if monster is stunned (green crystal = Suppressive Fire)
            // Crystal stays on the monster until next trigger (enforces "cannot choose same monster next turn")
            $stunCrystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_green", $monsterId);
            if (count($stunCrystals) > 0) {
                $this->game->notifyMessage(clienttranslate('${token_name} is suppressed and cannot move this turn'), [
                    "token_name" => $monsterId,
                ]);
                continue;
            }

            // TODO: Charge rule A — Monster Dice may cause rank 1 monsters to charge
            $this->moveMonster($monsterId, $currentHex, $isChargeTurn);

            // Stop immediately if the last house was destroyed
            if ($this->game->isEndOfGame()) {
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

        // Charge rule C: if monster finished normal move and is not adjacent to a hero,
        // but would be after 1 extra step, it charges to get into attack range
        if (!$charge && !$this->game->hexMap->isHeroAdjacentTo($currentHex)) {
            $nextHex = $this->game->hexMap->getMonsterNextHex($currentHex);
            if ($nextHex !== null && $this->game->hexMap->isHeroAdjacentTo($nextHex)) {
                $this->moveMonsterOneStep($monsterId, $currentHex, clienttranslate('${token_name} charges toward the nearest hero!'));
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
        // TODO: Legends swap places with blocking monsters instead of being stopped
        if ($this->game->hexMap->isOccupied($nextHex)) {
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

    /**
     * Monster reaches Grimheim: remove the monster and destroy town pieces.
     * Legends destroy 3 town pieces, regular monsters destroy 1.
     * Freyja's Well (house_0) is always destroyed last.
     */
    private function monsterEntersGrimheim(string $monsterId, string $fromHex): void {
        // Legends destroy 3 town pieces, regular monsters destroy 1
        $isLegend = str_contains($monsterId, "legend");
        $destroyCount = $isLegend ? 3 : 1;

        $this->game->effect_destroyHouses($destroyCount, $monsterId, clienttranslate('${token_name} tears down a house!'));

        // Remove monster from the map
        $this->game->getMonster($monsterId)->moveTo("supply_monster", clienttranslate('${token_name} goes home happy'));
    }
}
