<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\OpCommon\CountableOperation;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class OperationTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("card_equip_1_21", "tableau_" . PCOLOR);
    }

    private function addDamage(string $tokenId, int $amount): void {
        $this->game->effect_moveCrystals($tokenId, "red", $amount, $tokenId, ["message" => ""]);
    }

    // -------------------------------------------------------------------------
    // getPossibleMovesDelegate — single operation
    // -------------------------------------------------------------------------

    public function testDelegateSingleWithTargets(): void {
        $this->addDamage("hero_1", 3);
        $op = $this->game->machine->instantiateOperation("actionMend", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_11_8", $moves);
        $this->assertEquals(Material::RET_OK, $moves["hex_11_8"]["q"]);
        $this->assertEquals("2heal", $moves["hex_11_8"]["delegate"]);
    }

    public function testDelegateSingleNoTargetsHasError(): void {
        // No damage → heal targets present but not applicable
        $op = $this->game->machine->instantiateOperation("actionMend", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_11_8", $moves);
        $this->assertNotEquals(Material::RET_OK, $moves["hex_11_8"]["q"]);
        $this->assertEquals("2heal", $moves["hex_11_8"]["delegate"]);
    }

    // -------------------------------------------------------------------------
    // getPossibleMovesDelegate — multiple operations
    // -------------------------------------------------------------------------

    public function testDelegateMultiMergesTargets(): void {
        // In Grimheim: both heal and repairCard delegates
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->addDamage("hero_1", 2);
        $this->addDamage("card_equip_1_21", 1);
        $op = $this->game->machine->instantiateOperation("actionMend", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_9_9", $moves);
        $this->assertArrayHasKey("card_equip_1_21", $moves);
        $this->assertEquals("5removeDamage", $moves["hex_9_9"]["delegate"]);
        $this->assertEquals("5removeDamage", $moves["card_equip_1_21"]["delegate"]);
    }

    public function testDelegateMultiPartialTargets(): void {
        // In Grimheim: hero has damage, equipment does not
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $this->addDamage("hero_1", 2);
        $op = $this->game->machine->instantiateOperation("actionMend", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_9_9", $moves);
        // Equipment has no damage → still present but not applicable
        $this->assertArrayHasKey("card_equip_1_21", $moves);
        $this->assertNotEquals(Material::RET_OK, $moves["card_equip_1_21"]["q"]);
    }

    public function testDelegateMultiNoTargetsAllHaveErrors(): void {
        // In Grimheim: no damage anywhere → targets present but all non-applicable
        $this->game->tokens->moveToken("hero_1", "hex_9_9");
        $op = $this->game->machine->instantiateOperation("actionMend", PCOLOR);
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_9_9", $moves);
        $this->assertNotEquals(Material::RET_OK, $moves["hex_9_9"]["q"]);
        $this->assertArrayHasKey("card_equip_1_21", $moves);
        $this->assertNotEquals(Material::RET_OK, $moves["card_equip_1_21"]["q"]);
    }

    // -------------------------------------------------------------------------
    // copy() roundtrip
    // -------------------------------------------------------------------------

    public function testCopyPreservesTypeAndOwner(): void {
        $op = $this->game->machine->instantiateOperation("dealDamage(adj)", PCOLOR);
        $copy = $op->copy();
        $this->assertEquals($op->getTypeFullExpr(), $copy->getTypeFullExpr());
        $this->assertEquals($op->getOwner(), $copy->getOwner());
    }

    public function testCopyPreservesData(): void {
        $op = $this->game->machine->instantiateOperation("dealDamage(adj)", PCOLOR, ["card" => "test_card", "reason" => "test"]);
        $copy = $op->copy();
        $this->assertEquals("test_card", $copy->getDataField("card"));
        $this->assertEquals("test", $copy->getDataField("reason"));
    }

    public function testCopyPreservesCount(): void {
        /** @var \Bga\Games\Fate\OpCommon\CountableOperation */
        $copy = $this->game->machine->instantiateOperation("2dealDamage(adj)", PCOLOR)->copy();
        $this->assertEquals(2, $copy->getCount());
    }

    public function testCopyIsIndependent(): void {
        $op = $this->game->machine->instantiateOperation("dealDamage(adj)", PCOLOR, ["card" => "original"]);
        $copy = $op->copy();
        $copy->withDataField("card", "modified");
        $this->assertEquals("original", $op->getDataField("card"), "Modifying copy should not affect original");
    }

    public function testCopyHasZeroId(): void {
        $op = $this->game->machine->instantiateOperation("dealDamage(adj)", PCOLOR);
        $copy = $op->copy();
        $this->assertEquals(0, $copy->getId());
    }

    public function testCopyComplexOperation(): void {
        $op = $this->game->machine->instantiateOperation("gainXp/drawEvent", PCOLOR, ["card" => "test"]);
        $copy = $op->copy();
        $this->assertEquals("gainXp/drawEvent", $copy->getTypeFullExpr());
        $this->assertEquals("test", $copy->getDataField("card"));
        $this->assertEquals(PCOLOR, $copy->getOwner());
    }

    public function testCopySeqOperation(): void {
        $op = $this->game->machine->instantiateOperation("dealDamage(adj),moveMonster(marked)", PCOLOR, ["reason" => "kick"]);
        $copy = $op->copy();
        $this->assertEquals("dealDamage(adj),moveMonster(marked)", $copy->getTypeFullExpr());
        $this->assertEquals(["reason" => "kick"], $copy->getData());
    }

    // -------------------------------------------------------------------------
    // action_whatever (zombie pick)
    // -------------------------------------------------------------------------

    /** resolve() validates the pick with count checks, as real multi-select ops do */
    private function fakeOp(string $ttype, array $targets, array $data = []): CountableOperation {
        return new class ($ttype, $targets, $data) extends CountableOperation {
            public mixed $resolved = null;
            function __construct(private string $ttype, private array $targets, array $data) {
                parent::__construct("fake", PCOLOR, $data);
            }
            function getArgType() {
                return $this->ttype;
            }
            function getPossibleMoves() {
                return $this->targets;
            }
            function resolve(): void {
                $this->resolved = $this->getCheckedArg(true, true);
            }
        };
    }

    public function testWhateverNoTargetsSkips(): void {
        $op = $this->fakeOp(Operation::TTYPE_TOKEN, [], ["l_skip" => true]);
        $op->action_whatever();
        $this->assertNull($op->resolved);
        $this->assertNull($this->game->zombieTarget);
    }

    public function testWhateverSingleUsesRolledIndex(): void {
        $op = $this->fakeOp(Operation::TTYPE_TOKEN, ["a", "b", "c"]);
        $this->game->randQueue = [2];
        $op->action_whatever();
        $this->assertEquals("c", $op->resolved);
        $this->assertEquals("c", $this->game->zombieTarget);
    }

    public function testWhateverArrayPicksMinCount(): void {
        $op = $this->fakeOp(Operation::TTYPE_TOKEN_ARRAY, ["a", "b", "c"], ["count" => 3, "mcount" => 2]);
        $op->action_whatever();
        $this->assertEquals(["a", "b"], $op->resolved);
    }

    public function testWhateverArrayCountCappedByTargets(): void {
        $op = $this->fakeOp(Operation::TTYPE_TOKEN_ARRAY, ["a", "b", "c"], ["count" => 5, "mcount" => 3]);
        $op->action_whatever();
        $this->assertEquals(["a", "b", "c"], $op->resolved);
    }

    public function testWhateverCountUsesRolledIndexAndCount(): void {
        $op = $this->fakeOp(Operation::TTYPE_TOKEN_COUNT, ["a", "b"], ["count" => 3, "mcount" => 1]);
        $this->game->randQueue = [1, 2];
        $op->action_whatever();
        $this->assertEquals(["b" => 2], $op->resolved);
    }
}
