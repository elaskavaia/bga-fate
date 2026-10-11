<?php

declare(strict_types=1);

final class Op_monsterMoveAllGrimheimChargeTest extends AbstractOpTestCase {
    function getOperationType(): string {
        return "monsterMoveAll";
    }

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("card_hero_1", $this->getPlayersTableau());
        $this->game->tokens->moveToken("hero_2", "hex_1_2");
        $this->game->tokens->moveToken("hero_3", "hex_2_1");
        $this->game->tokens->moveToken("hero_4", "hex_2_2");
    }

    public function testGoblinChargesTowardHeroInsideGrimheim(): void {
        // Path hex_14_7 -> hex_13_7 -> hex_12_8 -> hex_11_8 (adjacent to Grimheim hex_10_8)
        $this->game->tokens->moveToken("hero_1", "hex_10_8");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_14_7");

        $this->createOp("monsterMoveAll", ["charge" => false]);
        $this->call_resolve();

        // Documents buggy behavior for BGA #240669; flip the assertion when fixed (expected hex_12_8)
        $this->assertEquals("hex_11_8", $this->game->tokens->getTokenLocation("monster_goblin_1"));
    }
}
