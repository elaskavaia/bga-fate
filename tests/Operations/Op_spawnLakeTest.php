<?php

declare(strict_types=1);

/**
 * BGA #251438: Elven Arrows (in(TrollCaves):spawn(troll)) let the player place the troll on a lake.
 */
final class Op_spawnLakeTest extends AbstractOpTestCase {
    function getOperationType(): string {
        return "spawn(troll)";
    }

    public function testSpawnFromTrollCavesOffersLakeHex(): void {
        $heroHex = "hex_5_7";
        $hexMap = $this->game->hexMap;
        $this->assertEquals("TrollCaves", $hexMap->getHexNamedLocation($heroHex));
        $this->game->tokens->moveToken("hero_1", $heroHex);
        foreach ($hexMap->getAdjacentHexes($heroHex) as $hex) {
            $charId = $hexMap->getCharacterOnHex($hex);
            if ($charId !== null && $charId !== "hero_1") {
                $this->game->getMonster($charId)->moveTo("supply_monster", "");
            }
        }
        $hexMap->invalidateOccupancy();

        $lakes = array_values(
            array_filter(
                $hexMap->getAdjacentHexes($heroHex),
                fn($hex) => $hexMap->getHexTerrain($hex) === "lake" && $hexMap->getHexNamedLocation($hex) === ""
            )
        );
        $this->assertNotEmpty($lakes, "fixture: Troll Caves hex must border a plain lake");

        $targets = array_keys($this->createOp("spawn(troll)")->getPossibleMoves());
        // Pins buggy behavior for BGA #251438 - flip the assertion when the fix lands
        $this->assertContains($lakes[0], $targets);
    }
}
