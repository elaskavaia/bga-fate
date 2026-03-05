<?php

declare(strict_types=1);

require_once __DIR__ . "/GameTest.php";

use Bga\Games\Fate\Operations\Op_actionAttack;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Tests\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_actionAttackTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR: strength 2, starting ability (Sure Shot, no +str), starting equip (+1)
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_ability_1_3", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_equip_1_15", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(): Op_actionAttack {
        /** @var Op_actionAttack */
        $op = $this->game->machine->instanciateOperation("actionAttack", PCOLOR);
        return $op;
    }

    // -------------------------------------------------------------------------
    // getPossibleMoves
    // -------------------------------------------------------------------------

    public function testNoMonstersAdjacentReturnsEmpty(): void {
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testAdjacentMonsterIsTargetable(): void {
        // hex_12_8 is adjacent to hex_11_8
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testRange2MonsterTargetableWithBow(): void {
        // Bjorn has First Bow (attack_range=2), hex_13_7 is 2 steps from hex_11_8
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_13_7", $moves);
    }

    public function testOutOfRangeMonsterNotTargetable(): void {
        // Embla (hero_3) has Flimsy Blade (no attack_range), so range=1
        $this->game->tokens->moveToken("card_hero_3_1", "tableau_" . BCOLOR);
        $this->game->tokens->moveToken("card_equip_3_15", "tableau_" . BCOLOR); // Flimsy Blade, no range
        $this->game->tokens->moveToken("hero_3", "hex_11_8");

        // hex_13_7 is 2 steps away — out of range 1
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");

        /** @var Op_actionAttack */
        $op = $this->game->machine->instanciateOperation("actionAttack", BCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayNotHasKey("hex_13_7", $moves);
    }

    public function testMultipleAdjacentMonsters(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_11_7");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
        $this->assertArrayHasKey("hex_11_7", $moves);
        $this->assertCount(2, $moves);
    }

    public function testAdjacentHeroNotTargetable(): void {
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayNotHasKey("hex_12_8", $moves);
    }

    // -------------------------------------------------------------------------
    // getHeroAttackStrength
    // -------------------------------------------------------------------------

    public function testAttackStrengthBjornStarting(): void {
        // Bjorn hero card: strength=2, starting equip (Bjorn's First Bow): +1, ability (Sure Shot I): no strength
        $strength = $this->game->getHeroAttackStrength(PCOLOR);
        $this->assertEquals(3, $strength); // 2 + 1
    }

    public function testAttackStrengthHeroCardOnly(): void {
        // Remove equipment and ability
        $this->game->tokens->moveToken("card_equip_1_15", "limbo");
        $this->game->tokens->moveToken("card_ability_1_3", "limbo");
        $strength = $this->game->getHeroAttackStrength(PCOLOR);
        $this->assertEquals(2, $strength); // hero card only
    }

    // -------------------------------------------------------------------------
    // resolve
    // -------------------------------------------------------------------------

    public function testResolveAllHitsKillsGoblin(): void {
        // Goblin: health=2. Bjorn strength=3. Seed all dice to side 5 (hit) → 3 hits, kills goblin
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->randQueue = [5, 5, 5];
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(0, $crystals);
    }

    public function testResolveAllMissesLeaveMonsterAlive(): void {
        // Seed all dice to side 1 (miss) → 0 hits
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->randQueue = [1, 1, 1];
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(0, $crystals); // 0 hits = 0 damage
    }

    public function testResolvePartialHitsApplyDamage(): void {
        // Troll: health=7. Seed: hit, miss, hit → 2 hits, not enough to kill
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->game->randQueue = [5, 1, 6]; // side 5=hit, side 1=miss, side 6=hit
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_troll_1"));
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_troll_1");
        $this->assertCount(2, $crystals);
    }

    public function testResolveInvalidTargetThrows(): void {
        // No monster on hex_12_8
        $op = $this->createOp();
        $this->expectException(\Bga\GameFramework\UserException::class);
        $this->expectOutputRegex("/./");
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);
    }

    public function testDiceStayOnDisplayAfterAttack(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->randQueue = [5, 5, 5];

        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

        // Dice should remain on display_battle so the player can see them
        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(3, $diceOnDisplay);
    }

    public function testSecondAttackCleansUpPreviousDice(): void {
        // First attack — dice land on display_battle
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->game->randQueue = [1, 1, 1];
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(3, $diceOnDisplay);

        // Second attack — old dice cleaned up, new dice placed
        $this->game->randQueue = [5, 5, 5];
        $op2 = $this->createOp();
        $op2->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(3, $diceOnDisplay);
    }

    // -------------------------------------------------------------------------
    // effect_applyDamageMonster
    // -------------------------------------------------------------------------

    public function testDamageKillsMonsterWhenEnough(): void {
        // Goblin: health=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // Pre-place damage crystals (crystal placement now happens in rollAttackDice)
        $this->game->effect_moveCrystals("monster_goblin_1", "red", 2, "monster_goblin_1", ["message" => ""]);

        $killed = $this->game->effect_applyDamageMonster("monster_goblin_1", 2, PCOLOR);
        $this->assertTrue($killed);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));

        // No red crystals should remain on monster
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(0, $crystals);
    }

    public function testDamageDoesNotKillWhenInsufficient(): void {
        // Goblin: health=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // Pre-place 1 damage crystal (crystal placement now happens in rollAttackDice)
        $this->game->effect_moveCrystals("monster_goblin_1", "red", 1, "monster_goblin_1", ["message" => ""]);

        $killed = $this->game->effect_applyDamageMonster("monster_goblin_1", 1, PCOLOR);
        $this->assertFalse($killed);
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));

        // 1 red crystal should be on monster
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(1, $crystals);
    }

    public function testDamageAccumulates(): void {
        // Troll: health=7
        $this->game->tokens->moveToken("monster_troll_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // First attack: 3 damage — pre-place crystals
        $this->game->effect_moveCrystals("monster_troll_1", "red", 3, "monster_troll_1", ["message" => ""]);
        $killed = $this->game->effect_applyDamageMonster("monster_troll_1", 3, PCOLOR);
        $this->assertFalse($killed);
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_troll_1");
        $this->assertCount(3, $crystals);

        // Second attack: 4 more damage — pre-place crystals, total 7, enough to kill
        $this->game->effect_moveCrystals("monster_troll_1", "red", 4, "monster_troll_1", ["message" => ""]);
        $killed = $this->game->effect_applyDamageMonster("monster_troll_1", 4, PCOLOR);
        $this->assertTrue($killed);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_troll_1"));

        // All red crystals returned to supply
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_troll_1");
        $this->assertCount(0, $crystals);
    }

    public function testZeroDamageDoesNothing(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        $killed = $this->game->effect_applyDamageMonster("monster_goblin_1", 0, PCOLOR);
        $this->assertFalse($killed);
        $this->assertEquals("hex_12_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(0, $crystals);
    }

    // -------------------------------------------------------------------------
    // effect_gainXp
    // -------------------------------------------------------------------------

    public function testXpAwardedOnKill(): void {
        // Goblin: xp=1, health=2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // Pre-place enough damage crystals to kill
        $this->game->effect_moveCrystals("monster_goblin_1", "red", 2, "monster_goblin_1", ["message" => ""]);

        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $this->game->effect_applyDamageMonster("monster_goblin_1", 2, PCOLOR);

        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore + 1, $xpAfter);
    }

    public function testXpNotAwardedOnSurvive(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // Pre-place 1 damage crystal (not enough to kill goblin with health=2)
        $this->game->effect_moveCrystals("monster_goblin_1", "red", 1, "monster_goblin_1", ["message" => ""]);

        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $this->game->effect_applyDamageMonster("monster_goblin_1", 1, PCOLOR);

        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore, $xpAfter);
    }

    public function testBruteGivesMoreXp(): void {
        // Brute: health=3, xp=2
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // Pre-place enough damage crystals to kill
        $this->game->effect_moveCrystals("monster_brute_1", "red", 3, "monster_brute_1", ["message" => ""]);

        $xpBefore = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $this->game->effect_applyDamageMonster("monster_brute_1", 3, PCOLOR);

        $xpAfter = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($xpBefore + 2, $xpAfter);
    }

    // -------------------------------------------------------------------------
    // Cover (forest hex)
    // -------------------------------------------------------------------------

    public function testHeroNotOnMapReturnsEmpty(): void {
        // Move hero off the map — getPossibleMoves should return empty, not crash
        $this->game->tokens->moveToken("hero_1", "limbo");
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertEmpty($moves);
    }

    public function testForestHexProvidesCover(): void {
        // hex_12_4 is forest, hex_12_5 is plains and adjacent
        $this->game->tokens->moveToken("hero_1", "hex_12_5");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_4");
        $this->game->hexMap->invalidateOccupancy();

        $terrain = $this->game->hexMap->getHexTerrain("hex_12_4");
        $this->assertEquals("forest", $terrain);

        // Monster on forest hex should be targetable
        $op = $this->createOp();
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_4", $moves);
    }

    public function testCoverBlocksHitcov(): void {
        // hex_12_4 is forest. Goblin health=2.
        // Seed: side 4 (hitcov), side 4 (hitcov), side 5 (hit) → only 1 real hit (hitcov blocked by cover)
        $this->game->tokens->moveToken("hero_1", "hex_12_5");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_4");
        $this->game->hexMap->invalidateOccupancy();

        $this->game->randQueue = [4, 4, 5];
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_4"]);

        // 1 hit is not enough to kill goblin (health=2)
        $this->assertEquals("hex_12_4", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "monster_goblin_1");
        $this->assertCount(1, $crystals);
    }

    public function testHitcovCountsWithoutCover(): void {
        // hex_12_8 is plains (no cover). Goblin health=2.
        // Seed: side 4 (hitcov), side 4 (hitcov), side 1 (miss) → 2 hits, kills goblin
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $terrain = $this->game->hexMap->getHexTerrain("hex_12_8");
        $this->assertNotEquals("forest", $terrain);

        $this->game->randQueue = [4, 4, 1];
        $op = $this->createOp();
        $op->action_resolve([Operation::ARG_TARGET => "hex_12_8"]);

        // 2 hitcov hits on non-forest hex → goblin killed
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }
}
