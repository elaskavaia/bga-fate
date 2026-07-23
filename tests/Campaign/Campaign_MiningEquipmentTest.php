<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Verifies the fix for BGA #233793: Boldur's Mining Equipment (card_equip_4_17)
 * can be clicked/used when standing adjacent to a mountain.
 *
 * Card data: r = spendDurab:c_mining, durability = 2.
 * Effect: gain 2 gold [XP] adjacent to 1 mountain / 3 gold adjacent to 3.
 *
 * Was broken because the gain half was the unimplemented Op_custom stub (always
 * ERR_NOT_APPLICABLE), which voided the whole paygain so useCard never offered it.
 * Now Op_c_mining implements the effect.
 */
class Campaign_MiningEquipmentTest extends CampaignBaseTest {
    private string $heroId;
    private string $cardId = "card_equip_4_17";

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());
        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    public function testMiningEquipmentClickableAdjacentToMountain(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($this->cardId, "tableau_$color");

        // Boldur on hex_5_8 (forest) - adjacent mountain at hex_5_7 (as in Orebiter test).
        $this->game->tokens->moveToken($this->heroId, "hex_5_8");
        $this->game->hexMap->invalidateOccupancy();

        // Sanity: hero on hex_5_8 is adjacent to mountain hex_5_7.
        $this->assertEquals("mountain", $this->game->hexMap->getHexTerrain("hex_5_7"));
        $neighbors = $this->game->hexMap->getAdjacentHexes("hex_5_8");
        $this->assertContains("hex_5_7", $neighbors, "hero hex must be adjacent to the mountain");

        $this->assertValidTarget($this->cardId, "Mining Equipment is clickable adjacent to a mountain (BGA #233793)");
    }

    public function testMiningEquipmentGainsTwoGoldAdjacentToOneMountain(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($this->heroId, "hex_5_8"); // adjacent to exactly 1 mountain
        $this->game->hexMap->invalidateOccupancy();

        $before = $this->countXp();
        $op = $this->game->machine->instantiateOperation("c_mining", $color);
        $op->action_resolve(["target" => "confirm"]);
        $this->assertEquals($before + 2, $this->countXp());
    }

    public function testMiningEquipmentGainsThreeGoldAdjacentToThreeMountains(): void {
        $color = $this->getActivePlayerColor();
        $hex = $this->findHexAdjacentToMountains(3);
        $this->assertNotNull($hex, "map should have a hex adjacent to 3 mountains");
        $this->game->tokens->moveToken($this->heroId, $hex);
        $this->game->hexMap->invalidateOccupancy();

        $before = $this->countXp();
        $op = $this->game->machine->instantiateOperation("c_mining", $color);
        $op->action_resolve(["target" => "confirm"]);
        $this->assertEquals($before + 3, $this->countXp());
    }

    private function findHexAdjacentToMountains(int $min): ?string {
        foreach (array_keys($this->game->material->getTokensWithPrefix("hex")) as $hex) {
            if ($this->game->hexMap->getHexTerrain($hex) === "mountain") {
                continue;
            }
            $mountains = 0;
            foreach ($this->game->hexMap->getAdjacentHexes($hex) as $adj) {
                if ($this->game->hexMap->getHexTerrain($adj) === "mountain") {
                    $mountains++;
                }
            }
            if ($mountains >= $min) {
                return $hex;
            }
        }
        return null;
    }

    public function testMiningEquipmentNotClickableAwayFromMountains(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($this->cardId, "tableau_$color");

        // Boldur on hex_7_9 (plains, no adjacent mountains).
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->game->hexMap->invalidateOccupancy();
        foreach ($this->game->hexMap->getAdjacentHexes("hex_7_9") as $hex) {
            $this->assertNotEquals("mountain", $this->game->hexMap->getHexTerrain($hex));
        }

        $this->assertNotValidTarget($this->cardId, "Mining Equipment needs an adjacent mountain to be useful");
    }
}
