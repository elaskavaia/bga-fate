<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Reproduction tests for BGA bug #235064 ("Alva's Treetreader multiple bugs").
 *
 * Treetreader I (card_ability_2_5) / II (card_ability_2_6):
 *   r=(in(forest):spendUse:move)/(spendUse:move(forest)), effect bullets:
 *     <li>Move into an adjacent forest area</li>   -> choice_0 label, drives branch 0
 *     <li>Move out of a forest area</li>            -> choice_1 label, drives branch 1
 *
 * The two effect bullets are in the REVERSE order of the two DSL branches, so each
 * button drives the opposite behavior of its label:
 *   - "Move into an adjacent forest area" runs in(forest):move (an UNFILTERED move) ->
 *     it offers every adjacent hex, not just forest.
 *   - "Move out of a forest area" runs move(forest) (a forest-ONLY move) -> it forces
 *     you back into a forest instead of out of it.
 * The assertions marked "BUG:" pin that CURRENT behavior and must be flipped when it is fixed.
 *
 * The once-per-turn limit (spendUse) is fixed and verified.
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

    // --- Claim 3: "Move INTO a forest" button offers non-forest tiles too ---
    // The button labeled "Move into an adjacent forest area" (choice_0) actually runs
    // branch 0 = in(forest):move (an unfiltered move), so it offers EVERY adjacent hex.
    public function testMoveIntoForestButtonOffersNonForestBug235064(): void {
        $cardId = $this->placeTreetreaderI();

        $this->respond($cardId);

        $info = $this->getOpArgs()["info"] ?? [];
        $this->assertStringContainsString("Move into", $info["choice_0"]["name"] ?? "");

        $this->respond("choice_0");
        $offered = $this->getOpArgs()["target"] ?? [];

        $this->assertNotEmpty($offered, "sanity: move-into should offer something");
        // BUG: a "move into a forest" action offers non-forest destinations. Once fixed,
        // change assertNotEmpty -> assertEmpty (only forest hexes should be offered).
        $this->assertNotEmpty($this->nonForest($offered), "buggy: 'Move into a forest' offers non-forest destinations");
    }

    // --- Claim 2: "Move OUT of a forest" button offers only forest tiles ---
    // The button labeled "Move out of a forest area" (choice_1) actually runs
    // branch 1 = move(forest), so it offers ONLY forest hexes (keeping you in the forest).
    public function testMoveOutOfForestButtonOffersOnlyForestBug235064(): void {
        $cardId = $this->placeTreetreaderI();

        $this->respond($cardId);

        $info = $this->getOpArgs()["info"] ?? [];
        $this->assertStringContainsString("Move out", $info["choice_1"]["name"] ?? "");

        $this->respond("choice_1");
        $offered = $this->getOpArgs()["target"] ?? [];

        $this->assertNotEmpty($offered, "sanity: move-out should offer something");
        $this->assertNotEmpty($this->forestOnly($offered), "move-out offered no forest either");
        // BUG: a "move out of a forest" action offers ONLY forest destinations. Once fixed,
        // change assertEmpty -> assertNotEmpty (non-forest hexes should be offered).
        $this->assertEmpty($this->nonForest($offered), "buggy: 'Move out of a forest' offers only forest destinations");
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

    // Second row of the same data change, driven through the other branch. Also pins that
    // the `on=custom` passive is NOT gated by the use: spendUse resolves before the move,
    // so the card is already marked used by the time the forest-entry heal fires.
    public function testTreetreaderIIOncePerTurnAndPassiveStillHealsBug235064(): void {
        $cardId = $this->placeTreetreader("card_ability_2_6");
        $this->game->effect_moveCrystals("supply_red", "red", 2, $this->heroId, ["message" => ""]);
        $this->assertEquals(2, $this->countDamage($this->heroId));

        $this->respond($cardId);
        $this->respond("choice_1");

        $forestTargets = $this->forestOnly($this->getOpArgs()["target"] ?? []);
        $this->assertNotEmpty($forestTargets, "need a forest hex to hop to");
        $this->respond($forestTargets[0]);

        $this->assertEquals(1, $this->game->tokens->getTokenState($cardId), "Treetreader II marked used after one activation");
        $this->assertNotValidTarget($cardId, "Treetreader II no longer offered this turn");
        $this->assertEquals(1, $this->countDamage($this->heroId), "forest-entry passive still heals with the use spent");
    }
}
