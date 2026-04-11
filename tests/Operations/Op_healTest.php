<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_heal;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_healTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Assign hero 1 (Bjorn) to PCOLOR, hero 2 (Alva) to BCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_hero_2_1", "tableau_" . BCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
    }

    private function addDamage(string $heroId, int $amount): void {
        $this->game->effect_moveCrystals($heroId, "red", $amount, $heroId, ["message" => ""]);
    }

    private function getDamage(string $heroId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $heroId));
    }

    public function testHealSelfRemovesDamage(): void {
        $this->addDamage("hero_1", 4);
        $this->op = $this->createOp("2heal(self)");
        $this->call_resolve("hex_11_8");
        $this->assertEquals(2, $this->getDamage("hero_1"));
    }

    public function testHealSelfCapsAtCurrentDamage(): void {
        $this->addDamage("hero_1", 1);
        $this->op = $this->createOp("2heal(self)");
        $this->call_resolve("hex_11_8");
        $this->assertEquals(0, $this->getDamage("hero_1"));
    }

    public function testHealSelfNotApplicableWhenNoDamage(): void {
        $this->op = $this->createOp("2heal(self)");
        $moves = $this->op->getPossibleMoves();
        $this->assertArrayHasKey("hex_11_8", $moves);
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $moves["hex_11_8"]["q"]);
    }

    public function testHealSelfTargetsWhenDamaged(): void {
        $this->addDamage("hero_1", 3);
        $this->op = $this->createOp("2heal(self)");
        $moves = $this->op->getPossibleMoves();
        $this->assertArrayHasKey("hex_11_8", $moves);
        $this->assertEquals(Material::RET_OK, $moves["hex_11_8"]["q"]);
    }

    public function testHealAdjIncludesSelf(): void {
        $this->addDamage("hero_1", 2);
        $this->op = $this->createOp("1heal(adj)");
        $moves = $this->op->getPossibleMoves();
        $this->assertArrayHasKey("hex_11_8", $moves);
    }

    public function testHealAdjIncludesAdjacentHero(): void {
        // hex_11_8 and hex_12_8 are adjacent
        $this->addDamage("hero_2", 3);
        $this->op = $this->createOp("1heal(adj)");
        $moves = $this->op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testHealAdjExcludesDistantHero(): void {
        // Move hero 2 far away
        $this->game->tokens->moveToken("hero_2", "hex_8_5");
        $this->addDamage("hero_2", 3);
        $this->op = $this->createOp("1heal(adj)");
        $moves = $this->op->getPossibleMoves();
        $this->assertArrayNotHasKey("hex_8_5", $moves);
    }

    public function testHealAdjResolvesOnTarget(): void {
        $this->addDamage("hero_2", 4);
        $this->op = $this->createOp("2heal(adj)");
        $this->call_resolve("hex_12_8");
        $this->assertEquals(2, $this->getDamage("hero_2"));
    }

    public function testHealCountFromExpression(): void {
        $this->addDamage("hero_1", 5);
        $this->op = $this->createOp("3heal(self)");
        $this->call_resolve("hex_11_8");
        $this->assertEquals(2, $this->getDamage("hero_1"));
    }

    public function testHealPresetTargetUsesHexId(): void {
        $this->addDamage("hero_1", 3);
        $this->op = $this->createOp("2heal", ["target" => "hex_11_8"]);
        $moves = $this->op->getArgsInfo();
        $this->assertCount(1, $moves);
        $this->assertValidTarget("hex_11_8");
        $this->call_resolve("hex_11_8");
        $this->assertEquals(1, $this->getDamage("hero_1"));
    }
}
