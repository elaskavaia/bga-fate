<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 */

declare(strict_types=1);

namespace Bga\Games\Fate\Operations;

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\Operation;

/**
 * Monster turn operation.
 * Runs after all players have completed their turns.
 *
 * Steps (per rules): 1. Advance time marker, 2. Monster dice (variant), 3. Monsters move,
 * 4. Monsters attack, 5. Reinforcements (if on axes/skull spot).
 */
class Op_turnMonster extends Operation {
    function resolve(): void {
        $this->cleanupMonsterDisplay();
        $this->advanceTimeTrack();
        $spotType = $this->getCurrentSpotType();
        $isChargeTurn = $spotType === "tm_red_skull";
        // TODO: Step 2 — Roll Monster Dice (variant rule for higher difficulty)
        // Effects: maneuver CW/CCW, attack +1, charge rank 1, push, ambush
        $this->moveAllMonsters($isChargeTurn);
        // TODO: Step 4 — Monsters Attack (all monsters adjacent to heroes attack)
        $this->queueReinforcements($spotType);
        $this->queueNextRound();
    }

    /**
     * Get the time track spot type for the current step.
     */
    private function getCurrentSpotType(): string {
        $step = $this->game->tokens->db->getTokenState("rune_stone");
        $track = $this->game->tokens->db->getTokenLocation("rune_stone");
        $slotId = "slot_{$track}_{$step}";
        return $this->game->tokens->getRulesFor($slotId, "r", "tm_yellow_shield");
    }

    private function cleanupMonsterDisplay(): void {
        $cards = $this->game->tokens->getTokensOfTypeInLocation("card_monster", "display_monsterturn");
        foreach ($cards as $card) {
            $cardId = $card["key"];
            $deck = $this->game->tokens->getRulesFor($cardId, "location");
            $minState = $this->game->tokens->db->getExtremePosition(false, $deck);
            $this->game->tokens->dbSetTokenLocation($cardId, $deck, $minState - 1, ""); // no notify text
        }
    }

    // TODO: support long time track (timetrack_2) based on game option
    private function advanceTimeTrack(): void {
        $currentStep = $this->game->tokens->db->getTokenState("rune_stone");
        $nextStep = $currentStep + 1;
        $maxSteps = Material::TIME_TRACK_SHORT_LENGTH;

        $this->game->tokens->dbSetTokenLocation(
            "rune_stone",
            "timetrack_1", // short track
            $nextStep,
            clienttranslate('Rune Stone: time advances to step ${step} of ${max}'),
            [
                "step" => $nextStep,
                "max" => $maxSteps,
            ]
        );
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
            $this->game->notify->all("message", clienttranslate("Skull turn! All monsters charge toward Grimheim!"), []);
        }

        foreach ($monsters as $m) {
            $monsterId = $m["id"];
            $currentHex = $m["hex"];

            // TODO: Charge rule A — Monster Dice may cause rank 1 monsters to charge
            $this->moveMonster($monsterId, $currentHex, $isChargeTurn);
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
                $this->moveMonsterOneStep($monsterId, $currentHex);
            }
        }
    }

    /**
     * Move a single monster one step toward Grimheim.
     *
     * @return string|null The new hex the monster is on, or null if it couldn't move or entered Grimheim.
     */
    private function moveMonsterOneStep(string $monsterId, string $currentHex): ?string {
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

        $this->game->hexMap->moveCharacter($monsterId, $nextHex, clienttranslate('monsters move ${token_name} toward Grimheim'));
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

        // Build destroy queue: non-well houses first, well last
        $houses = $this->game->tokens->getTokensOfTypeInLocation("house", "hex%");

        for ($i = 0; $i < $destroyCount; $i++) {
            $targetHouseRec = array_shift($houses);
            if ($targetHouseRec === null) {
                break; // no more town pieces to destroy
            }
            // Freyja's Well is destroyed last — push it back if others remain
            if ($targetHouseRec["key"] === "house_0" && count($houses) > 0) {
                $houses[$targetHouseRec["key"]] = $targetHouseRec;
                $i--; // retry with next house
                continue;
            }
            $this->game->tokens->dbSetTokenLocation(
                $monsterId,
                $targetHouseRec["location"], // to show animations on monster
                null,
                clienttranslate('${token_name} enters Grimheim and destroys the house')
            );
            $this->game->tokens->dbSetTokenLocation($targetHouseRec["key"], "limbo", 0, "");
        }
        // Remove monster from the map
        $this->game->hexMap->moveCharacter($monsterId, "supply_monsters", clienttranslate('monsters remove ${token_name} from the map'));
    }

    private function queueReinforcements(string $spotType): void {
        if ($spotType === "tm_yellow_axes") {
            $this->queue("reinforcement", null, ["deck" => "deck_monster_yellow"]);
        } elseif ($spotType === "tm_red_axes") {
            $this->queue("reinforcement", null, ["deck" => "deck_monster_red"]);
        } elseif ($spotType === "tm_red_skull") {
            // Skull spots trigger red reinforcements AND charge (charge handled in moveAllMonsters)
            $this->queue("reinforcement", null, ["deck" => "deck_monster_red"]);
        }
    }

    private function queueNextRound(): void {
        if ($this->game->isEndOfGame()) {
            if ($this->game->isHeroesWin()) {
                // Time track completed — players win!
                $this->game->notify->all("message", clienttranslate("The time track has reached the end. Freyja returns! You win!"), []);
            } else {
                $this->game->notify->all("message", clienttranslate("The heroes have failed. The monster wins!"), []);
            }
            return;
        }

        // Start next round with the first player
        $firstPlayerId = $this->game->getFirstPlayer();
        $this->game->machine->queue("turn", $this->game->custom_getPlayerColorById($firstPlayerId));
    }
}
