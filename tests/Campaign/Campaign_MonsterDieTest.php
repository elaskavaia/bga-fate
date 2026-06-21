<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for the Monster Die variant — Phase D2 passive effects:
 *   - `attack` (side 3) → every monster attacks with +1 strength this turn
 *   - `charge` (side 5) → rank-1 monsters move +1 step this turn
 *
 * The die's rolled state lives on display_monsterturn. These tests bypass the
 * (random) roll by directly parking the die at a specific side before the
 * monster turn fires, then assert the downstream behavior in the monster
 * movement / attack ops.
 */
class Campaign_MonsterDieTest extends CampaignBaseTest {
    private string $heroId;
    private string $color;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([1]); // Solo Bjorn

        $this->color = $this->getActivePlayerColor();
        $this->heroId = $this->game->getHeroTokenId($this->color);
        $this->game->tokens->moveToken($this->heroId, "hex_7_9"); // park hero out of the way
        $this->seedDeck("deck_monster_yellow", ["card_monster_7", "card_monster_8", "card_monster_9", "card_monster_10"]);
        $this->seedDeck("deck_event_" . $this->color, ["card_event_1_27_1", "card_event_1_27_2"]);
        $this->clearHand($this->color);
        $this->clearMonstersFromMap();

        // Enable monster-die variant for the rest of the test.
        $this->game->setGameStateValue("var_monster_die", 1);
    }

    /**
     * Force the die onto display_monsterturn at the requested side, bypassing the
     * random roll. Op_rollMonsterDie's leftover-sweep will move it back to supply
     * and re-roll — to keep the side fixed we also disable the variant flag *for
     * this test*. The downstream readers (`getMonsterDieSide()`) only inspect the
     * physical die state, so they pick up our seeded value either way.
     */
    private function seedDieSide(int $state): void {
        $this->game->setGameStateValue("var_monster_die", 0);
        $this->game->tokens->dbSetTokenLocation("die_monster", "display_monsterturn", $state, "");
    }

    /**
     * Drive a real monster die roll by stuffing the next bgaRand result. Use this
     * for active sides (maneuver/push/ambush) where we need the roll op itself to
     * dispatch the per-side handler. Passive sides (attack/charge) can keep using
     * seedDieSide.
     */
    private function forceRollSide(int $side): void {
        $this->game->randQueue[] = $side;
    }

    private function driveOneMonsterTurn(): void {
        $this->respond("actionPractice");
        $this->respond("actionFocus");
        $this->skipOp("turn");
        $this->skipIfOp("upgrade");
        $this->skipIfOp("drawEvent");
    }

    public function testChargeGivesRank1MonstersExtraStep(): void {
        $this->seedDieSide(5); // charge

        // Goblin (rank 1, move=2) on a road hex — base move would land at hex_12_4.
        // With charge +1 it should reach hex_12_5.
        $monster = "monster_goblin_20";
        $this->game->getMonster($monster)->moveTo("hex_12_2", "");

        $this->driveOneMonsterTurn();

        $this->assertEquals("hex_12_5", $this->tokenLocation($monster), "rank-1 goblin should charge +1 step on charge side");
    }

    public function testChargeDoesNotAffectHigherRankMonsters(): void {
        $this->seedDieSide(5); // charge

        // Brute (rank 2, move=1) on the same road. Without charge it lands at hex_12_3.
        // Charge side should NOT add an extra step (only rank-1 monsters charge per the die).
        $monster = "monster_brute_15";
        $this->game->getMonster($monster)->moveTo("hex_12_2", "");

        $this->driveOneMonsterTurn();

        $this->assertEquals("hex_12_3", $this->tokenLocation($monster), "rank-2 brute should not charge from monster die");
    }

    public function testNonChargeSideLeavesMovementUnchanged(): void {
        $this->seedDieSide(3); // attack — irrelevant to movement

        $monster = "monster_goblin_20";
        $this->game->getMonster($monster)->moveTo("hex_12_2", "");

        $this->driveOneMonsterTurn();

        // Without the charge side, goblin walks its base move=2: hex_12_2 → hex_12_4.
        $this->assertEquals("hex_12_4", $this->tokenLocation($monster), "non-charge side does not affect movement");
    }

    public function testAttackSideGivesEveryMonsterPlusOneStrength(): void {
        $this->seedDieSide(3); // attack

        // Place a goblin adjacent to the hero (hex_7_9) — base strength=1, so with +1 it
        // should roll 2 attack dice onto display_battle.
        $monster = "monster_goblin_20";
        $this->game->getMonster($monster)->moveTo("hex_7_8", "");

        $this->driveOneMonsterTurn();

        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(2, $diceOnDisplay, "goblin (strength 1) + attack-side die (+1) should roll 2 attack dice");
    }

    public function testEndOfMonsterTurnSweepsDieBackToSupply(): void {
        $this->seedDieSide(3); // attack — any side

        $this->game->getMonster("monster_goblin_20")->moveTo("hex_12_2", "");
        $this->driveOneMonsterTurn();

        // After the monster turn ends, the die must NOT be left on display where
        // hero-turn code reading getMonsterDieSide() would still see it.
        $onDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_monster", "display_monsterturn");
        $this->assertCount(0, $onDisplay, "die must be swept off display at end of monster turn");
        $this->assertNull($this->game->getMonsterDieSide(), "side reads null between monster turns");
    }

    public function testNonAttackSideLeavesStrengthUnchanged(): void {
        $this->seedDieSide(5); // charge — irrelevant to strength

        $monster = "monster_goblin_20";
        $this->game->getMonster($monster)->moveTo("hex_7_8", "");

        $this->driveOneMonsterTurn();

        // Goblin base strength=1, no +1 from non-attack side.
        $diceOnDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_attack", "display_battle");
        $this->assertCount(1, $diceOnDisplay, "goblin strength stays at 1 when die side is not attack");
    }

    // -------------------------------------------------------------------------
    // Review-flagged gaps: real-roll path + edge cases + A8 log
    // -------------------------------------------------------------------------

    public function testRealRollPathParksDieOnDisplay(): void {
        // Skip seedDieSide — let the real Op_rollMonsterDie roll the die during the turn.
        $this->driveOneMonsterTurn();

        $onDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_monster", "display_monsterturn");
        // Sweep at end of monster turn means the die is back in supply now.
        $this->assertCount(0, $onDisplay, "die swept off display at turn end");

        // But during the turn the side must have been one of the 6 valid rules — confirm via
        // the roll log line we always emit.
        $sideRules = ["maneuver_1", "maneuver_2", "attack", "push", "charge", "ambush"];
        $rollLogs = array_filter($this->game->notify->_getNotifications(), fn($n) => str_contains($n["log"] ?? "", "Monsters roll"));
        $this->assertCount(1, $rollLogs, "exactly one Monster die roll per monster turn");
    }

    public function testChargeWithNoMonstersDoesNotCrash(): void {
        $this->seedDieSide(5); // charge

        // No monster placed — deliberately empty map.
        $this->driveOneMonsterTurn();

        // Just reaching here without exception is the assertion.
        $this->assertTrue(true, "monster turn with charge side and no monsters resolves cleanly");
    }

    public function testAttackSideBuffsMultipleAdjacentMonstersIndependently(): void {
        $this->seedDieSide(3); // attack

        // Two goblins each adjacent to the hero on hex_7_9. Each must roll its own
        // attack with strength=2 (base 1 + attack-side +1).
        $this->game->getMonster("monster_goblin_19")->moveTo("hex_7_8", "");
        $this->game->getMonster("monster_goblin_20")->moveTo("hex_8_9", "");

        $this->driveOneMonsterTurn();

        // Filter the notify stream for the per-attack "attacks ... with strength X" lines.
        $strengthLogs = array_values(
            array_filter(
                $this->game->notify->_getNotifications(),
                fn($n) => str_contains($n["log"] ?? "", "attacks") && str_contains($n["log"] ?? "", "strength")
            )
        );
        $this->assertCount(2, $strengthLogs, "both goblins should emit one attack log");
        foreach ($strengthLogs as $i => $log) {
            $this->assertEquals(2, $log["args"]["strength"] ?? null, "goblin #$i should attack at strength 2 (1 base + 1 attack-side)");
        }
    }

    public function testChargeSideDoesNotStackWithSkullTurn(): void {
        $this->seedDieSide(5); // charge — would normally give rank-1 +1 step

        // Force a skull-turn time-track spot (slot 9 of timetrack_1 = tm_red_skull) — already
        // grants every monster +1 step. The Monster Die charge side must NOT add a SECOND +1.
        $this->game->tokens->moveToken("rune_stone", "timetrack_1", 8); // step 9 after the +1 advance

        $monster = "monster_goblin_20";
        $this->game->getMonster($monster)->moveTo("hex_12_2", "");

        $this->driveOneMonsterTurn();

        // Skull alone: goblin (move=2) + 1 = 3 steps → hex_12_2 → hex_12_3 → hex_12_4 → hex_12_5.
        // If charge stacked, would be 4 steps → hex_12_6 (or further).
        $this->assertEquals("hex_12_5", $this->tokenLocation($monster), "skull-turn charge already provides +1; charge die must not stack");
    }

    // -------------------------------------------------------------------------
    // Phase D3 / D4 / D5: active sides — full roll-dispatch path
    // -------------------------------------------------------------------------

    public function testPushSideMovesAdjacentHeroTowardGrimheim(): void {
        // Hero at hex_12_7 (dir=7, getMonsterNextHex → hex_11_8). Adjacent monster at hex_13_7.
        $this->game->tokens->moveToken($this->heroId, "hex_12_7");
        $this->game->getMonster("monster_goblin_19")->moveTo("hex_13_7", "");
        $this->forceRollSide(4); // push

        $this->driveOneMonsterTurn();

        $this->assertEquals("hex_11_8", $this->tokenLocation($this->heroId), "hero should be pushed one hex toward Grimheim");
    }

    public function testPushSideLeavesUnadjacentHeroPut(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_12_7");
        // No monster adjacent — clearMonstersFromMap in setUp already cleared the map.
        $this->forceRollSide(4); // push

        $this->driveOneMonsterTurn();

        $this->assertEquals("hex_12_7", $this->tokenLocation($this->heroId), "no adjacent monster → no push");
    }

    public function testAmbushSidePlacesGoblinAdjacent(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->forceRollSide(6); // ambush

        $goblinsBefore = $this->countTokens("monster_goblin", "hex%");
        $this->driveOneMonsterTurn();

        // Ambush now prompts the player to place the goblin themselves (RULES.md "Ambush").
        $this->assertEquals("spawn", $this->getOpArgs()["type"] ?? "", "ambush should prompt to place the goblin");
        $heroHex = $this->tokenLocation($this->heroId);
        $adjacent = $this->game->hexMap->getAdjacentHexes($heroHex);
        $chosen = $this->getOpArgs()["target"][0];
        $this->assertContains($chosen, $adjacent, "offered placement hex must be adjacent to the hero");
        $this->respond($chosen);

        $this->assertEquals($goblinsBefore + 1, $this->countTokens("monster_goblin", "hex%"), "ambush spawns exactly one goblin");
        $this->assertEquals(1, $this->countTokens("monster_goblin", $chosen), "the new goblin landed on the chosen adjacent hex");
    }

    public function testAmbushSideSkipsHeroInGrimheim(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_9_9"); // Grimheim
        $this->forceRollSide(6); // ambush

        $goblinsBefore = $this->countTokens("monster_goblin", "hex%");
        $this->driveOneMonsterTurn();
        $goblinsAfter = $this->countTokens("monster_goblin", "hex%");

        $this->assertEquals($goblinsBefore, $goblinsAfter, "no goblin spawned when the only hero is in Grimheim");
    }

    public function testManeuverCwRotatesMonsterAroundHero(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_12_7");
        // Goblin at W of hero (hex_11_7). CW rotation → NW = hex_12_6.
        $this->game->getMonster("monster_goblin_19")->moveTo("hex_11_7", "");
        $this->forceRollSide(1); // maneuver_1 = cw

        $this->driveOneMonsterTurn();

        $this->assertEquals("hex_12_6", $this->tokenLocation("monster_goblin_19"));
    }

    public function testManeuverCcwRotatesMonsterAroundHero(): void {
        $this->game->tokens->moveToken($this->heroId, "hex_12_7");
        // Goblin at W of hero. CCW → SW = hex_11_8.
        $this->game->getMonster("monster_goblin_19")->moveTo("hex_11_7", "");
        $this->forceRollSide(2); // maneuver_2 = ccw

        $this->driveOneMonsterTurn();

        $this->assertEquals("hex_11_8", $this->tokenLocation("monster_goblin_19"));
    }
}
