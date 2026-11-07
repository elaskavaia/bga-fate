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

    /** BGA #255238: Boneyard places on the hexes printed on the card. */
    public function testBoneyardPlacement(): void {
        $this->placeCardOnEmptyMap("card_monster_17");
        $this->assertEquals(
            [
                "hex_16_9" => "skeleton",
                "hex_15_10" => "skeleton",
                "hex_16_10" => "imp",
                "hex_15_11" => "skeleton",
                "hex_14_12" => "skeleton",
            ],
            $this->getMarshPlacement()
        );
    }

    /** BGA #255238: Marshing places its last imp on hex_14_12, as printed on the card. */
    public function testMarshingPlacement(): void {
        $this->placeCardOnEmptyMap("card_monster_21");
        $this->assertEquals(
            [
                "hex_16_7" => "draugr",
                "hex_17_7" => "skeleton",
                "hex_16_9" => "imp",
                "hex_17_9" => "imp",
                "hex_16_10" => "imp",
                "hex_14_12" => "imp",
            ],
            $this->getMarshPlacement()
        );
    }
}
