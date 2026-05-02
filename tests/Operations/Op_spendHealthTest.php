<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\Operation;

final class Op_spendHealthTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->clearMachine(); // drop leftover reinforcement/turnStart so dispatchAll() only runs the queued applyDamage
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getHeroDamage(): int {
        return $this->countRedCrystals("hero_1");
    }

    public function testResolveAdds1Damage(): void {
        $this->assertEquals(0, $this->getHeroDamage());
        $this->createOp();
        $this->call_resolve();
        $this->dispatchAll();
        $this->assertEquals(1, $this->getHeroDamage());
    }

    public function testResolveAddsNDamage(): void {
        $this->createOp("3spendHealth");
        $this->call_resolve();
        $this->dispatchAll();
        $this->assertEquals(3, $this->getHeroDamage());
    }

    public function testResolveTakesFromSupply(): void {
        $supplyBefore = $this->countRedCrystals("supply_crystal_red");
        $this->createOp("2spendHealth");
        $this->call_resolve();
        $this->dispatchAll();
        $supplyAfter = $this->countRedCrystals("supply_crystal_red");
        $this->assertEquals($supplyBefore - 2, $supplyAfter);
    }

    public function testDamageStacksWithExisting(): void {
        $this->game->effect_moveCrystals("hero_1", "red", 2, "hero_1");
        $this->createOp();
        $this->call_resolve();
        $this->dispatchAll();
        $this->assertEquals(3, $this->getHeroDamage());
    }

    public function testKnockoutWhenDamageExceedsHealth(): void {
        $hero = $this->game->getHero($this->owner);
        $maxHealth = $hero->getMaxHealth();
        $this->game->effect_moveCrystals("hero_1", "red", $maxHealth - 1, "hero_1");

        $this->createOp();
        $this->call_resolve();
        $this->dispatchAll();

        // Knockout sets damage to exactly 5 and moves hero to starting hex in Grimheim
        $this->assertEquals(5, $this->getHeroDamage());
        $this->assertEquals("hex_8_9", $this->game->tokens->getTokenLocation("hero_1"));
    }
}
