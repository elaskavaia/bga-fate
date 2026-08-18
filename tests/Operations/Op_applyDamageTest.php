<?php

declare(strict_types=1);

use Bga\Games\Fate\Model\Trigger;

/**
 * Op_applyDamage owns the kill detection + damage notification. The load-bearing
 * invariant (see misc/docs/plan-applydamage-refactor.md) is that on a kill it
 * queues `trigger(TMonsterKilled)` AHEAD of `finishKill`, so handlers run while
 * the dying monster is still on its pre-cleanup hex with its bonus crystals
 * intact. These tests guard that ordering.
 */
final class Op_applyDamageTest extends AbstractOpTestCase {

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    public function testTriggerFiresBeforeCleanup(): void {
        // Goblin: health=2. Apply 2 damage in one shot → kill.
        $monsterHex = "hex_12_8";
        $this->game->tokens->moveToken("monster_goblin_1", $monsterHex);

        $op = $this->createOp("applyDamage", [
            "attacker" => "hero_1",
            "target" => "monster_goblin_1",
            "amount" => 2,
        ]);
        $op->resolve();

        // Resolve queued the trigger and finishKill but did NOT cleanup yet.
        $this->assertEquals($monsterHex, $this->game->tokens->getTokenLocation("monster_goblin_1"), "monster must still be on its hex when trigger handlers fire");

        $opTypes = array_values(array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations(PCOLOR)));
        $triggerType = "trigger(" . Trigger::MonsterKilled->value . ")";

        $triggerIdx = array_search($triggerType, $opTypes, true);
        $finishIdx = array_search("finishKill", $opTypes, true);

        $this->assertNotFalse($triggerIdx, "TMonsterKilled trigger op must be queued");
        $this->assertNotFalse($finishIdx, "finishKill op must be queued");
        $this->assertLessThan($finishIdx, $triggerIdx, "trigger must come before finishKill");
    }

    public function testHeroKnockoutQueuesHeroKnockedOutTrigger(): void {
        // Bjorn maxHealth=9. 9 damage in one shot → knockout.
        $op = $this->createOp("applyDamage", [
            "attacker" => "hero_1",
            "target" => "hero_1",
            "amount" => 9,
        ]);
        $op->resolve();

        // Hero hasn't been moved back to Grimheim yet.
        $this->assertEquals("hex_11_8", $this->game->tokens->getTokenLocation("hero_1"));

        $opTypes = array_values(array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations(PCOLOR)));
        $this->assertContains("trigger(" . Trigger::HeroKnockedOut->value . ")", $opTypes);
        $this->assertContains("finishKill", $opTypes);
    }

    public function testNoTriggerWhenMonsterSurvives(): void {
        // Brute: health=3. Apply 1 → survives.
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_8");

        $op = $this->createOp("applyDamage", [
            "attacker" => "hero_1",
            "target" => "monster_brute_1",
            "amount" => 1,
        ]);
        $op->resolve();

        $opTypes = array_values(array_map(fn($o) => $o["type"], $this->game->machine->getAllOperations(PCOLOR)));
        $triggerType = "trigger(" . Trigger::MonsterKilled->value . ")";
        $this->assertNotContains($triggerType, $opTypes, "no kill trigger when monster survives");
        $this->assertNotContains("finishKill", $opTypes, "no cleanup when monster survives");

        $this->assertEquals(1, $this->countRedCrystals("monster_brute_1"));
    }

    public function testQueenIOnlyDamagedByAdjacentCharacters(): void {
        $queen = "monster_legend_1_1";

        // Attacker hero_1 at hex_11_8; Queen at hex_13_7 (distance 2) - ranged damage prevented.
        $this->game->getMonster($queen)->moveTo("hex_13_7", "");
        $this->createOp("applyDamage", ["attacker" => "hero_1", "target" => $queen, "amount" => 1])->resolve();
        $this->assertEquals(0, $this->countRedCrystals($queen), "non-adjacent attacker deals no damage to Queen I");

        // Queen adjacent (hex_12_8, distance 1) - damage applies.
        $this->game->getMonster($queen)->moveTo("hex_12_8", "");
        $this->createOp("applyDamage", ["attacker" => "hero_1", "target" => $queen, "amount" => 1])->resolve();
        $this->assertEquals(1, $this->countRedCrystals($queen), "adjacent attacker damages Queen I");
    }

    public function testFinishKillRunsCleanupAfterDispatch(): void {
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        $op = $this->createOp("applyDamage", [
            "attacker" => "hero_1",
            "target" => "monster_goblin_1",
            "amount" => 2,
        ]);
        $op->resolve();
        $this->dispatchAll();

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_goblin_1"));
        $this->assertEquals(0, $this->countRedCrystals("monster_goblin_1"));
    }

    /**
     * BGA #237220: a monster stopped taking damage entirely - the log read "Alva deals 2 [DAMAGE]
     * to Brute (0/3)" and the Brute never accumulated damage. Two defects, both fixed and verified
     * below: Game::effect_moveCrystals grew a pile with DbTokens::pickTokensForLocation, which
     * moves only as many tokens as the source holds, so an empty supply_crystal_red placed zero
     * crystals while the log still announced the full amount (supplies are unlimited, so the
     * shortfall is now minted); and Game::effect_monsterEntersGrimheim returned the monster to
     * supply_monster without sweeping its crystals, which is how the supply drained in the first
     * place - the same token then respawned still damaged.
     */
    private function placeBrute(): void {
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
        $this->placeBrute();
        $this->drainRedSupply();

        $this->applyDamage(2);

        $notification = $this->getLastDamageNotification();
        $this->assertEquals(2, $notification["args"]["amount"], "log announces the full amount");
        $this->assertEquals(2, $notification["args"]["totalDamage"], "announced damage actually landed");
        $this->assertEquals(2, $this->countRedCrystals("monster_brute_1"));
    }

    public function testMonsterStillDiesWhenRedSupplyIsEmpty(): void {
        $this->placeBrute();
        $this->drainRedSupply();

        // Brute health = 3.
        $this->applyDamage(3);
        $this->dispatchAll();

        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_brute_1"));
    }

    public function testShortSupplyMintsOnlyTheMissingCrystals(): void {
        $this->placeBrute();
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
        $this->placeBrute();
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
        $this->placeBrute();
        $this->applyDamage(2);
        $this->game->tokens->moveToken("monster_brute_1", "supply_monster");
        $this->assertEquals(2, $this->countRedCrystals("monster_brute_1"), "pre-fix state: damaged in the supply");

        $this->game->getMonster("monster_brute_1")->moveTo("hex_12_8", "");

        $this->assertEquals(0, $this->countRedCrystals("monster_brute_1"));
    }
}
