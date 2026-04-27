<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/**
 * Integration tests for Boldur's ability and hero cards.
 * Scripts full game turns using the harness GameDriver in-process.
 */
class Campaign_BoldurAbilityTest extends CampaignBaseTest {
    private string $heroId;

    protected function setUp(): void {
        parent::setUp();
        $this->setupGame([4]); // Solo Boldur
        $this->heroId = $this->game->getHeroTokenId($this->getActivePlayerColor());

        $this->clearMonstersFromMap();
        $this->clearHand($this->getActivePlayerColor());
    }

    // --- Beefy Berserker I (card_ability_4_9) ---
    // No r/on — passive: runes [RUNE] always count as hits when this hero attacks.
    // Hooked in Character::countHit: if attacker is a hero whose tableau holds 4_9 or 4_10,
    // a "rune" die result is treated as a hit.
    // Card also grants +1 strength (one extra die).

    public function testBeefyBerserkerICountsRunesAsHits(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_4_9";
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        // strength tracker only recomputes on setup/turnEnd/upgrade — recalc to pick up the +1 die
        $this->game->getHero($color)->recalcTrackers();

        // Boldur I (3) + Boldur's First Pick (1) + Beefy Berserker I (1) = 5 dice.
        $this->assertEquals(5, $this->game->getHero($color)->getAttackStrength());

        // Boldur on plains outside Grimheim, troll (health=6) adjacent — survives the attack.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        // 2 runes (3) + 3 misses (1). Without the card, runes wouldn't count → 0 damage.
        // With Beefy Berserker I, both runes count as hits → 2 damage.
        $this->seedRand([3, 3, 1, 1, 1]);
        // Op_turn inlines attack-target hexes — pick the troll's hex directly.
        $this->respond("hex_7_8");

        $this->assertEquals(2, $this->countDamage($troll));
        $this->assertEquals("hex_7_8", $this->tokenLocation($troll));
        $this->assertEmpty($this->game->randQueue, "Strength should consume exactly 5 dice");
    }

    // --- Wrecking Ball I (card_ability_4_7) ---
    // r=nop, on=custom — bespoke Op_c_wrecking pendulum loop dispatched from Op_move
    // when the card is on the tableau and an adjacent hex is occupied. Designer rule
    // clarifications: pendulum swap allowed (push displaced into the hex Boldur
    // came from); "character" includes heroes; cannot push out of Grimheim.

    /** Move Boldur to a plains hex outside Grimheim and arm the Wrecking Ball card. */
    private function placeWreckingBallI(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_ability_4_7", "tableau_$color");
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $this->game->hexMap->invalidateOccupancy();
    }

    public function testWreckingBallIRamsAdjacentMonsterDealsDamageAndPushes(): void {
        $this->placeWreckingBallI();
        $goblin = "monster_goblin_1";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");
        $this->game->hexMap->invalidateOccupancy();

        // turn op inlines actionMove targets — wrecking card_id is offered alongside hexes.
        $this->assertValidTarget("card_ability_4_7");
        $this->respond("card_ability_4_7"); // launch the pendulum via Op_move
        // c_wrecking destination phase — pick the occupied adjacent hex.
        $this->assertOperation("c_wrecking");
        $this->respond("hex_7_8");
        // Push phase — choose where the displaced goblin goes. Pick hex_6_8 (a non-came-from neighbor).
        $this->assertOperation("c_wrecking");
        $this->respond("hex_6_8");
        // Loop continues; end the action.
        $this->respond("endOfMove");
        $this->skipIfOp("drawEvent");

        $this->assertEquals("hex_7_8", $this->tokenLocation($this->heroId), "Boldur ended on the rammed hex");
        $this->assertEquals("hex_6_8", $this->tokenLocation($goblin), "Goblin pushed to hex_6_8");
        $this->assertEquals(1, $this->countDamage($goblin), "Wrecking Ball deals 1 damage to displaced character");
    }

    public function testWreckingBallIPendulumSwapsWithCameFromHex(): void {
        $this->placeWreckingBallI();
        $goblin = "monster_goblin_2";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");
        $this->game->hexMap->invalidateOccupancy();

        $this->respond("card_ability_4_7");
        $this->respond("hex_7_8"); // ram into goblin's hex
        // Pendulum: push displaced goblin into the hex Boldur just came from.
        $this->respond("hex_7_9");
        $this->respond("endOfMove");
        $this->skipIfOp("drawEvent");

        $this->assertEquals("hex_7_8", $this->tokenLocation($this->heroId), "Boldur ended on goblin's old hex");
        $this->assertEquals("hex_7_9", $this->tokenLocation($goblin), "Goblin swapped into Boldur's old hex");
        $this->assertEquals(1, $this->countDamage($goblin));
    }

    public function testWreckingBallIChainsMultipleRamsInOneAction(): void {
        // Boldur's move = 3. Use a troll (health=7) so it survives multiple rams.
        $this->placeWreckingBallI();
        $troll = "monster_troll_1";
        $bystander = "monster_goblin_4";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");
        $this->game->getMonster($bystander)->moveTo("hex_7_10", "");
        $this->game->hexMap->invalidateOccupancy();

        $this->respond("card_ability_4_7");
        $this->respond("hex_7_8"); // ram troll
        $this->respond("hex_7_9"); // pendulum-swap troll back to Boldur's start
        // Boldur on hex_7_8 now; step back to hex_7_9 (now occupied by troll) → ram again.
        $this->respond("hex_7_9");
        $this->respond("hex_7_8"); // shove troll back to hex_7_8
        $this->respond("endOfMove");
        $this->skipIfOp("drawEvent");

        $this->assertEquals("hex_7_9", $this->tokenLocation($this->heroId));
        $this->assertEquals("hex_7_8", $this->tokenLocation($troll));
        $this->assertEquals(2, $this->countDamage($troll), "Troll rammed twice = 2 damage");
        $this->assertEquals(0, $this->countDamage($bystander), "Bystander never engaged");
    }

    public function testWreckingBallIEndOfMoveSentinelEndsAction(): void {
        $this->placeWreckingBallI();
        $goblin = "monster_goblin_5";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");
        $this->game->hexMap->invalidateOccupancy();

        $this->respond("card_ability_4_7");
        // Choose endOfMove immediately — no ram, no damage.
        $this->assertValidTarget("endOfMove");
        $this->respond("endOfMove");
        $this->skipIfOp("drawEvent");

        $this->assertEquals("hex_7_9", $this->tokenLocation($this->heroId), "Boldur did not move");
        $this->assertEquals("hex_7_8", $this->tokenLocation($goblin), "Goblin not displaced");
        $this->assertEquals(0, $this->countDamage($goblin));
        // Move action consumed (the action marker placed when actionMove was queued).
        $hero = $this->game->getHero($this->getActivePlayerColor());
        $this->assertContains("actionMove", $hero->getActionsTaken());
    }

    public function testWreckingBallIPushesIntoGrimheim(): void {
        // Designer rule: pushing into Grimheim is allowed and deals 1 damage as usual.
        // Park Boldur at hex_8_8 (adjacent to Grimheim hex_8_9 and hex_9_8); a goblin at hex_7_8
        // adjacent to Boldur. Push the goblin into Grimheim hex_8_9.
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_ability_4_7", "tableau_$color");
        // Park Boldur at hex_8_7; goblin at hex_8_8 adjacent. After ram, Boldur on hex_8_8
        // whose neighbours include Grimheim hexes hex_8_9 and hex_9_8.
        $this->game->tokens->moveToken($this->heroId, "hex_8_7");
        $goblin = "monster_goblin_6";
        $this->game->getMonster($goblin)->moveTo("hex_8_8", "");
        $this->game->hexMap->invalidateOccupancy();

        $this->respond("card_ability_4_7");
        $this->respond("hex_8_8");
        $this->assertValidTarget("hex_8_9");
        $this->respond("hex_8_9");
        $this->respond("endOfMove");
        $this->skipIfOp("drawEvent");

        $this->assertEquals("hex_8_8", $this->tokenLocation($this->heroId));
        $this->assertEquals("hex_8_9", $this->tokenLocation($goblin), "Goblin pushed into Grimheim");
        $this->assertEquals(1, $this->countDamage($goblin), "1 damage applied even when target lands in Grimheim");
    }

    // --- Wrecking Ball II (card_ability_4_8) ---
    // Same ram/push behaviour as I, plus passive +1 move (Hero::calcBaseMove).

    public function testWreckingBallIIGrantsPassivePlusOneMove(): void {
        $color = $this->getActivePlayerColor();
        $hero = $this->game->getHero($color);

        // Boldur's base move is 3.
        $this->assertEquals(3, $hero->getNumberOfMoves(), "Baseline Boldur move = 3");

        // Place Wrecking Ball II on tableau and recompute trackers.
        $this->game->tokens->moveToken("card_ability_4_8", "tableau_$color");
        $hero->recalcTrackers();

        $this->assertEquals(4, $hero->getNumberOfMoves(), "Wrecking Ball II grants passive +1 move");
    }

    public function testWreckingBallIIRamsLikeLevelI(): void {
        // Level II shares the ram mechanic; verify the card is offered as a target.
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_ability_4_8", "tableau_$color");
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $goblin = "monster_goblin_7";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");
        $this->game->hexMap->invalidateOccupancy();

        $this->assertValidTarget("card_ability_4_8");
        $this->respond("card_ability_4_8");
        $this->respond("hex_7_8");
        $this->respond("hex_6_8");
        $this->respond("endOfMove");
        $this->skipIfOp("drawEvent");

        $this->assertEquals("hex_7_8", $this->tokenLocation($this->heroId));
        $this->assertEquals("hex_6_8", $this->tokenLocation($goblin));
        $this->assertEquals(1, $this->countDamage($goblin));
    }
}
