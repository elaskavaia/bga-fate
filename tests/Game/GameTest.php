<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use Bga\Games\Fate\States\GameDispatch;
use PHPUnit\Framework\TestCase;

final class GameTest extends TestCase {
    private GameUT $game;
    function dispatchOneStep($done = null) {
        $game = $this->game;
        $state = $game->machine->dispatchOne();
        if ($state === null) {
            $state = GameDispatch::class;
        }
        if ($done !== null) {
            $this->assertEquals($done, $state);
        }
        $op = $this->game->machine->createTopOperationFromDbForOwner(null);
        return $op;
    }
    function dispatch($done = null) {
        $game = $this->game;
        $state = $game->machine->dispatchAll();
        if ($state === null) {
            $state = GameDispatch::class;
        }
        if ($done !== null) {
            $this->assertEquals($done, $state);
        }
        $op = $this->game->machine->createTopOperationFromDbForOwner(null);
        return $op;
    }
    function game(int $x = -1) {
        $game = new GameUT();
        if ($x === -1) {
            $game->init();
        } elseif ($x == 0) {
            $game->initWithHeros();
        } else {
            $game->initWithHero($x);
        }
        $this->game = $game;
        return $game;
    }

    protected function setUp(): void {
        $this->game();
    }

    /** Get the hero number assigned to a player color after setup */
    function getHeroNumber(string $color): int {
        return $this->game->getHeroNumber($color);
    }
    public function testGetAdjacentHexes() {
        $game = $this->game;

        // Center hex (9,9) should have 6 neighbors
        $adj = $game->hexMap->getAdjacentHexes("hex_9_9");
        $this->assertCount(6, $adj);
        sort($adj);
        $this->assertEquals(["hex_10_8", "hex_10_9", "hex_8_10", "hex_8_9", "hex_9_10", "hex_9_8"], $adj);

        // Edge hex should have fewer neighbors
        $adj = $game->hexMap->getAdjacentHexes("hex_1_9");
        $this->assertLessThan(6, count($adj));
        $this->assertContains("hex_2_9", $adj);
        $this->assertNotContains("hex_0_9", $adj); // off the board

        // Non-existent hex returns empty
        $this->assertEmpty($game->hexMap->getAdjacentHexes("hex_99_99"));
    }

    public function testGetMoveDistance() {
        $game = $this->game;

        // Same hex
        $this->assertEquals(0, $game->hexMap->getMoveDistance("hex_9_9", "hex_9_9"));
        // Grimheim
        $this->assertEquals(0, $game->hexMap->getMoveDistance("hex_9_9", "hex_10_9"));
        $this->assertEquals(0, $game->hexMap->getMoveDistance("hex_9_9", "hex_9_8"));
        // Two steps
        $this->assertEquals(2, $game->hexMap->getMoveDistance("hex_11_6", "hex_11_8"));
        // Invalid
        $this->assertEquals(-1, $game->hexMap->getMoveDistance("hex_9_9", "hex_99_99"));
        $this->assertEquals(-1, $game->hexMap->getMoveDistance("hex_99_99", "hex_9_9"));

        // Grimheim hexes: distance 0 between any two Grimheim hexes
        $this->assertEquals(0, $game->hexMap->getMoveDistance("hex_9_9", "hex_8_10")); // both Grimheim
        $this->assertEquals(0, $game->hexMap->getMoveDistance("hex_8_9", "hex_10_8")); // both Grimheim, far apart

        // Distance from Grimheim to adjacent non-Grimheim hex
        $this->assertEquals(1, $game->hexMap->getMoveDistance("hex_9_9", "hex_11_8")); // hex_11_8 is adjacent to hex_10_8 (Grimheim)
        $this->assertEquals(1, $game->hexMap->getMoveDistance("hex_11_8", "hex_8_9")); // symmetric
    }

    public function testGetReachableHexes() {
        $game = $this->game;

        // From a Grimheim hex, all Grimheim hexes are at distance 0
        $reachable = $game->hexMap->getReachableHexes("hex_9_9", 3);
        $this->assertArrayHasKey("hex_9_8", $reachable); // Grimheim
        $this->assertArrayHasKey("hex_10_8", $reachable); // Grimheim
        $this->assertEquals(0, $reachable["hex_9_8"]);

        // Adjacent non-Grimheim hex at distance 1
        $this->assertArrayHasKey("hex_11_8", $reachable);
        $this->assertEquals(1, $reachable["hex_11_8"]);

        // Start hex excluded
        $this->assertArrayNotHasKey("hex_9_9", $reachable);

        // Mountain hexes not reachable for heroes
        $this->assertArrayNotHasKey("hex_9_11", $reachable); // mountain

        // Far hexes not reachable
        $this->assertArrayNotHasKey("hex_9_1", $reachable);
    }

    public function testGetReachableHexesMountainForMonster() {
        $game = $this->game;

        // hex_13_4 is mountain — not reachable by hero
        $reachable = $game->hexMap->getReachableHexes("hex_12_4", 1, "hero");
        $this->assertArrayNotHasKey("hex_13_4", $reachable);

        // But reachable by monster
        $reachable = $game->hexMap->getReachableHexes("hex_12_4", 1, "monster");
        $this->assertArrayHasKey("hex_13_4", $reachable);
    }

    public function testGetReachableHexesBlockedByOccupied() {
        $game = $this->game;
        $game->tokens->createAllTokens();

        // Place a hero on hex_11_8
        $game->tokens->moveToken("hero_1", "hex_11_8");

        // hex_11_8 should not be reachable (occupied)
        $reachable = $game->hexMap->getReachableHexes("hex_10_8", 3);
        $this->assertArrayNotHasKey("hex_11_8", $reachable);

        // Hexes behind the occupied one should still be reachable via other paths
        $this->assertArrayHasKey("hex_12_8", $reachable);
    }

    public function testEnteringGrimheimEndsMovement() {
        $game = $this->game;

        // From hex_11_8 (adjacent to Grimheim hex_10_8), entering Grimheim ends movement
        $reachable = $game->hexMap->getReachableHexes("hex_11_8", 3);

        // All Grimheim hexes should be reachable
        $this->assertArrayHasKey("hex_10_8", $reachable); // Grimheim
        $this->assertArrayHasKey("hex_9_9", $reachable); // Grimheim
        $this->assertArrayHasKey("hex_8_9", $reachable); // Grimheim

        // But hexes on the other side of Grimheim should NOT be reachable
        // because entering Grimheim ends movement
        $this->assertArrayNotHasKey("hex_7_9", $reachable); // beyond Grimheim
        $this->assertArrayNotHasKey("hex_7_10", $reachable); // beyond Grimheim
    }

    public function testExitingGrimheimCostsOneStep() {
        $game = $this->game;

        // From Grimheim, adjacent non-mountain hexes should be at distance 1
        $reachable = $game->hexMap->getReachableHexes("hex_9_9", 3);

        // hex_11_8 is adjacent to Grimheim border hex hex_10_8
        $this->assertArrayHasKey("hex_11_8", $reachable);
        $this->assertEquals(1, $reachable["hex_11_8"]);

        // hex_7_9 is adjacent to Grimheim border hex hex_8_9
        $this->assertArrayHasKey("hex_7_9", $reachable);
        $this->assertEquals(1, $reachable["hex_7_9"]);
    }

    public function testSetupCreatesHeroCardsInDecks() {
        $game = $this->game;
        $game->setupGameTables();

        // Heroes are randomly assigned — look up which hero each player got
        $ph = $this->getHeroNumber(PCOLOR);
        $bh = $this->getHeroNumber(BCOLOR);

        // Starting cards should be on tableau
        $this->assertEquals("tableau_" . PCOLOR, $game->tokens->getTokenLocation("card_hero_{$ph}_1"));
        $this->assertEquals("tableau_" . PCOLOR, $game->tokens->getTokenLocation("card_ability_{$ph}_3"));
        $this->assertEquals("tableau_" . PCOLOR, $game->tokens->getTokenLocation("card_equip_{$ph}_15"));

        $this->assertEquals("tableau_" . BCOLOR, $game->tokens->getTokenLocation("card_hero_{$bh}_1"));
        $this->assertEquals("tableau_" . BCOLOR, $game->tokens->getTokenLocation("card_ability_{$bh}_3"));
        $this->assertEquals("tableau_" . BCOLOR, $game->tokens->getTokenLocation("card_equip_{$bh}_15"));
    }

    public function testSetupHeroCardsInCorrectDecks() {
        $game = $this->game;
        $game->setupGameTables();

        $ph = $this->getHeroNumber(PCOLOR);

        // Level II hero card should be in limbo (not a starting card)
        $this->assertEquals("limbo", $game->tokens->getTokenLocation("card_hero_{$ph}_2"));

        // Non-starting ability cards should be in ability deck
        $this->assertEquals("deck_ability_" . PCOLOR, $game->tokens->getTokenLocation("card_ability_{$ph}_9"));

        // Non-starting equip cards should be in equip deck — find one that exists for this hero
        // All heroes have equipment starting at num=16+
        $this->assertEquals("deck_equip_" . PCOLOR, $game->tokens->getTokenLocation("card_equip_{$ph}_16"));

        // Event cards — find an event with count>1 for this hero
        // All heroes have events; find first indexed event token
        $eventTokens = $game->tokens->getTokensOfTypeInLocation("card_event_{$ph}", "deck_event_" . PCOLOR);
        $this->assertNotEmpty($eventTokens, "Should have event cards in deck");
    }

    public function testAbilityDeckHasNoLevelIICards() {
        $game = $this->game;
        $game->setupGameTables();

        $ph = $this->getHeroNumber(PCOLOR);
        $deckCards = $game->tokens->getTokensOfTypeInLocation("card_ability_{$ph}", "deck_ability_" . PCOLOR);
        foreach ($deckCards as $cardId => $info) {
            $num = (int) \Bga\Games\Fate\getPart($cardId, 3);
            $this->assertEquals(1, $num % 2, "Level II card $cardId should not be in ability deck");
        }

        // Level II ability cards should be in limbo
        $this->assertEquals("limbo", $game->tokens->getTokenLocation("card_ability_{$ph}_10"));
    }

    public function testUpgradeCostMarkerOnTableauWithCost5() {
        $game = $this->game;
        $game->setupGameTables();

        $markerId = "marker_" . PCOLOR . "_3";
        $this->assertEquals("tableau_" . PCOLOR, $game->tokens->getTokenLocation($markerId));
        $this->assertEquals(5, (int) $game->tokens->getTokenState($markerId));
    }

    public function testSetupUnusedHeroesInLimbo() {
        $game = $this->game;
        $game->setupGameTables();

        $ph = $this->getHeroNumber(PCOLOR);
        $bh = $this->getHeroNumber(BCOLOR);
        $usedHeros = [$ph, $bh];

        // 2 players: unused heroes should be in limbo
        for ($i = 1; $i <= 4; $i++) {
            if (!in_array($i, $usedHeros)) {
                $this->assertEquals("limbo", $game->tokens->getTokenLocation("hero_$i"));
                // No cards created for unused heroes
                $this->assertNull($game->tokens->getTokenInfo("card_hero_{$i}_1"));
            }
        }
    }

    public function testSetupTokensUseHeroColor() {
        $game = $this->game;
        $heroColors = $game->getAvailColors();
        // Start with colors in default order (as BGA assigns them)
        $game->_setPlayerBasicInfoFromColors([$heroColors[0], $heroColors[1]]);
        // Assign hero 2 to player 1 — player 1 should get Alva's blue, not Bjorn's green
        $game->setHeroOrder([2, 1, 3, 4]);
        $game->setupGameTables();

        $alvaColor = $heroColors[1]; // hero 2 = Alva blue
        // Player 1 got hero 2, so their color is now Alva's blue
        // getHeroTokenId and getActionsTaken should work with the reassigned color
        $heroId = $game->getHeroTokenId($alvaColor);
        $this->assertEquals("hero_2", $heroId, "Player 1 should have hero_2 (Alva)");
        $hero = $game->getHeroById($heroId);
        // getActionsTaken reads marker tokens — must not crash (the BGA bug was null here)
        $taken = $hero->getActionsTaken();
        $this->assertIsArray($taken);
    }

    public function testSetupEventCardInHand() {
        $game = $this->game;
        $game->setupGameTables();

        $hand = $game->tokens->getTokensOfTypeInLocation("card", "hand_" . PCOLOR);
        $this->assertCount(1, $hand, "Hero should have exactly 1 card in hand after setup");
    }

    public function testinstantiateAllOperations() {
        $this->game(0);
        $token_types = $this->game->material->get();
        $tested = [];
        foreach ($token_types as $key => $info) {
            $this->assertTrue(!!$key);
            if (!str_starts_with($key, "Op_")) {
                continue;
            }
            //echo "testing op $key\n";
            $this->subTestOp($key, $info);
            $tested[$key] = 1;
        }

        $dir = dirname(__FILE__, 3);
        $files = glob("$dir/modules/php/Operations/*.php");

        foreach ($files as $file) {
            $base = basename($file);
            $this->assertTrue(!!$base);
            if (!str_starts_with($base, "Op_")) {
                continue;
            }
            $mne = preg_replace("/Op_(.*).php/", "\\1", $base);
            $key = "Op_{$mne}";
            if (str_contains($key, "Base")) {
                continue;
            }
            if (array_key_exists($key, $tested)) {
                continue;
            }
            //echo "testing op $key\n";
            $this->subTestOp($key, ["type" => $mne]);
        }
    }

    public function testMoveCrystalsGain() {
        $game = $this->game;
        $game->tokens->createAllTokens();

        $supply = $game->tokens->countTokensInLocation("supply_crystal_yellow");
        $tableau = $game->tokens->countTokensInLocation("tableau_" . PCOLOR);

        $game->effect_moveCrystals("hero_1", "yellow", 3, "tableau_" . PCOLOR);

        $this->assertEquals($supply - 3, $game->tokens->countTokensInLocation("supply_crystal_yellow"));
        $this->assertEquals($tableau + 3, $game->tokens->countTokensInLocation("tableau_" . PCOLOR));
    }

    public function testMoveCrystalsPay() {
        $game = $this->game;
        $game->tokens->createAllTokens();

        // Give player some crystals first
        $game->effect_moveCrystals("hero_1", "yellow", 5, "tableau_" . PCOLOR);
        $supply = $game->tokens->countTokensInLocation("supply_crystal_yellow");
        $tableau = $game->tokens->countTokensInLocation("tableau_" . PCOLOR);

        $game->effect_moveCrystals("hero_1", "yellow", -3, "tableau_" . PCOLOR);

        $this->assertEquals($supply + 3, $game->tokens->countTokensInLocation("supply_crystal_yellow"));
        $this->assertEquals($tableau - 3, $game->tokens->countTokensInLocation("tableau_" . PCOLOR));
    }

    public function testMoveCrystalsZeroDoesNothing() {
        $game = $this->game;
        $game->tokens->createAllTokens();

        $supply = $game->tokens->countTokensInLocation("supply_crystal_red");
        $game->effect_moveCrystals("hero_1", "red", 0, "hex_9_9");
        $this->assertEquals($supply, $game->tokens->countTokensInLocation("supply_crystal_red"));
    }

    public function testMoveCrystalsPayInsufficientThrows() {
        $game = $this->game;
        $game->tokens->createAllTokens();

        // Tableau starts empty — paying should throw
        $this->expectException(\Bga\GameFramework\UserException::class);
        $game->effect_moveCrystals("hero_1", "green", -1, "tableau_" . PCOLOR);
    }

    public function testinstantiateAllEventCardOperations() {
        $this->game(0);

        foreach ($this->game->material->get() as $key => $info) {
            if (!str_starts_with($key, "card_event_")) {
                continue;
            }
            $r = $info["r"] ?? "";
            $this->assertNotEmpty($r, "r should be set for event");
            //echo "testing event card $key r=$r\n";
            $op = $this->game->machine->instantiateOperation($r, PCOLOR, ["card" => $key]);
            $this->assertNotNull($op, "Failed to instantiate op '$r' for $key");
        }
    }

    public function testinstantiateAllEquipCardOperations() {
        $this->game(0);

        foreach ($this->game->material->get() as $key => $info) {
            if (!str_starts_with($key, "card_equip_")) {
                continue;
            }
            $r = $info["r"] ?? "";
            if ($r === "") {
                continue;
            }
            //echo "testing equip card $key r=$r\n";
            $op = $this->game->machine->instantiateOperation($r, PCOLOR, ["card" => $key]);
            $this->assertNotNull($op, "Failed to instantiate op '$r' for $key");
        }
    }

    public function testinstantiateAllAbilityCardOperations() {
        $this->game(0);
        foreach ($this->game->material->get() as $key => $info) {
            if (!str_starts_with($key, "card_ability_")) {
                continue;
            }
            $r = $info["r"] ?? "";
            if ($r === "") {
                continue;
            }
            $op = $this->game->machine->instantiateOperation($r, PCOLOR, ["card" => $key]);
            $this->assertNotNull($op, "Failed to instantiate op '$r' for $key");
        }
    }

    public function testinstantiateAllHeroCardOperations() {
        $this->game(0);

        foreach ($this->game->material->get() as $key => $info) {
            if (!str_starts_with($key, "card_hero_")) {
                continue;
            }
            $r = $info["r"] ?? "";
            if ($r === "") {
                continue;
            }
            $op = $this->game->machine->instantiateOperation($r, PCOLOR, ["card" => $key]);
            $this->assertNotNull($op, "Failed to instantiate op '$r' for $key");
        }
    }

    function subTestOp($key, $info = []) {
        $type = substr($key, 3);
        $this->assertTrue(!!$type);

        /** @var \Bga\Games\Fate\OpCommon\Operation */
        $op = $this->game->machine->instantiateOperation($type, PCOLOR);

        $args = $op->getArgs();
        $ttype = $args["ttype"] ?? "";
        $this->assertTrue($ttype != "", "empty ttype for $key");

        $this->assertFalse(str_contains($op->getOpName(), "?"), $op->getOpName());
        $this->assertFalse($op->getOpName() == $op->getType(), "No name set for operation $key");
        return $op;
    }

    // -------------------------------------------------------------------------
    // evaluateExpression — healthRem, adj terms
    // -------------------------------------------------------------------------

    private function setupHeroAndTokens(): void {
        $this->game->tokens->createAllTokens();
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    public function testHealthRemFullHealth(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // Goblin health=2, no damage → healthRem=2
        $result = $this->game->evaluateExpression("healthRem", PCOLOR, "monster_goblin_1");
        $this->assertEquals(2, $result);
    }

    public function testHealthRemWithDamage(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->tokens->moveToken("crystal_red_1", "monster_brute_1");
        // Brute health=3, 1 damage → healthRem=2
        $result = $this->game->evaluateExpression("healthRem", PCOLOR, "monster_brute_1");
        $this->assertEquals(2, $result);
    }

    public function testHealthRemExpression(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->tokens->moveToken("crystal_red_1", "monster_brute_1");
        // healthRem<=2 should be true (2<=2)
        $result = $this->game->evaluateExpression("healthRem<=2", PCOLOR, "monster_brute_1");
        $this->assertEquals(1, $result);
    }

    public function testHealthRemExpressionFalse(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        // Brute health=3, no damage → healthRem=3, 3<=2 is false
        $result = $this->game->evaluateExpression("healthRem<=2", PCOLOR, "monster_brute_1");
        $this->assertEquals(0, $result);
    }

    public function testAdjTermTrue(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // hero_1 at hex_11_8, hex_12_8 is adjacent
        $result = $this->game->evaluateExpression("adj", PCOLOR, "monster_goblin_1");
        $this->assertEquals(1, $result);
    }

    public function testAdjTermFalse(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        // hero_1 at hex_11_8, hex_13_7 is 2 hexes away
        $result = $this->game->evaluateExpression("adj", PCOLOR, "monster_goblin_1");
        $this->assertEquals(0, $result);
    }

    public function testRangeTermAdjacent(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        // hex_12_8 is 1 hex away from hero at hex_11_8
        $this->assertEquals(1, $this->game->evaluateExpression("range", PCOLOR, "monster_goblin_1"));
    }

    public function testRangeTermDistance2(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_8");
        $this->assertEquals(2, $this->game->evaluateExpression("range", PCOLOR, "monster_goblin_1"));
    }

    public function testRangeTermPredicate(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_8");
        // range>=2 should be true at distance 2
        $this->assertEquals(1, $this->game->evaluateExpression("range>=2", PCOLOR, "monster_goblin_1"));
        // And false at distance 1
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->assertEquals(0, $this->game->evaluateExpression("range>=2", PCOLOR, "monster_goblin_1"));
    }

    public function testCountMonsterXpReadsBruteFromAttackHex(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        // Brute XP = 2
        $this->assertEquals(2, $this->game->evaluateExpression("countMonsterXp", PCOLOR));
    }

    public function testCountMonsterXpReadsTroll(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        // Troll XP = 3
        $this->assertEquals(3, $this->game->evaluateExpression("countMonsterXp", PCOLOR));
    }

    public function testCountMonsterXpZeroWhenAttackHexEmpty(): void {
        $this->setupHeroAndTokens();
        // No marker_attack placed → no attack hex
        $this->assertEquals(0, $this->game->evaluateExpression("countMonsterXp", PCOLOR));
    }

    public function testCountMonsterXpAddsBonusYellowCrystals(): void {
        $this->setupHeroAndTokens();
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("marker_attack", "hex_12_8");
        // Goblin base XP=1, plus 2 bonus yellow crystals (e.g. Prey markers) → 3
        $this->game->tokens->moveToken("crystal_yellow_1", "monster_goblin_1");
        $this->game->tokens->moveToken("crystal_yellow_2", "monster_goblin_1");
        $this->assertEquals(3, $this->game->evaluateExpression("countMonsterXp", PCOLOR));
    }

    public function testCountDiceEmptyWhenDisplayClear(): void {
        $this->setupHeroAndTokens();
        $this->assertEquals(0, $this->game->evaluateExpression("countDice", PCOLOR));
    }

    public function testCountDiceCountsAllFaces(): void {
        $this->setupHeroAndTokens();
        // Three dice on display_battle, faces don't matter for countDice
        $this->game->tokens->moveToken("die_attack_1", "display_battle", 1);
        $this->game->tokens->moveToken("die_attack_2", "display_battle", 3);
        $this->game->tokens->moveToken("die_attack_3", "display_battle", 5);
        $this->assertEquals(3, $this->game->evaluateExpression("countDice", PCOLOR));
    }

    public function testCloserToGrimheimTrue(): void {
        $this->setupHeroAndTokens();
        // Hero at hex_11_8, place goblin closer to Grimheim (hex_9_8 is nearer)
        $this->game->tokens->moveToken("monster_goblin_1", "hex_9_8");
        $result = $this->game->evaluateExpression("closerToGrimheim", PCOLOR, "monster_goblin_1");
        $this->assertEquals(1, $result);
    }

    public function testCloserToGrimheimFalse(): void {
        $this->setupHeroAndTokens();
        // Hero at hex_11_8, place goblin farther from Grimheim
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $result = $this->game->evaluateExpression("closerToGrimheim", PCOLOR, "monster_goblin_1");
        $this->assertEquals(0, $result);
    }

    public function testCloserToGrimheimSameDistance(): void {
        $this->setupHeroAndTokens();
        // Place goblin at same distance as hero — should be false (strictly closer)
        $this->game->tokens->moveToken("monster_goblin_1", "hex_10_8");
        $heroDist = $this->game->hexMap->getDistanceMapToGrimheim()["hex_11_8"];
        $monsterDist = $this->game->hexMap->getDistanceMapToGrimheim()["hex_10_8"];
        $expected = $monsterDist < $heroDist ? 1 : 0;
        $result = $this->game->evaluateExpression("closerToGrimheim", PCOLOR, "monster_goblin_1");
        $this->assertEquals($expected, $result);
    }

    public function testCloserToGrimheimInCompoundExpression(): void {
        $this->setupHeroAndTokens();
        // Goblin (rank=1) closer to Grimheim
        $this->game->tokens->moveToken("monster_goblin_1", "hex_9_8");
        $result = $this->game->evaluateExpression("rank<=2 and closerToGrimheim", PCOLOR, "monster_goblin_1");
        $this->assertEquals(1, $result);
    }

    // -------------------------------------------------------------------------
    // getGameProgression
    // -------------------------------------------------------------------------

    public function testGameProgressionAtStart(): void {
        $this->game->tokens->createAllTokens();
        $this->assertEquals(0, $this->game->getGameProgression());
    }

    public function testGameProgressionMidGame(): void {
        $this->game->tokens->createAllTokens();
        $this->game->tokens->moveToken("rune_stone", "timetrack_1", 6);
        $this->assertEquals(50, $this->game->getGameProgression());
    }

    // -------------------------------------------------------------------------
    // countRunes
    // -------------------------------------------------------------------------

    public function testCountRunesNoDice(): void {
        $game = $this->game;
        $game->tokens->createAllTokens();
        // No dice on display_battle — should return 0
        $this->assertEquals(0, $game->countRunes());
    }

    public function testCountRunesNoneAreRunes(): void {
        $game = $this->game;
        $game->tokens->createAllTokens();
        // Place dice on display_battle with non-rune sides (1=miss, 5=hit)
        $game->tokens->moveToken("die_attack_1", "display_battle", 1);
        $game->tokens->moveToken("die_attack_2", "display_battle", 5);
        $this->assertEquals(0, $game->countRunes());
    }

    public function testCountRunesOneRune(): void {
        $game = $this->game;
        $game->tokens->createAllTokens();
        // Side 3 = rune
        $game->tokens->moveToken("die_attack_1", "display_battle", 3);
        $game->tokens->moveToken("die_attack_2", "display_battle", 5);
        $this->assertEquals(1, $game->countRunes());
    }

    public function testCountRunesMultipleRunes(): void {
        $game = $this->game;
        $game->tokens->createAllTokens();
        $game->tokens->moveToken("die_attack_1", "display_battle", 3);
        $game->tokens->moveToken("die_attack_2", "display_battle", 3);
        $game->tokens->moveToken("die_attack_3", "display_battle", 6); // hit
        $this->assertEquals(2, $game->countRunes());
    }

    public function testCountRunesAllSides(): void {
        $game = $this->game;
        $game->tokens->createAllTokens();
        // Place one die per side (1-6), only side 3 is rune
        for ($i = 1; $i <= 6; $i++) {
            $game->tokens->moveToken("die_attack_$i", "display_battle", $i);
        }
        $this->assertEquals(1, $game->countRunes());
    }

    // -------------------------------------------------------------------------
    // evaluateTerm / evaluateExpression — countRunes
    // -------------------------------------------------------------------------

    public function testEvaluateTermCountRunesZero(): void {
        $game = $this->game;
        $game->tokens->createAllTokens();
        // No dice on display → countRunes evaluates to 0
        $result = $game->evaluateExpression("countRunes", PCOLOR, "monster_goblin_1");
        $this->assertEquals(0, $result);
    }

    public function testEvaluateTermCountRunesWithDice(): void {
        $game = $this->game;
        $game->tokens->createAllTokens();
        $game->tokens->moveToken("die_attack_1", "display_battle", 3); // rune
        $game->tokens->moveToken("die_attack_2", "display_battle", 5); // hit
        $game->tokens->moveToken("die_attack_3", "display_battle", 3); // rune
        $result = $game->evaluateExpression("countRunes", PCOLOR, "monster_goblin_1");
        $this->assertEquals(2, $result);
    }

    public function testEvaluateTermCountRunesInArithmetic(): void {
        $game = $this->game;
        $game->tokens->createAllTokens();
        $game->tokens->moveToken("die_attack_1", "display_battle", 3); // rune
        $game->tokens->moveToken("die_attack_2", "display_battle", 3); // rune
        // countRunes + 1 = 3
        $result = $game->evaluateExpression("countRunes+1", PCOLOR, "monster_goblin_1");
        $this->assertEquals(3, $result);
    }

    public function testEvaluateTermCountRunesComparison(): void {
        $game = $this->game;
        $game->tokens->createAllTokens();
        $game->tokens->moveToken("die_attack_1", "display_battle", 3); // rune
        // countRunes>=1 → true (1)
        $this->assertEquals(1, $game->evaluateExpression("countRunes>=1", PCOLOR, "monster_goblin_1"));
        // countRunes>=2 → false (0)
        $this->assertEquals(0, $game->evaluateExpression("countRunes>=2", PCOLOR, "monster_goblin_1"));
    }

    public function testEvaluateTermCountRunesNoContext(): void {
        $game = $this->game;
        $game->tokens->createAllTokens();
        $game->tokens->moveToken("die_attack_1", "display_battle", 3); // rune
        // countRunes doesn't need context — should still work without one
        $result = $game->evaluateExpression("countRunes", PCOLOR);
        $this->assertEquals(1, $result);
    }

    public function testGameProgressionAtEnd(): void {
        $this->game->tokens->createAllTokens();
        $this->game->tokens->moveToken("rune_stone", "timetrack_1", 12);
        $this->assertEquals(100, $this->game->getGameProgression());
    }

    // -------------------------------------------------------------------------
    // evaluateExpression — bareword monster-tribe term (matches getPart($context, 1))
    // -------------------------------------------------------------------------

    public function testMonsterTribeBarewordMatchesBrute(): void {
        $this->setupHeroAndTokens();
        $this->assertEquals(1, $this->game->evaluateExpression("brute or skeleton", PCOLOR, "monster_brute_1"));
    }

    public function testMonsterTribeBarewordMatchesSkeleton(): void {
        $this->setupHeroAndTokens();
        $this->assertEquals(1, $this->game->evaluateExpression("brute or skeleton", PCOLOR, "monster_skeleton_1"));
    }

    public function testMonsterTribeBarewordRejectsGoblin(): void {
        $this->setupHeroAndTokens();
        $this->assertEquals(0, $this->game->evaluateExpression("brute or skeleton", PCOLOR, "monster_goblin_1"));
    }
}
