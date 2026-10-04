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
        $op = $this->game->machine->instantiateOperation("spendDurab:2heal(adj)", PCOLOR, ["card" => "card_equip_1_19"]);
        $this->assertInstanceOf(ComplexOperation::class, $op);
        foreach ($op->delegates as $sub) {
            $this->assertEquals("card_equip_1_19", $sub->getDataField("card"), "child " . $sub->getType() . " missing card data");
        }
    }

    public function testWithDataPreservesChildCount(): void {
        /** @var ComplexOperation $op */
        $op = $this->game->machine->instantiateOperation("spendDurab:2heal(adj)", PCOLOR, ["card" => "card_equip_1_19"]);
        foreach ($op->delegates as $sub) {
            if ($sub->getType() === "heal") {
                /** @var CountableOperation $sub */
                $this->assertEquals(2, $sub->getCount(), "child heal count should be 2");
            }
        }
    }

    public function testWithDataDoesNotOverwriteChildMcount(): void {
        /** @var ComplexOperation $op */
        $op = $this->game->machine->instantiateOperation("spendDurab:2heal(adj)", PCOLOR, ["card" => "card_equip_1_19", "count" => 5]);
        foreach ($op->delegates as $sub) {
            if ($sub->getType() === "heal") {
                /** @var CountableOperation $sub */
                $this->assertEquals(2, $sub->getCount(), "child heal count should remain 2, not parent's 5");
            }
        }
    }

    public function testWithDataOnSimpleOpNoChildren(): void {
        $op = $this->game->machine->instantiateOperation("spendDurab", PCOLOR, ["card" => "card_equip_1_19"]);
        $this->assertNotInstanceOf(ComplexOperation::class, $op);
        $this->assertEquals("card_equip_1_19", $op->getDataField("card"));
    }

    /**
     * Op_seq gates the sequence on its first delegate only. With an empty battle display
     * `addDamage` has no targets but skips itself, while `spendMana` has no targets and cannot.
     */
    private function seq(string $expr) {
        $this->game->tokens->moveToken("die_attack_1", "limbo", 1);
        return $this->game->machine->instantiateOperation($expr, PCOLOR);
    }

    public function testSeqIsVoidWhenFirstSubIsVoid(): void {
        $this->assertTrue($this->seq("spendMana,gainMana")->isVoid(), "a non-skippable empty first sub voids the whole sequence");
    }

    public function testSeqOffersConfirmWhenFirstSubIsSkippable(): void {
        $this->assertTrue($this->seq("addDamage")->noValidTargets(), "precondition: no dice to add damage to");
        $seq = $this->seq("addDamage,gainMana");
        $this->assertSame(["confirm"], $seq->getArgs()["target"], "an empty but skippable first sub must not void what follows");
    }

    public function testSeqBranchStaysSelectableInChoice(): void {
        $info = $this->seq("addDamage,gainMana/gainMana")->getArgs()["info"];
        $this->assertSame(0, $info["choice_0"]["q"], "the branch is still doable through its later delegate");
    }
}
