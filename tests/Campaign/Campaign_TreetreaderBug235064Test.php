<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Reproduction tests for BGA bug #235064 ("Alva's Treetreader multiple bugs").
 *
 * Treetreader I (card_ability_2_5) / II (card_ability_2_6):
 *   r=(spendUse:move(forest))/(in(forest):spendUse:move), effect bullets:
 *     <li>Move into an adjacent forest area</li>   -> choice_0, branch 0 = move(forest)
 *     <li>Move out of a forest area</li>            -> choice_1, branch 1 = in(forest):move
 *
 * Op_or labels choice_$i from effect_($i+1), so the bullets must be listed in the same
 * order as the branches. They were reversed, making each button do the opposite of its
 * label. "Out of a forest" is an unfiltered move by design (FORUM.md: standing in a
 * forest next to Grimheim, you may step out into Grimheim); it overlaps branch 0 on
 * forest neighbours, which is legal under either bullet.
 */
class Campaign_TreetreaderBug235064Test extends CampaignBaseTest {
    // Forest hex in the OgreValley cluster: ring has 3 forest neighbors (hex_3_8, hex_4_8,
    // hex_2_10) and 3 non-forest neighbors (hex_4_9, hex_2_9, hex_3_10), no mountains.
    // Multiple forest neighbors means move(forest) prompts instead of auto-resolving.
    private const FOREST_HEX = "hex_3_9";

    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([2]); // Solo Alva
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
        $this->clearEquipDecks();
    }

    private function placeTreetreader(string $cardId): string {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken($cardId, "tableau_$color", 0);
        $this->game->tokens->moveToken($this->heroId, self::FOREST_HEX);
        return $cardId;
    }

    private function placeTreetreaderI(): string {
        return $this->placeTreetreader("card_ability_2_5");
    }

    private function nonForest(array $hexes): array {
        return array_values(array_filter($hexes, fn($h) => is_string($h) && $this->game->hexMap->getHexTerrain($h) !== "forest"));
    }

    private function forestOnly(array $hexes): array {
        return array_values(array_filter($hexes, fn($h) => is_string($h) && $this->game->hexMap->getHexTerrain($h) === "forest"));
    }

    /** Sanity: the chosen forest hex really does have both forest and non-forest neighbors. */
    public function testForestHexHasMixedNeighbors(): void {
        $hero = $this->game->getHeroById($this->heroId);
        $ring = array_keys($this->game->hexMap->getReachableHexes(self::FOREST_HEX, 1, $hero));
        $this->assertGreaterThanOrEqual(2, count($this->forestOnly($ring)), "need 2+ forest neighbors so move(forest) prompts");
        $this->assertNotEmpty($this->nonForest($ring), "need a non-forest neighbor");
    }

    // --- Claim 3: "Move INTO a forest" button offered non-forest tiles too ---
    public function testMoveIntoForestOffersOnlyForestBug235064(): void {
        $cardId = $this->placeTreetreaderI();

        $this->respond($cardId);

        $info = $this->getOpArgs()["info"] ?? [];
        $this->assertStringContainsString("Move into", $info["choice_0"]["name"] ?? "");

        $this->respond("choice_0");
        $offered = $this->getOpArgs()["target"] ?? [];

        $this->assertNotEmpty($this->forestOnly($offered), "move-into should offer forest destinations");
        $this->assertEmpty($this->nonForest($offered), "'Move into a forest' must not offer non-forest destinations");
    }

    // --- Claim 2: "Move OUT of a forest" button offered only forest tiles ---
    public function testMoveOutOfForestOffersNonForestBug235064(): void {
        $cardId = $this->placeTreetreaderI();

        $this->respond($cardId);

        $info = $this->getOpArgs()["info"] ?? [];
        $this->assertStringContainsString("Move out", $info["choice_1"]["name"] ?? "");

        $this->respond("choice_1");
        $offered = $this->getOpArgs()["target"] ?? [];

        $this->assertNotEmpty($this->nonForest($offered), "'Move out of a forest' must offer non-forest destinations");
        $this->assertNotEmpty(
            $this->forestOnly($offered),
            "out branch is unfiltered by design (FORUM.md:5305), forest neighbors stay offered"
        );
    }

    // --- Claim 1: Treetreader can be activated unlimited times in one turn ---
    // Ability cards may only be used once per turn (RULES.md line 185/420), enforced by
    // the spendUse prefix which flips the card token to used.
    public function testTreetreaderCanOnlyBeUsedOncePerTurnBug235064(): void {
        $cardId = $this->placeTreetreaderI();

        $this->assertEquals(0, $this->game->tokens->getTokenState($cardId), "card starts unused");
        $this->assertValidTarget($cardId, "Treetreader offered as a free action");

        $this->respond($cardId);
        $this->respond("choice_0");

        $offered = $this->getOpArgs()["target"] ?? [];
        $forestTargets = $this->forestOnly($offered);
        $this->assertNotEmpty($forestTargets, "need a forest hex to hop to");
        $this->respond($forestTargets[0]);

        $this->assertEquals("PlayerTurn", $this->getStateArgs()["name"], "back at PlayerTurn");
        $this->assertEquals(1, $this->game->tokens->getTokenState($cardId), "Treetreader marked used after one activation");
        $this->assertNotValidTarget($cardId, "Treetreader no longer offered this turn");
    }

    // Treetreader II is a separate CSV row carrying its own copy of the same `r`, so it gets
    // its own label/branch check. Also pins that the `on=custom` passive is NOT gated by the
    // use: spendUse resolves before the move, so the card is already marked used by the time
    // the forest-entry heal fires.
    public function testTreetreaderIIBranchesAndPassiveBug235064(): void {
        $cardId = $this->placeTreetreader("card_ability_2_6");
        $this->game->effect_moveCrystals("supply_red", "red", 2, $this->heroId, ["message" => ""]);
        $this->assertEquals(2, $this->countDamage($this->heroId));

        $this->respond($cardId);
        $this->assertStringContainsString("Move into", $this->getOpArgs()["info"]["choice_0"]["name"] ?? "");

        $this->respond("choice_0");
        $offered = $this->getOpArgs()["target"] ?? [];
        $this->assertNotEmpty($offered, "move-into should offer forest destinations");
        $this->assertEmpty($this->nonForest($offered), "'Move into a forest' must not offer non-forest destinations");
        $this->respond($offered[0]);

        $this->assertEquals(1, $this->game->tokens->getTokenState($cardId), "Treetreader II marked used after one activation");
        $this->assertNotValidTarget($cardId, "Treetreader II no longer offered this turn");
        $this->assertEquals(1, $this->countDamage($this->heroId), "forest-entry passive still heals with the use spent");
    }
}
