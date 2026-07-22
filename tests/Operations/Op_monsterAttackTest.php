<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_monsterAttackTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Move other heroes off map so they don't interfere with ranged attacks
        $this->game->tokens->moveToken("hero_2", "limbo");
        $this->game->tokens->moveToken("hero_3", "limbo");
        $this->game->tokens->moveToken("hero_4", "limbo");
    }

    private function resolveMonsterAttack(string $monsterId): void {
        $op = $this->game->machine->instantiateOperation("monsterAttack", null, ["char" => $monsterId]);
        $op->resolve();
        // Run the queued roll → resolveHits → dealDamage pipeline
        $this->game->machine->dispatchAll();
    }

    // -------------------------------------------------------------------------
    // No attack scenarios
    // -------------------------------------------------------------------------

    public function testNoAdjacentHeroesDoesNothing(): void {
        // Monster not adjacent to any hero
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");

        $this->resolveMonsterAttack("monster_goblin_1");

        // No damage on hero
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(0, $crystals);
    }

    public function testDeadMonsterSkipsAttack(): void {
        // Monster not on map (killed before attack phase)
        $this->game->tokens->moveToken("monster_goblin_1", "supply_monster");

        $this->resolveMonsterAttack("monster_goblin_1");

        // No damage on hero
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(0, $crystals);
    }

    // -------------------------------------------------------------------------
    // Basic attack
    // -------------------------------------------------------------------------

    public function testMonsterAttacksAdjacentHero(): void {
        // Goblin strength=1, adjacent to hero on hex_11_8
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        // Seed dice: 1 die (strength=1), side 5 = hit
        $this->game->randQueue = [5];
        $this->resolveMonsterAttack("monster_goblin_1");

        // 1 red crystal on hero
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(1, $crystals);
    }

    public function testMonsterAllMissesNoDamage(): void {
        // Goblin strength=1, side 1 = miss
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->game->randQueue = [1];
        $this->resolveMonsterAttack("monster_goblin_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(0, $crystals);
    }

    public function testBruteAttacksWithHigherStrength(): void {
        // Brute strength=3
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");

        // Seed 3 dice: hit, miss, hit → 2 damage
        $this->game->randQueue = [5, 1, 6];
        $this->resolveMonsterAttack("monster_brute_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(2, $crystals);
    }

    // -------------------------------------------------------------------------
    // Seer of Odin (II) special attack
    // -------------------------------------------------------------------------

    public function testSeerIISpecialAttackHitsHeroOutsideRange(): void {
        // Seer parked far from any hero — range gate would normally veto this attack.
        $this->game->getMonster("monster_legend_2_2")->moveTo("hex_2_8", "");
        $this->game->hexMap->invalidateOccupancy(); // sync occupancy with the hero moves from setUp
        $this->resolveMonsterAttack("monster_legend_2_2");
        $this->assertCount(1, $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1"));
    }

    public function testSeerIIAttackSkipsHeroInGrimheim(): void {
        // Knocked-out heroes sit in Grimheim and are out of Seer's reach.
        $this->game->tokens->moveToken("hero_1", "hex_9_9"); // Grimheim well hex
        $this->game->hexMap->invalidateOccupancy();
        $this->game->getMonster("monster_legend_2_2")->moveTo("hex_2_8", "");
        $this->resolveMonsterAttack("monster_legend_2_2");
        $this->assertCount(0, $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1"));
    }

    public function testAttackAllWithSeerPresentDoesNotCrash(): void {
        // Regression: Seer of Odin (II) buckets under an integer auto-index key.
        // The grouping pass must not feed that int to getCharacterHex() (prod TypeError).
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // adjacent to hero_1
        $this->game->getMonster("monster_legend_2_2")->moveTo("hex_2_8", "");
        $this->game->hexMap->invalidateOccupancy();

        $op = $this->game->machine->instantiateOperation("monsterAttackAll", null);
        $op->resolve();

        $queued = $this->game->machine->db->getOperations(null, "monsterAttack");
        $this->assertCount(2, $queued, "Both the goblin and the Seer should queue a monsterAttack");
    }

    // -------------------------------------------------------------------------
    // Trollkin faction bonus
    // -------------------------------------------------------------------------

    public function testTrollkinFactionBonus(): void {
        // Two trollkin monsters: goblin on hex_12_8 (adjacent to hero on hex_11_8),
        // brute on hex_12_7 (adjacent to goblin). Goblin attacks → +1 for adjacent trollkin brute = strength 2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_7");

        // Seed 2 dice (strength 1 + 1 bonus): hit, hit → 2 damage
        $this->game->randQueue = [5, 5];
        $this->resolveMonsterAttack("monster_goblin_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(2, $crystals);
    }

    public function testHrungbaldDoublesTrollkinSupport(): void {
        // Same setup as testTrollkinFactionBonus, but Hrungbald is on the board (anywhere):
        // the adjacent brute now grants +2 instead of +1, so goblin attacks at strength 3.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8"); // adjacent to hero on hex_11_8
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_7"); // adjacent to goblin
        $this->game->tokens->moveToken("monster_legend_5_1", "hex_2_2"); // Hrungbald in play, far away

        // Seed 3 dice (strength 1 + 2 doubled support): hit, hit, hit → 3 damage
        $this->game->randQueue = [5, 5, 5];
        $this->resolveMonsterAttack("monster_goblin_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(3, $crystals);
    }

    public function testHrungbaldInLimboDoesNotDoubleSupport(): void {
        // Hrungbald II starts in limbo (not on a board hex) — support stays +1.
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_7");
        $this->assertEquals("limbo", $this->game->tokens->getTokenLocation("monster_legend_5_2"));

        // Seed 2 dice (strength 1 + 1 bonus): hit, hit → 2 damage
        $this->game->randQueue = [5, 5];
        $this->resolveMonsterAttack("monster_goblin_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(2, $crystals);
    }

    public function testNonTrollkinNoFactionBonus(): void {
        // Sprite (firehorde) + goblin (trollkin) adjacent to hero — sprite gets no trollkin bonus
        $this->game->tokens->moveToken("monster_sprite_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_11_7");

        // Seed 1 die (sprite strength=1, no bonus): hit → 1 damage
        $this->game->randQueue = [5];
        $this->resolveMonsterAttack("monster_sprite_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(1, $crystals);
    }

    // -------------------------------------------------------------------------
    // Ranged attacks (Fire Horde range 2)
    // -------------------------------------------------------------------------

    public function testFireHordeAttacksAtRange2(): void {
        // Sprite (firehorde) has range 2, hero_1 at hex_11_8, sprite at hex_13_7 (distance 2)
        $this->game->tokens->moveToken("monster_sprite_1", "hex_13_7");

        $this->game->randQueue = [5]; // 1 hit
        $this->resolveMonsterAttack("monster_sprite_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(1, $crystals);
    }

    public function testSurtIIAttacksAtRange3(): void {
        // Surt II has range 3: hero_1 at hex_11_8, Surt at hex_14_7 (distance 3, beyond firehorde range 2).
        $this->game->tokens->moveToken("monster_legend_4_2", "hex_14_7");

        $this->game->randQueue = [5, 5, 5, 5, 5, 5, 5]; // Surt II strength 7, all hits
        $this->resolveMonsterAttack("monster_legend_4_2");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(7, $crystals);
    }

    // -------------------------------------------------------------------------
    // Surt I: runes count as hits for all Fire Horde while he is on the board
    // -------------------------------------------------------------------------

    public function testSurtIGrantsFirehordeRuneAsHit(): void {
        $this->game->tokens->moveToken("monster_sprite_1", "hex_12_8"); // firehorde, adjacent to hero_1
        $this->game->tokens->moveToken("monster_legend_4_1", "hex_2_2"); // Surt I in play, far away

        $this->game->randQueue = [3]; // sprite strength 1, rolls a rune
        $this->resolveMonsterAttack("monster_sprite_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(1, $crystals, "rune counts as a hit while Surt I is in play");
    }

    public function testSurtIIDoesNotGrantFirehordeRune(): void {
        // Surt II grants range 3, not the rune buff - a firehorde rune stays a miss.
        $this->game->tokens->moveToken("monster_sprite_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_legend_4_2", "hex_2_2"); // Surt II in play

        $this->game->randQueue = [3]; // rune
        $this->resolveMonsterAttack("monster_sprite_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(0, $crystals, "rune stays a miss - Surt II grants range, not the rune buff");
    }

    public function testTrollkinDoesNotAttackAtRange2(): void {
        // Goblin (trollkin) has range 1, hero_1 at hex_11_8, goblin at hex_13_7 (distance 2)
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");

        $this->resolveMonsterAttack("monster_goblin_1");

        // No damage — goblin can't reach hero at range 2
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(0, $crystals);
    }

    // -------------------------------------------------------------------------
    // Target selection (picks weakest)
    // -------------------------------------------------------------------------

    public function testPicksWeakestHero(): void {
        // Two heroes adjacent to monster: hero_1 (Bjorn, health=9) and hero_4 (Boldur, health=6)
        $this->game->tokens->moveToken("card_hero_4_1", "tableau_" . BCOLOR);
        $this->game->tokens->moveToken("hero_4", "hex_11_7");
        // Give hero_1 more damage (7/9 = lower effective HP than 0/6)
        $this->game->effect_moveCrystals("hero_1", "red", 7, "hero_1", ["message" => ""]);

        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        // Seed: 1 hit
        $this->game->randQueue = [5];
        $this->resolveMonsterAttack("monster_goblin_1");

        // Hero_1 has lower effective HP (9-7=2 vs 6-0=6), so it should be targeted
        $crystals1 = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(8, $crystals1); // 7 + 1

        $crystals4 = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_4");
        $this->assertCount(0, $crystals4);
    }

    // -------------------------------------------------------------------------
    // Queueing from turnMonster
    // -------------------------------------------------------------------------

    public function testHeroKnockoutEndsGameWhenLastHouseDestroyed(): void {
        // Destroy all houses except Freyja's Well and one other
        for ($i = 2; $i <= 9; $i++) {
            $this->game->tokens->moveToken("house_$i", "limbo");
        }
        // house_0 (Well) and house_1 remain — knockout destroys 2 → game over

        // Pre-place 8 damage on Bjorn (health=9), goblin hits for 1 → knocked out
        $this->game->effect_moveCrystals("hero_1", "red", 8, "hero_1", ["message" => ""]);
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $this->game->randQueue = [5]; // 1 hit
        $this->resolveMonsterAttack("monster_goblin_1");

        // Both houses destroyed → Freyja's Well gone → game over
        $this->assertTrue($this->game->isEndOfGame(), "Game should end when knockout destroys last houses");
        $this->assertFalse($this->game->isHeroesWin(), "Heroes should lose");
    }

    // -------------------------------------------------------------------------
    // Queueing from turnMonster
    // -------------------------------------------------------------------------

    public function testTurnMonsterQueuesAttacks(): void {
        // Place monster adjacent to hero — check that monsterAttack is queued
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        // Need time track setup for turnMonster
        $this->game->tokens->moveToken("rune_stone", "timetrack_1", 0);

        $op = $this->game->machine->instantiateOperation("turnMonster", null);
        $op->resolve();

        // Check that monsterAttackAll was queued
        $attackAllOps = $this->game->machine->db->getOperations(null, "monsterAttackAll");
        $this->assertNotEmpty($attackAllOps);
    }
}
