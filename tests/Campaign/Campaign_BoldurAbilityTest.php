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

    // --- Beefy Berserker II (card_ability_4_10) ---
    // Same passive rune-as-hit hook, but grants +3 strength instead of +1.

    public function testBeefyBerserkerIICountsRunesAsHits(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_4_10";
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        $this->game->getHero($color)->recalcTrackers();

        // Boldur I (3) + Boldur's First Pick (1) + Beefy Berserker II (3) = 7 dice.
        $this->assertEquals(7, $this->game->getHero($color)->getAttackStrength());

        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        // 3 runes + 4 misses. Without the card runes wouldn't count → 0 damage.
        // With Beefy Berserker II all 3 runes count as hits → 3 damage (troll h=6 survives).
        $this->seedRand([3, 3, 3, 1, 1, 1, 1]);
        $this->respond("hex_7_8");

        $this->assertEquals(3, $this->countDamage($troll));
        $this->assertEquals("hex_7_8", $this->tokenLocation($troll));
        $this->assertEmpty($this->game->randQueue, "Strength should consume exactly 7 dice");
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

    // --- Rapid Strike II (card_ability_4_4) ---
    // r=2spendMana:actionAttack — pay 2 mana to perform an attack action.
    // Unlike Rapid Strike I (which has spendUse), II has no spendUse and may be
    // activated multiple times per turn — that's the distinguishing feature.

    public function testRapidStrikeIIPerformsAttackTwiceInOneTurn(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_4_4";
        // Heroes start at Level I — move Rapid Strike II onto the tableau manually.
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        // Seed 4 mana on the card so we can pay 2+2 (two activations in one turn).
        $this->game->effect_moveCrystals($this->heroId, "green", 4, $cardId, ["message" => ""]);

        // Park Boldur outside Grimheim with a beefy troll (h=6) adjacent so it survives both attacks.
        $this->game->tokens->moveToken($this->heroId, "hex_7_9");
        $troll = "monster_troll_1";
        $this->game->getMonster($troll)->moveTo("hex_7_8", "");

        // Boldur I (3) + Boldur's First Pick (1) = 4 dice per attack. Two activations = 8 dice.
        // Seed 1 hit + 3 misses per activation → 1 damage each, 2 total (troll h=6 survives).
        $this->seedRand([5, 1, 1, 1, 5, 1, 1, 1]);

        // First activation: respond(cardId) pays 2 mana and auto-runs actionAttack
        // (single adjacent monster → target inlined into the turn op, no second prompt).
        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        $this->assertEquals(1, $this->countDamage($troll), "First Rapid Strike II attack deals 1 damage");
        $this->assertEquals(2, $this->countTokens("crystal_green", $cardId), "2 of 4 mana spent");

        // Second activation in the same turn — proves "may be used several times per turn"
        // (no spendUse in r → no per-turn lockout, unlike Rapid Strike I).
        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        $this->assertEquals(2, $this->countDamage($troll), "Second Rapid Strike II attack deals another 1 damage");
        $this->assertEquals(0, $this->countTokens("crystal_green", $cardId), "All 4 mana spent");
        $this->assertEquals("hex_7_8", $this->tokenLocation($troll), "Troll still alive after 2 damage");
        $this->assertEmpty($this->game->randQueue, "Exactly 8 dice rolled (4 per attack x 2 attacks)");
    }

    // --- Boldur Hero I (card_hero_4_1) ---
    // No r/on — passive: "Armor. (Always prevents 1 damage)".
    // Implemented in Hero::getArmor() returning 1 for heroNum===4. Op_resolveHits
    // calls Character::applyArmor before queueing dealDamage so total incoming
    // hits are reduced by 1 (floor 0). Driven via direct monsterAttack op to
    // isolate the armor pipeline from monsterAttackAll's defender grouping.

    private function runMonsterAttack(string $monsterId, string $heroHex): void {
        $this->game->hexMap->invalidateOccupancy();
        $op = $this->game->machine->instantiateOperation("monsterAttack", null, [
            "char" => $monsterId,
            "target" => $heroHex,
        ]);
        $op->resolve();
        $this->game->machine->dispatchAll();
    }

    public function testBoldurHeroIArmorAbsorbsOneDamagePerAttack(): void {
        $this->assertEquals(1, $this->game->getHero($this->getActivePlayerColor())->getArmor(), "Boldur has armor=1");
        // Brute (str=3) adjacent. Seed 2 hits + 1 miss → 2 hits, armor → 1 damage.
        $boldurHex = "hex_7_9";
        $this->game->tokens->moveToken($this->heroId, $boldurHex);
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_7_8", "");

        $damageBefore = $this->countDamage($this->heroId);
        $this->seedRand([5, 5, 1]);

        $this->runMonsterAttack($brute, $boldurHex);

        $this->assertEquals(
            $damageBefore + 1,
            $this->countDamage($this->heroId),
            "Boldur takes 2 hits - 1 armor = 1 damage"
        );
    }

    public function testBoldurHeroIArmorAbsorbsSingleHitToZero(): void {
        // Goblin (str=1) adjacent → 1 die. Seed 1 hit → armor absorbs fully → 0 damage.
        $boldurHex = "hex_7_9";
        $this->game->tokens->moveToken($this->heroId, $boldurHex);
        $goblin = "monster_goblin_1";
        $this->game->getMonster($goblin)->moveTo("hex_7_8", "");

        $damageBefore = $this->countDamage($this->heroId);
        $this->seedRand([5]);

        $this->runMonsterAttack($goblin, $boldurHex);

        $this->assertEquals(
            $damageBefore,
            $this->countDamage($this->heroId),
            "Single hit fully absorbed by armor"
        );
    }

    // --- Dreadnought II (card_ability_4_12) ---
    // Passive: "Each adjacent monster that attacks you is dealt 1 damage after its attack."
    // Hardcoded in Op_monsterAttack: queues a 1dealDamage targeting the attacker's hex
    // after the roll, gated on (defender has card_ability_4_12) AND (attacker adjacent).

    public function testDreadnoughtIIDeals1DamageToAdjacentAttacker(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_ability_4_12", "tableau_$color");

        $boldurHex = "hex_7_9";
        $this->game->tokens->moveToken($this->heroId, $boldurHex);
        $brute = "monster_brute_1";
        $bruteHex = "hex_7_8";
        $this->game->getMonster($brute)->moveTo($bruteHex, "");

        // Brute str=3 → 3 dice. Seed all hits (Boldur takes 3 - 1 armor = 2 dmg).
        $this->seedRand([5, 5, 5]);
        $this->runMonsterAttack($brute, $boldurHex);

        $this->assertEquals(1, $this->countDamage($brute), "Dreadnought II reflects 1 damage to the brute");
    }

    public function testDreadnoughtIIDoesNotReflectFromNonAdjacentAttacker(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_ability_4_12", "tableau_$color");

        $boldurHex = "hex_7_9";
        $this->game->tokens->moveToken($this->heroId, $boldurHex);
        // Wisp = ranged (range 2). Place 2 hexes away so it can attack but isn't adjacent.
        $wisp = "monster_wisp_1";
        $wispHex = "hex_7_7";
        $this->game->getMonster($wisp)->moveTo($wispHex, "");

        $this->seedRand([5, 5, 5]);
        $this->runMonsterAttack($wisp, $boldurHex);

        $this->assertEquals(0, $this->countDamage($wisp), "Ranged (non-adjacent) attacker is not reflected");
    }

    public function testNoReflectWithoutDreadnoughtIIOnTableau(): void {
        // Sanity check: same setup as the positive test, minus the card → no reflect.
        $boldurHex = "hex_7_9";
        $this->game->tokens->moveToken($this->heroId, $boldurHex);
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_7_8", "");

        $this->seedRand([5, 5, 5]);
        $this->runMonsterAttack($brute, $boldurHex);

        $this->assertEquals(0, $this->countDamage($brute), "Without the card, attacker takes no reflect damage");
    }

    /**
     * Pins down DESIGN.md open question #6 for Dreadnought II: the reflect resolves
     * as part of the same attack, so it still fires even when the attack itself
     * knocks Boldur out. Same assumption we apply to Embla's Riposte.
     */
    public function testDreadnoughtIIReflectsEvenWhenBoldurKnockedOut(): void {
        $color = $this->getActivePlayerColor();
        $this->game->tokens->moveToken("card_ability_4_12", "tableau_$color");
        $boldur = $this->game->getHero($color);
        $boldur->recalcTrackers();

        $boldurHex = "hex_7_9";
        $this->game->tokens->moveToken($this->heroId, $boldurHex);
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_7_8", "");

        // Pre-damage so the incoming 2 damage (3 hits - 1 armor) takes Boldur to 0.
        $preDmg = $boldur->getEffectiveHealth() - 2;
        $this->game->effect_moveCrystals($this->heroId, "red", $preDmg, $this->heroId, ["message" => ""]);

        $this->seedRand([5, 5, 5]);
        $this->runMonsterAttack($brute, $boldurHex);

        $this->assertEquals(1, $this->countDamage($brute), "Reflect still fires even when the attack KOs Boldur");
    }

    // --- Fortified I (card_ability_4_13) ---
    // r=spendUse:1heal(self) — flip card as used, then heal 1 damage from Boldur.

    public function testFortifiedIResolveHealsBoldurAndMarksUsed(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_4_13";
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        $this->game->effect_moveCrystals($this->heroId, "red", 2, $this->heroId, ["message" => ""]);
        $this->assertEquals(2, $this->countDamage($this->heroId));

        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        $this->confirmCardEffect();

        $this->assertEquals(1, $this->countDamage($this->heroId), "Fortified I should heal 1 damage from Boldur");
        $this->assertEquals(1, $this->game->tokens->getTokenState($cardId), "card should be marked used (state=1)");
    }

    // --- Fortified II (card_ability_4_14) ---
    // Same active effect as Fortified I (r=spendUse:1heal(self)) but with passive
    // stat bonuses: strength=+1, health=+2.

    public function testFortifiedIIHealsAndGrantsPassiveStatBonuses(): void {
        $color = $this->getActivePlayerColor();
        $cardId = "card_ability_4_14";
        $this->game->tokens->moveToken($cardId, "tableau_$color");
        $hero = $this->game->getHero($color);
        $hero->recalcTrackers();

        // Passive stats: Boldur Hero I (3) + First Pick (1) + Fortified II (1) = 5 dice.
        $this->assertEquals(5, $hero->getAttackStrength(), "Fortified II grants +1 strength");
        // Health: Boldur Hero I (6) + Fortified II (2) = 8 max.
        $this->assertEquals(8, $hero->getMaxHealth(), "Fortified II grants +2 health");

        // Active effect: spendUse:1heal(self) — heal 1 damage and mark card used.
        $this->game->effect_moveCrystals($this->heroId, "red", 2, $this->heroId, ["message" => ""]);
        $this->assertEquals(2, $this->countDamage($this->heroId));

        $this->assertValidTarget($cardId);
        $this->respond($cardId);
        $this->confirmCardEffect();

        $this->assertEquals(1, $this->countDamage($this->heroId), "Fortified II should heal 1 damage from Boldur");
        $this->assertEquals(1, $this->game->tokens->getTokenState($cardId), "card should be marked used (state=1)");
    }

    // --- Boldur Hero II (card_hero_4_2) ---
    // r=empty, on=empty → passive stat-bearing card. strength=5, health=7.
    // Effect text "Armor. (Always prevents 1 damage)" is delivered by Hero::getArmor (hno==4 → 1),
    // shared with Hero I.

    public function testBoldurHeroIIRaisesStrengthAndHealthKeepsArmor(): void {
        $color = $this->getActivePlayerColor();
        // Swap Hero I out for Hero II (setup places card_hero_4_1).
        $this->game->tokens->moveToken("card_hero_4_1", "limbo");
        $this->game->tokens->moveToken("card_hero_4_2", "tableau_$color");
        $this->game->getHero($color)->recalcTrackers();

        $hero = $this->game->getHero($color);
        // Strength = 5 (Hero II) + 1 (Boldur's First Pick in starter tableau).
        $this->assertEquals(6, $hero->getAttackStrength(), "Hero II + First Pick strength");
        $this->assertEquals(7, $hero->getMaxHealth(), "Hero II health");
        $this->assertEquals(1, $hero->getArmor(), "Boldur retains armor=1 with Hero II");
    }
}
