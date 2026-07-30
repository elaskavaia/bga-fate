<?php

declare(strict_types=1);

use Bga\Games\Fate\Model\Hero;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

/**
 * BGA #234817: reporter says a hero Move action ended as soon as the hero
 * stepped into a hex adjacent to a monster, with movement and open hexes
 * remaining. Rules: only MONSTER movement stops on adjacency to a hero; a
 * hero may move past/around a monster (just not THROUGH the occupied hex).
 *
 * This probes the shared core of the move action (HexMap::getReachableHexes,
 * used by Op_move and Op_moveStep). Open plains field x=11..15,y=7..9.
 * Hero at hex_12_8, monster at hex_13_8 (adjacent, east), budget 4.
 */
final class HeroMoveAdjacentMonsterBug234817Test extends TestCase {
    private GameUT $game;
    private Hero $hero;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        $this->game->setPlayersNumber(1);
        $this->game->tokens->moveToken("card_hero_1", "tableau_" . PCOLOR);
        $this->hero = $this->game->getHero(PCOLOR);

        // Isolate the field: clear all characters off the map, then place ours.
        foreach (["hero_1", "hero_2", "hero_3", "hero_4"] as $hId) {
            $this->game->tokens->moveToken($hId, "limbo");
        }
        foreach ($this->game->tokens->getTokensOfTypeInLocation(null, "hex%") as $token) {
            if (str_starts_with($token["key"], "monster")) {
                $this->game->tokens->moveToken($token["key"], "limbo");
            }
        }
        $this->game->tokens->moveToken("hero_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_8");
        $this->game->hexMap->invalidateOccupancy();
    }

    public function testHeroCanMoveAroundAndPastAdjacentMonster(): void {
        // Budget 4 (Embla). Monster at hex_13_8 sits directly east of the hero.
        $reachable = $this->game->hexMap->getReachableHexes("hex_12_8", 4, $this->hero);

        // Sanity: cannot stop ON the monster's occupied hex (can't move through it).
        $this->assertArrayNotHasKey("hex_13_8", $reachable, "occupied monster hex is not a valid destination");

        // The "stepped adjacent" hexes around the monster are reachable.
        $this->assertArrayHasKey("hex_12_9", $reachable, "adjacent-to-monster hex reachable (1 step)");
        $this->assertArrayHasKey("hex_13_9", $reachable, "adjacent-to-monster hex reachable going around (2 steps)");

        // The decisive assertions: hexes BEYOND the monster, not adjacent to it,
        // are still reachable -> movement does NOT stop on becoming adjacent.
        $this->assertArrayHasKey("hex_14_9", $reachable, "hex beyond the monster reachable (3 steps around)");
        $this->assertArrayHasKey("hex_15_9", $reachable, "farther hex beyond the monster reachable (4 steps around)");
        $this->assertArrayHasKey("hex_14_8", $reachable, "hex directly behind the monster reachable via north detour (4 steps)");
    }
}
