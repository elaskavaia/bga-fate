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
 * Reinforcement: draw monster cards and place monsters on the board.
 * Fully automatic — one instance handles all players' reinforcements.
 *
 * Data: ["deck" => "deck_monster_yellow"|"deck_monster_red"]
 */
class Op_reinforcement extends Operation {
    function resolve(): void {
        $deck = $this->getDataField("deck", "deck_monster_yellow");
        $playerCount = $this->game->getPlayersNumber();

        for ($i = 0; $i < $playerCount; $i++) {
            $this->drawAndPlaceMonsters($deck);
        }
    }

    private function drawAndPlaceMonsters(string $deck): void {
        $maxRetries = 20; // safety limit to avoid infinite loop
        for ($retry = 0; $retry < $maxRetries; $retry++) {
            $cardInfo = $this->game->tokens->getTokenOnTop($deck);
            if ($cardInfo === null) {
                $this->game->notifyMessage(clienttranslate("Monster deck is empty — no reinforcements"));
                return;
            }
            $cardId = $cardInfo["key"];
            $spawnLoc = $this->game->tokens->getRulesFor($cardId, "spawnloc");
            $spawnStr = $this->game->tokens->getRulesFor($cardId, "spawn");

            // TODO: Skip legend cards — not yet implemented
            if (str_contains($spawnStr, "L")) {
                $this->dbSetTokenLocation(
                    $cardId,
                    "display_monsterturn",
                    1,
                    clienttranslate('${token_name} is a legend card, drawing another...')
                );
                continue;
            }

            // Parse spawn string into list of monster types
            $monsterTypes = $this->parseSpawnString($spawnStr, $spawnLoc);

            // Get available hexes in the spawn location
            $allHexes = $this->game->hexMap->getHexesInLocation($spawnLoc);

            // Move card to display
            $this->dbSetTokenLocation($cardId, "display_monsterturn", 0, clienttranslate('Monster card drawn: ${token_name}'));

            // Place monsters
            $errors = false;
            $placements = [];
            foreach ($monsterTypes as $index => $monsterType) {
                $hex = $allHexes[$index];
                if ($this->game->hexMap->isOccupied($hex)) {
                    $errors = true;
                    break;
                }

                // Pick one monster of this type from supply (skip already claimed)
                $supplyTokens = $this->game->tokens->getTokensOfTypeInLocation($monsterType, "supply_monster");
                $monsterId = null;
                foreach ($supplyTokens as $token) {
                    if (!isset($placements[$token["key"]])) {
                        $monsterId = $token["key"];
                        break;
                    }
                }
                if ($monsterId === null) {
                    $errors = true;
                    break;
                }

                $placements[$monsterId] = $hex;
            }

            if ($errors) {
                $this->dbSetTokenLocation(
                    $cardId,
                    "display_monsterturn",
                    1, // state indicated it was skipped, make it grayed out in UI
                    clienttranslate('${token_name} cannot be placed, drawing another...')
                );
                continue;
            }

            foreach ($placements as $monsterId => $hex) {
                $this->game->hexMap->moveCharacter($monsterId, $hex);
            }
            return;
        }

        // Exhausted retries — no placeable card found
        $this->game->notifyMessage(clienttranslate("No placeable monster card found — skipping reinforcement"));
    }
    const MONSTER_ABBREV = [
        "trollkin" => ["G" => "monster_goblin", "B" => "monster_brute", "T" => "monster_troll"],
        "firehorde" => ["S" => "monster_sprite", "E" => "monster_elemental", "J" => "monster_jotunn"],
        "dead" => ["I" => "monster_imp", "S" => "monster_skeleton", "D" => "monster_draugr"],
    ];
    /**
     * Parse spawn string like "G,G,,,B,,,T,,B" into array of monster type IDs.
     * Skips empty entries and L (legends, deferred to later iteration).
     */
    private function parseSpawnString(string $spawnStr, string $spawnLoc): array {
        $faction = Material::SPAWN_FACTION[$spawnLoc] ?? "trollkin";
        $abbrevMap = self::MONSTER_ABBREV[$faction] ?? [];

        $parts = explode(",", $spawnStr);
        $result = [];
        foreach ($parts as $abbrev) {
            $abbrev = trim($abbrev);
            if ($abbrev === "" || $abbrev === "L") {
                continue; // skip empty slots and legends TODO: fix legend placement
            }
            $monsterType = $abbrevMap[$abbrev] ?? null;
            if ($monsterType !== null) {
                $result[] = $monsterType;
            }
        }
        return $result;
    }
}
