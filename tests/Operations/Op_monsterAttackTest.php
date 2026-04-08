<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_monsterAttackTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();

        $this->game->initWithHero(1);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        // Move other heroes off map so they don't interfere with ranged attacks
        $this->game->tokens->moveToken("hero_2", "limbo");
        $this->game->tokens->moveToken("hero_3", "limbo");
        $this->game->tokens->moveToken("hero_4", "limbo");
    }

    private function resolveMonsterAttack(string $monsterId): void {
        $op = $this->game->machine->instanciateOperation("monsterAttack", null, ["char" => $monsterId]);
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
        $this->game->hexMap->invalidateOccupancy();

        $this->resolveMonsterAttack("monster_goblin_1");

        // No damage on hero
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(0, $crystals);
    }

    public function testDeadMonsterSkipsAttack(): void {
        // Monster not on map (killed before attack phase)
        $this->game->tokens->moveToken("monster_goblin_1", "supply_monster");
        $this->game->hexMap->invalidateOccupancy();

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
        $this->game->hexMap->invalidateOccupancy();

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
        $this->game->hexMap->invalidateOccupancy();

        $this->game->randQueue = [1];
        $this->resolveMonsterAttack("monster_goblin_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(0, $crystals);
    }

    public function testBruteAttacksWithHigherStrength(): void {
        // Brute strength=3
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
        $this->game->hexMap->invalidateOccupancy();

        // Seed 3 dice: hit, miss, hit → 2 damage
        $this->game->randQueue = [5, 1, 6];
        $this->resolveMonsterAttack("monster_brute_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(2, $crystals);
    }

    // -------------------------------------------------------------------------
    // Trollkin faction bonus
    // -------------------------------------------------------------------------

    public function testTrollkinFactionBonus(): void {
        // Two trollkin monsters: goblin on hex_12_8 (adjacent to hero on hex_11_8),
        // brute on hex_12_7 (adjacent to goblin). Goblin attacks → +1 for adjacent trollkin brute = strength 2
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_7");
        $this->game->hexMap->invalidateOccupancy();

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
        $this->game->hexMap->invalidateOccupancy();

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
        $this->game->hexMap->invalidateOccupancy();

        $this->game->randQueue = [5]; // 1 hit
        $this->resolveMonsterAttack("monster_sprite_1");

        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1");
        $this->assertCount(1, $crystals);
    }

    public function testTrollkinDoesNotAttackAtRange2(): void {
        // Goblin (trollkin) has range 1, hero_1 at hex_11_8, goblin at hex_13_7 (distance 2)
        $this->game->tokens->moveToken("monster_goblin_1", "hex_13_7");
        $this->game->hexMap->invalidateOccupancy();

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
        $this->game->hexMap->invalidateOccupancy();

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
        $this->game->hexMap->invalidateOccupancy();

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
        $this->game->hexMap->invalidateOccupancy();

        // Need time track setup for turnMonster
        $this->game->tokens->moveToken("rune_stone", "timetrack_1", 0);

        $op = $this->game->machine->instanciateOperation("turnMonster", null);
        $op->resolve();

        // Check that monsterAttackAll was queued
        $attackAllOps = $this->game->machine->db->getOperations(null, "monsterAttackAll");
        $this->assertNotEmpty($attackAllOps);
    }
}
