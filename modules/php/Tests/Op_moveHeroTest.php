<?php

declare(strict_types=1);

use Bga\Games\Fate\Material;
use Bga\Games\Fate\Operations\Op_moveHero;
use Bga\Games\Fate\Tests\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_moveHeroTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function createOp(string $expr = "1moveHero"): Op_moveHero {
        /** @var Op_moveHero */
        $op = $this->game->machine->instanciateOperation($expr, PCOLOR);
        return $op;
    }

    private function getHeroHex(): string {
        return $this->game->tokens->getTokenLocation("hero_1");
    }

    public function testMoveHero1ReachesAdjacent(): void {
        $op = $this->createOp("1moveHero");
        $moves = $op->getPossibleMoves();
        // Should have some reachable hexes (adjacent non-blocked)
        $this->assertNotEmpty($moves);
        // hex_12_8 is adjacent plains, should be reachable
        $this->assertArrayHasKey("hex_12_8", $moves);
    }

    public function testMoveHero1DoesNotReachDistance2(): void {
        $op = $this->createOp("1moveHero");
        $moves = $op->getPossibleMoves();
        // hex_13_7 is distance 2 from hex_11_8
        $this->assertArrayNotHasKey("hex_13_7", $moves);
    }

    public function testMoveHero2MandatoryOnlyDistance2(): void {
        // 2moveHero is mandatory: must move exactly 2 steps
        $op = $this->createOp("2moveHero");
        $moves = $op->getPossibleMoves();
        $this->assertNotEmpty($moves);
        // hex_12_8 is adjacent (distance 1) — should NOT be offered for mandatory 2-step move
        $this->assertArrayNotHasKey("hex_12_8", $moves);
        // hex_13_7 is distance 2 — should be offered
        $this->assertArrayHasKey("hex_13_7", $moves);
    }

    public function testMoveHeroOptionalShowsAllDistances(): void {
        // [0,2]moveHero is optional: show all reachable hexes
        $op = $this->createOp("[0,2]moveHero");
        $moves = $op->getPossibleMoves();
        // Should include both distance 1 and distance 2
        $this->assertArrayHasKey("hex_12_8", $moves); // distance 1
        $this->assertArrayHasKey("hex_13_7", $moves); // distance 2
    }

    public function testResolveMovesHero(): void {
        $op = $this->createOp("1moveHero");
        $moves = $op->getPossibleMoves();
        $target = array_key_first($moves);
        $op->action_resolve(["target" => $target]);
        $this->assertEquals($target, $this->getHeroHex());
    }

    public function testResolveToGrimheimUsesHomeHex(): void {
        // Move hero adjacent to Grimheim
        $this->game->tokens->moveToken("hero_1", "hex_8_8");
        $op = $this->createOp("1moveHero");
        $moves = $op->getPossibleMoves();
        // Find a Grimheim hex in moves
        $grimheimHex = null;
        foreach (array_keys($moves) as $hex) {
            if ($this->game->hexMap->isInGrimheim($hex)) {
                $grimheimHex = $hex;
                break;
            }
        }
        $this->assertNotNull($grimheimHex, "Should be able to reach Grimheim from hex_8_8");
        $op->action_resolve(["target" => $grimheimHex]);
        // Hero should be at their home hex in Grimheim, not the clicked hex
        $heroHex = $this->getHeroHex();
        $this->assertTrue($this->game->hexMap->isInGrimheim($heroHex));
    }
}
