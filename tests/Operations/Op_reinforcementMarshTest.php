<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_reinforcement;

use function Bga\Games\Fate\getPart;

// Marsh of Sorrow areas in pattern order: rows y=7..12, two hexes each (left, right)
final class Op_reinforcementMarshTest extends AbstractOpTestCase {
    function getOperationType(): string {
        return "reinforcement";
    }

    protected function setUp(): void {
        parent::setUp();
        $this->game->machine->push("turnStart");
        $this->game->machine->dispatchAll();
    }

    private function placeCardOnEmptyMap(string $cardId): void {
        foreach ($this->game->hexMap->getMonstersOnMap() as $monster) {
            $this->game->tokens->moveToken($monster["key"], "supply_monster");
        }
        $this->game->hexMap->invalidateOccupancy();
        /** @var Op_reinforcement */
        $op = $this->createOp("reinforcement", ["deck" => "deck_monster_yellow", "card" => $cardId]);
        $op->resolve();
        $this->game->hexMap->invalidateOccupancy();
    }

    private function getMarshPlacement(): array {
        $result = [];
        foreach ($this->game->hexMap->getHexesInLocation("MarshOfSorrow") as $hex) {
            $monster = $this->game->hexMap->getCharacterOnHex($hex);
            if ($monster !== null) {
                $result[$hex] = getPart($monster, 1);
            }
        }
        return $result;
    }

    public function testMarshAreaOrder(): void {
        $this->assertEquals(
            [
                "hex_16_7",
                "hex_17_7",
                "hex_16_8",
                "hex_17_8",
                "hex_16_9",
                "hex_17_9",
                "hex_15_10",
                "hex_16_10",
                "hex_14_11",
                "hex_15_11",
                "hex_13_12",
                "hex_14_12",
            ],
            $this->game->hexMap->getHexesInLocation("MarshOfSorrow")
        );
    }

    public function testBoneyardPlacement(): void {
        $this->placeCardOnEmptyMap("card_monster_17");
        // pins buggy behavior for BGA #255238: spawn string lacks a leading "-", flips when fixed
        // card art: S hex_16_9, S hex_15_10, I hex_16_10, S hex_15_11, S hex_14_12
        $this->assertEquals(
            [
                "hex_17_8" => "skeleton",
                "hex_17_9" => "skeleton",
                "hex_15_10" => "imp",
                "hex_14_11" => "skeleton",
                "hex_13_12" => "skeleton",
            ],
            $this->getMarshPlacement()
        );
    }

    public function testMarshingPlacement(): void {
        $this->placeCardOnEmptyMap("card_monster_21");
        // pins buggy behavior for BGA #255238: last imp is on hex_14_12 on the card, flips when fixed
        $placement = $this->getMarshPlacement();
        $this->assertEquals("imp", $placement["hex_13_12"] ?? null);
        $this->assertArrayNotHasKey("hex_14_12", $placement);
    }
}
