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
}
