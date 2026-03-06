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
use function Bga\Games\Fate\getPart;

/**
 * Reinforcement: draw monster cards and place monsters on the board.
 * Fully automatic — one instance handles all players' reinforcements.
 *
 * Data: ["deck" => "deck_monster_yellow"|"deck_monster_red", "card" => optional specific card id to use instead of drawing]
 */
class Op_reinforcement extends Operation {
    function resolve(): void {
        $cardId = $this->getDataField("card", null);
        if ($cardId !== null) {
            $this->placeMonsterCard($cardId);
            return;
        }

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

            if ($this->placeMonsterCard($cardId)) {
                return;
            }
        }

        // Exhausted retries — no placeable card found
        $this->game->notifyMessage(clienttranslate("No placeable monster card found — skipping reinforcement"));
    }

    /** Map card number to legend number. Yellow 1-6, Red 37-42. */
    const CARD_LEGEND_MAP = [
        1 => [1, 1], 2 => [2, 1], 3 => [3, 1], 4 => [4, 1], 5 => [5, 1], 6 => [6, 1],
        37 => [1, 2], 38 => [2, 2], 39 => [3, 2], 40 => [4, 2], 41 => [5, 2], 42 => [6, 2],
    ];

    /** Place monsters from a specific card. Returns true on success, false if skipped. */
    private function placeMonsterCard(string $cardId): bool {
        $spawnLoc = $this->game->tokens->getRulesFor($cardId, "spawnloc");
        $spawnStr = $this->game->tokens->getRulesFor($cardId, "spawn");

        // Parse spawn string into list of monster types (including "LEGEND" markers)
        $monsterTypes = $this->parseSpawnString($spawnStr, $spawnLoc);

        // Get available hexes in the spawn location
        $allHexes = $this->game->hexMap->getHexesInLocation($spawnLoc);

        // Resolve legend token ID if this card spawns a legend
        $legendTokenId = null;
        $isLegendCard = in_array("LEGEND", $monsterTypes, true);
        if ($isLegendCard) {
            $legendTokenId = $this->getLegendTokenId($cardId);
            if ($legendTokenId === null) {
                $this->dbSetTokenLocation(
                    $cardId,
                    "display_monsterturn",
                    1,
                    clienttranslate('${token_name}: unknown legend — skipping')
                );
                return false;
            }
        }

        // Move card to display
        $this->dbSetTokenLocation($cardId, "display_monsterturn", 0, clienttranslate('Monster card drawn: ${token_name}'));

        // Build placements
        $errors = false;
        $placements = [];
        foreach ($monsterTypes as $index => $monsterType) {
            if ($monsterType === null) {
                continue; // empty hex position
            }
            $hex = $allHexes[$index] ?? null;
            if ($hex === null) {
                $errors = true;
                break;
            }
            if ($this->game->hexMap->isOccupied($hex)) {
                $errors = true;
                break;
            }

            if ($monsterType === "LEGEND") {
                // Check if legend is already on the map — skip placement (upgrade handled separately)
                $legendLoc = $this->game->tokens->getTokenLocation($legendTokenId);
                if (str_starts_with($legendLoc, "hex_")) {
                    // Legend already on the board — don't place again, just continue with escorts
                    continue;
                }
                $placements[$legendTokenId] = $hex;
            } else {
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
        }

        if ($errors) {
            $this->dbSetTokenLocation(
                $cardId,
                "display_monsterturn",
                1, // state indicated it was skipped, make it grayed out in UI
                clienttranslate('${token_name} cannot be placed, drawing another...')
            );
            return false;
        }

        foreach ($placements as $monsterId => $hex) {
            $this->game->hexMap->moveCharacter($monsterId, $hex);
        }
        return true;
    }

    /** Get the legend token ID for a monster card, or null if not a legend card. */
    private function getLegendTokenId(string $cardId): ?string {
        $cardNum = (int) getPart($cardId, 2);
        $legendInfo = self::CARD_LEGEND_MAP[$cardNum] ?? null;
        if ($legendInfo === null) {
            return null;
        }
        [$legendNum, $level] = $legendInfo;
        return "monster_legend_{$legendNum}_{$level}";
    }
    const MONSTER_ABBREV = [
        "trollkin" => ["G" => "monster_goblin", "B" => "monster_brute", "T" => "monster_troll"],
        "firehorde" => ["S" => "monster_sprite", "E" => "monster_elemental", "J" => "monster_jotunn"],
        "dead" => ["I" => "monster_imp", "S" => "monster_skeleton", "D" => "monster_draugr"],
    ];
    /**
     * Parse spawn string like "G,G,,,B,,,T,,B" into positional array.
     * Returns array indexed by hex position: null for empty slots, "LEGEND" for L, or monster type ID.
     */
    private function parseSpawnString(string $spawnStr, string $spawnLoc): array {
        $faction = Material::SPAWN_FACTION[$spawnLoc] ?? "trollkin";
        $abbrevMap = self::MONSTER_ABBREV[$faction] ?? [];

        $parts = explode(",", $spawnStr);
        $result = [];
        foreach ($parts as $index => $abbrev) {
            $abbrev = trim($abbrev);
            if ($abbrev === "") {
                $result[$index] = null;
            } elseif ($abbrev === "L") {
                $result[$index] = "LEGEND";
            } else {
                $result[$index] = $abbrevMap[$abbrev] ?? null;
            }
        }
        return $result;
    }
}
