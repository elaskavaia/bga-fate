<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_heal;
use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_healTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR, hero 2 (Alva) to BCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_hero_2_1", "tableau_" . BCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("hero_2", "hex_12_8");
    }

    private function createOp(string $expr = "2heal(self)"): Op_heal {
        /** @var Op_heal */
        $op = $this->game->machine->instanciateOperation($expr, PCOLOR);
        return $op;
    }

    private function addDamage(string $heroId, int $amount): void {
        $this->game->effect_moveCrystals($heroId, "red", $amount, $heroId, ["message" => ""]);
    }

    private function getDamage(string $heroId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $heroId));
    }

    public function testHealSelfRemovesDamage(): void {
        $this->addDamage("hero_1", 4);
        $op = $this->createOp("2heal(self)");
        $op->action_resolve(["target" => "hex_11_8"]);
        $this->assertEquals(2, $this->getDamage("hero_1"));
    }

    public function testHealSelfCapsAtCurrentDamage(): void {
        $this->addDamage("hero_1", 1);
        $op = $this->createOp("2heal(self)");
        $op->action_resolve(["target" => "hex_11_8"]);
        $this->assertEquals(0, $this->getDamage("hero_1"));
    }

    public function testHealSelfNotApplicableWhenNoDamage(): void {
        $op = $this->createOp("2heal(self)");
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_11_8", $moves);
        $this->assertEquals(Material::ERR_NOT_APPLICABLE, $moves["hex_11_8"]["q"]);
    }

    public function testHealSelfTargetsWhenDamaged(): void {
        $this->addDamage("hero_1", 3);
        $op = $this->createOp("2heal(self)");
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_11_8", $moves);
        $this->assertEquals(Material::RET_OK, $moves["hex_11_8"]["q"]);
    }

    public function testHealAdjIncludesSelf(): void {
        $this->addDamage("hero_1", 2);
        $op = $this->createOp("1heal(adj)");
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_11_8", $moves);
    }

    public function testHealAdjIncludesAdjacentHero(): void {
        // hex_11_8 and hex_12_8 are adjacent
        $this->addDamage("hero_2", 3);
        $op = $this->createOp("1heal(adj)");
        $moves = $op->getPossibleMoves();
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testHealAdjExcludesDistantHero(): void {
        // Move hero 2 far away
        $this->game->tokens->moveToken("hero_2", "hex_8_5");
        $this->addDamage("hero_2", 3);
        $op = $this->createOp("1heal(adj)");
        $moves = $op->getPossibleMoves();
        $this->assertArrayNotHasKey("hex_8_5", $moves);
    }

    public function testHealAdjResolvesOnTarget(): void {
        $this->addDamage("hero_2", 4);
        $op = $this->createOp("2heal(adj)");
        $op->action_resolve(["target" => "hex_12_8"]);
        $this->assertEquals(2, $this->getDamage("hero_2"));
    }

    public function testHealCountFromExpression(): void {
        $this->addDamage("hero_1", 5);
        $op = $this->createOp("3heal(self)");
        $op->action_resolve(["target" => "hex_11_8"]);
        $this->assertEquals(2, $this->getDamage("hero_1"));
    }

    public function testHealPresetTargetUsesHexId(): void {
        $this->addDamage("hero_1", 3);
        /** @var Op_heal */
        $op = $this->game->machine->instanciateOperation("2heal", PCOLOR, ["target" => "hex_11_8"]);
        $moves = $op->getPossibleMoves();
        $this->assertCount(1, $moves);
        $this->assertArrayHasKey("hex_11_8", $moves);
        $op->action_resolve(["target" => "hex_11_8"]);
        $this->assertEquals(1, $this->getDamage("hero_1"));
    }
}
