<?php

declare(strict_types=1);

/**
 * BGA #237220: a monster stopped taking damage entirely - the log read
 * "Alva deals 2 [DAMAGE] to Brute (0/3)" and the Brute never accumulated damage.
 *
 * Two defects, both fixed and verified here:
 *  - Game::effect_moveCrystals grew a pile with DbTokens::pickTokensForLocation, which moves
 *    only as many tokens as the source holds, so an empty supply_crystal_red placed zero
 *    crystals while the log still announced the full amount. Supplies are unlimited, so the
 *    shortfall is now minted.
 *  - Game::effect_monsterEntersGrimheim returned the monster to supply_monster without
 *    sweeping its crystals, which is how the supply drained in the first place - and the
 *    same token then respawned still damaged.
 */
final class Op_applyDamageCrystalSupplyBug237220Test extends AbstractOpTestCase {
    function getOperationType(): string {
        return "applyDamage";
    }

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");
    }

    /** Park every red crystal outside the supply, leaving $keep available. */
    private function drainRedSupply(int $keep = 0): void {
        $crystals = array_values($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "supply_crystal_red"));
        foreach (array_slice($crystals, $keep) as $crystal) {
            $this->game->tokens->moveToken($crystal["key"], "limbo");
        }
    }

    private function applyDamage(int $amount): void {
        $this->createOp("applyDamage", [
            "attacker" => "hero_1",
            "target" => "monster_brute_1",
            "amount" => $amount,
        ])->resolve();
    }

    private function getLastDamageNotification(): array {
        $found = null;
        foreach ($this->game->notify->_getNotifications() as $notification) {
            if (str_contains($notification["log"], 'deals ${amount} [DAMAGE]')) {
                $found = $notification;
            }
        }
        $this->assertNotNull($found, "applyDamage should have logged a damage message");
        return $found;
    }

    public function testEmptyRedSupplyStillAppliesTheFullDamage(): void {
        $this->drainRedSupply();

        $this->applyDamage(2);

        $notification = $this->getLastDamageNotification();
        $this->assertEquals(2, $notification["args"]["amount"], "log announces the full amount");
        $this->assertEquals(2, $notification["args"]["totalDamage"], "announced damage actually landed");
        $this->assertEquals(2, $this->countRedCrystals("monster_brute_1"));
    }

    public function testMonsterStillDiesWhenRedSupplyIsEmpty(): void {
        $this->drainRedSupply();

        // Brute health = 3.
        $this->applyDamage(3);
        $this->dispatchAll();

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_brute_1"));
    }

    public function testShortSupplyMintsOnlyTheMissingCrystals(): void {
        $this->drainRedSupply(1);

        $this->applyDamage(3);

        $this->assertEquals(3, $this->countRedCrystals("monster_brute_1"), "the one left plus 2 minted");
        $this->assertEquals(0, $this->countRedCrystals("supply_crystal_red"), "nothing minted beyond what was needed");
        // Minted ids continue past the 50 in the box rather than colliding with them.
        $this->assertNotNull($this->game->tokens->getTokenInfo("crystal_red_51"));
    }

    /**
     * The leak that drained the supply: a damaged monster reaching Grimheim must hand its
     * crystals back, otherwise they stay parented to the monster token forever - and ride
     * back onto the board when that token respawns.
     */
    public function testMonsterEnteringGrimheimReturnsItsDamageCrystals(): void {
        $supplyBefore = $this->countRedCrystals("supply_crystal_red");
        $this->applyDamage(2);
        // Prey parks yellow on a monster, so the sweep has to take every colour, not just damage.
        $this->game->effect_moveCrystals("monster_brute_1", "yellow", 2, "monster_brute_1");
        $this->assertEquals(2, $this->countRedCrystals("monster_brute_1"));

        $this->game->effect_monsterEntersGrimheim("monster_brute_1");

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_brute_1"));
        $this->assertEquals(0, $this->countRedCrystals("monster_brute_1"), "respawns undamaged");
        $this->assertEquals(0, $this->countYellowCrystals("monster_brute_1"), "and without a leftover prey bonus");
        $this->assertEquals($supplyBefore, $this->countRedCrystals("supply_crystal_red"), "crystals are back in circulation");
    }

    /**
     * Self-heal for tables already in flight: monsters parked in the supply before the fix
     * still hold their crystals, so the sweep also has to happen on the way out.
     */
    public function testMonsterLeavingTheSupplyArrivesUndamaged(): void {
        $this->applyDamage(2);
        $this->game->tokens->moveToken("monster_brute_1", "supply_monster");
        $this->assertEquals(2, $this->countRedCrystals("monster_brute_1"), "pre-fix state: damaged in the supply");

        $this->game->getMonster("monster_brute_1")->moveTo("hex_12_8", "");

        $this->assertEquals(0, $this->countRedCrystals("monster_brute_1"));
    }
}
