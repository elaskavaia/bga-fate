<?php

declare(strict_types=1);

use Bga\Games\Fate\OpCommon\ComplexOperation;
use Bga\Games\Fate\OpCommon\CountableOperation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class ComplexOperationTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    public function testWithDataPropagatesDataToChildren(): void {
        /** @var ComplexOperation $op */
        $op = $this->game->machine->instantiateOperation("costDamage:2heal(adj)", PCOLOR, ["card" => "card_equip_1_19"]);
        $this->assertInstanceOf(ComplexOperation::class, $op);
        foreach ($op->delegates as $sub) {
            $this->assertEquals("card_equip_1_19", $sub->getDataField("card"), "child " . $sub->getType() . " missing card data");
        }
    }

    public function testWithDataPreservesChildCount(): void {
        /** @var ComplexOperation $op */
        $op = $this->game->machine->instantiateOperation("costDamage:2heal(adj)", PCOLOR, ["card" => "card_equip_1_19"]);
        foreach ($op->delegates as $sub) {
            if ($sub->getType() === "heal") {
                /** @var CountableOperation $sub */
                $this->assertEquals(2, $sub->getCount(), "child heal count should be 2");
            }
        }
    }

    public function testWithDataDoesNotOverwriteChildMcount(): void {
        /** @var ComplexOperation $op */
        $op = $this->game->machine->instantiateOperation("costDamage:2heal(adj)", PCOLOR, ["card" => "card_equip_1_19", "count" => 5]);
        foreach ($op->delegates as $sub) {
            if ($sub->getType() === "heal") {
                /** @var CountableOperation $sub */
                $this->assertEquals(2, $sub->getCount(), "child heal count should remain 2, not parent's 5");
            }
        }
    }

    public function testWithDataOnSimpleOpNoChildren(): void {
        $op = $this->game->machine->instantiateOperation("costDamage", PCOLOR, ["card" => "card_equip_1_19"]);
        $this->assertNotInstanceOf(ComplexOperation::class, $op);
        $this->assertEquals("card_equip_1_19", $op->getDataField("card"));
    }
}
