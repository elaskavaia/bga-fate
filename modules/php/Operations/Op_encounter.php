<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Fate implementation : © Alena Laskavaia <laskava@gmail.com> - aka Victoria_La
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
 * Encounter: a hero stepping onto a bonus hex picks up some, all, or none of the
 * crystals parked there. Co-op aware — the choice is required because a teammate
 * may need the bonus more.
 *
 * From RULES.md:130-133 (setup):
 *  - 3 red on Troll Caves   — heal damage from the player's cards
 *  - 3 green on Nailfare    — add mana to the player's cards
 *  - 3 yellow on Wyrm Lair  — gain XP/gold to the player's tableau
 *
 * Queued by Op_step::resolve() when a hero lands on any hex carrying crystals.
 *
 * Data Fields:
 *  - hex: the hex id where the encounter is happening
 */
class Op_encounter extends Operation {
    private function getHex(): string {
        return (string) $this->getDataField("hex", "");
    }

    private function getColorOnHex(): string {
        $hex = $this->getHex();
        foreach (["red", "green", "yellow"] as $color) {
            if (count($this->game->tokens->getTokensOfTypeInLocation("crystal_$color", $hex)) > 0) {
                return $color;
            }
        }
        return "yellow"; // default (it will be 0/Skip only)
    }

    private function countOnHex(string $color): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_$color", $this->getHex()));
    }

    function getPrompt() {
        return match ($this->getColorOnHex()) {
            "red" => clienttranslate("Pick up red crystals to remove damage"),
            "green" => clienttranslate("Pick up mana to place on your cards"),
            "yellow" => clienttranslate("Pick up gold"),
            default => parent::getPrompt(),
        };
    }

    function canSkip() {
        return true;
    }

    function getPossibleMoves() {
        $color = $this->getColorOnHex();

        $n = $this->countOnHex($color);
        if ($n == 0) {
            return ["q" => Material::ERR_NOT_APPLICABLE];
        }
        $targets = [];
        for ($i = 1; $i <= $n; $i++) {
            $targets[(string) $i] = ["q" => Material::RET_OK];
        }
        return $targets;
    }

    function resolve(): void {
        $count = (int) $this->getCheckedArg();
        $color = $this->getColorOnHex();

        $hex = $this->getHex();
        $owner = $this->getOwner();
        $heroId = $this->game->getHeroTokenId($owner);
        // Return picked crystals to supply, then queue the appropriate gain op.
        $picked = array_slice(array_keys($this->game->tokens->getTokensOfTypeInLocation("crystal_$color", $hex)), 0, $count);
        $this->game->tokens->dbSetTokensLocation(
            $picked,
            "supply_crystal_$color",
            0,
            clienttranslate('${char_name} picks up ${count} ${token_name} from ${place_name}'),
            [
                "char_name" => $heroId,
                "count" => $count,
                "token_name" => "crystal_$color",
                "place_from" => $hex,
            ]
        );
        $delegate = match ($color) {
            "red" => "removeDamage",
            "green" => "gainMana",
            "yellow" => "gainXp",
        };
        $this->queue("{$count}{$delegate}", $owner);
    }
}
