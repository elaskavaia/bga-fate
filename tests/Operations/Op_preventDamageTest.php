<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_preventDamage;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_preventDamageTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
    }

    private function queueDealDamage(int $count = 3): void {
        $this->game->machine->push("dealDamage", PCOLOR, [
            "target" => "hex_11_8",
            "attacker" => "monster_goblin_1",
            "count" => $count,
        ]);
    }

    private function getDealDamageCount(): ?int {
        $ops = $this->game->machine->db->getOperations(null, "dealDamage");
        if (empty($ops)) {
            return null;
        }
        $row = reset($ops);
        $op = $this->game->machine->instanciateOperationFromDbRow($row);
        return (int) $op->getCount();
    }

    // -------------------------------------------------------------------------
    // resolve — reduces dealDamage count
    // -------------------------------------------------------------------------

    public function testPrevent1ReducesDealDamageBy1(): void {
        $this->queueDealDamage(3);
        $op = $this->game->machine->instanciateOperation("1preventDamage", PCOLOR);
        $op->resolve();
        $this->assertEquals(2, $this->getDealDamageCount());
    }

    public function testPrevent2ReducesDealDamageBy2(): void {
        $this->queueDealDamage(3);
        $op = $this->game->machine->instanciateOperation("2preventDamage", PCOLOR);
        $op->resolve();
        $this->assertEquals(1, $this->getDealDamageCount());
    }

    public function testPreventAllDamageHidesDealDamage(): void {
        $this->queueDealDamage(2);
        $op = $this->game->machine->instanciateOperation("2preventDamage", PCOLOR);
        $op->resolve();
        // dealDamage should be hidden (removed from active queue)
        $this->assertNull($this->getDealDamageCount());
    }

    public function testPreventMoreThanDamageClamps(): void {
        $this->queueDealDamage(1);
        $op = $this->game->machine->instanciateOperation("3preventDamage", PCOLOR);
        $op->resolve();
        // Only 1 damage existed, all prevented
        $this->assertNull($this->getDealDamageCount());
    }

    public function testNoDealDamageOnStackReturnsError(): void {
        // No dealDamage queued — getPossibleMoves returns error
        $op = $this->game->machine->instanciateOperation("1preventDamage", PCOLOR);
        $this->assertNotEquals(0, $op->getErrorCode());
    }

    public function testPreventDoesNotAffectOtherOperations(): void {
        $this->game->machine->push("roll", PCOLOR, ["count" => 3]);
        $this->queueDealDamage(3);
        $op = $this->game->machine->instanciateOperation("1preventDamage", PCOLOR);
        $op->resolve();
        // dealDamage reduced, roll untouched
        $this->assertEquals(2, $this->getDealDamageCount());
        $ops = $this->game->machine->db->getOperations(PCOLOR, "roll");
        $this->assertNotEmpty($ops);
    }
}
