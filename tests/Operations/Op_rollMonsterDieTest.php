<?php

declare(strict_types=1);

/**
 * Tests for Op_rollMonsterDie — Phase D1 of the Monster Die variant.
 * The op rolls die_monster and parks it on display_monsterturn.
 * Per-side effects (maneuver/attack/push/charge/ambush) ship in later phases.
 */
final class Op_rollMonsterDieTest extends AbstractOpTestCase {
    public function testRollLandsOnDisplay(): void {
        $this->call_resolve();

        $onDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_monster", "display_monsterturn");
        $this->assertCount(1, $onDisplay, "die_monster should be parked on display_monsterturn after roll");

        $die = reset($onDisplay);
        $this->assertEquals("die_monster", $die["key"]);
        $this->assertGreaterThanOrEqual(1, (int) $die["state"]);
        $this->assertLessThanOrEqual(6, (int) $die["state"]);
    }

    public function testRollClearsSupply(): void {
        $this->call_resolve();
        $inSupply = $this->game->tokens->getTokensOfTypeInLocation("die_monster", "supply_die_monster");
        $this->assertCount(0, $inSupply, "die_monster should leave supply when rolled");
    }

    public function testRollSweepsLeftoverFromPriorTurn(): void {
        // Pre-place the die on display as if a previous monster turn left it there.
        $this->game->tokens->dbSetTokenLocation("die_monster", "display_monsterturn", 4, "");
        $this->call_resolve();

        $onDisplay = $this->game->tokens->getTokensOfTypeInLocation("die_monster", "display_monsterturn");
        $this->assertCount(1, $onDisplay, "still exactly one die parked after sweep + reroll");
        $inSupply = $this->game->tokens->getTokensOfTypeInLocation("die_monster", "supply_die_monster");
        $this->assertCount(0, $inSupply, "supply ends empty (die was rolled back out)");
    }

    public function testTurnMonsterQueuesRollWhenOptionOn(): void {
        $this->game->setGameStateValue("var_monster_die", 1);
        $turnOp = $this->game->machine->instantiateOperation("turnMonster", $this->owner);
        $turnOp->action_resolve(["target" => "confirm"]);

        $queued = $this->game->machine->getTopOperations($this->owner);
        $types = array_map(fn($op) => $op["type"], $queued);
        $this->assertContains("rollMonsterDie", $types, "turnMonster must queue rollMonsterDie when var_monster_die=1");
    }

    public function testTurnMonsterSkipsRollWhenOptionOff(): void {
        $this->game->setGameStateValue("var_monster_die", 0);
        $turnOp = $this->game->machine->instantiateOperation("turnMonster", $this->owner);
        $turnOp->action_resolve(["target" => "confirm"]);

        $queued = $this->game->machine->getTopOperations($this->owner);
        $types = array_map(fn($op) => $op["type"], $queued);
        $this->assertNotContains("rollMonsterDie", $types, "turnMonster must NOT queue rollMonsterDie when var_monster_die=0");
    }

    // -------------------------------------------------------------------------
    // Phase D2: passive effects read via Game::getMonsterDieSide()
    // -------------------------------------------------------------------------

    public function testGetMonsterDieSideReturnsNullWhenNoDieParked(): void {
        $this->assertNull($this->game->getMonsterDieSide(), "no die on display = no side");
    }

    public function testGetMonsterDieSideMapsStateToRule(): void {
        $cases = [
            1 => "maneuver_1",
            2 => "maneuver_2",
            3 => "attack",
            4 => "push",
            5 => "charge",
            6 => "ambush",
        ];
        foreach ($cases as $state => $expected) {
            $this->game->tokens->dbSetTokenLocation("die_monster", "display_monsterturn", $state, "");
            $this->assertEquals($expected, $this->game->getMonsterDieSide(), "state $state should map to $expected");
        }
    }
}
