<?php

declare(strict_types=1);

/** BGA #251273 / #240269: trollkin support must drop once the adjacent trollkin is killed. */
final class Op_monsterAttackTrollkinKillTest extends AbstractOpTestCase {
    function getOperationType(): string {
        return "monsterAttack";
    }

    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("hero_2", "limbo");
        $this->game->tokens->moveToken("hero_3", "limbo");
        $this->game->tokens->moveToken("hero_4", "limbo");
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");
        $this->game->tokens->moveToken("monster_brute_1", "hex_12_7");
    }

    private function attackDamage(): int {
        $this->game->randQueue = [5, 5];
        $op = $this->game->machine->instantiateOperation("monsterAttack", null, ["char" => "monster_goblin_1"]);
        $op->resolve();
        $this->game->machine->dispatchAll();
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", "hero_1"));
    }

    public function testSupportCountedWhileBruteAlive(): void {
        $this->assertEquals(2, $this->attackDamage());
    }

    public function testSupportDropsAfterAdjacentBruteKilled(): void {
        // Prime the occupancy cache with the brute present, then kill through the real path
        $this->assertEquals("monster_brute_1", $this->game->hexMap->getCharacterOnHex("hex_12_7"));
        $this->game->getMonster("monster_brute_1")->finalizeDamage(3, "hero_1", true);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_brute_1"));
        $this->assertNull($this->game->hexMap->getCharacterOnHex("hex_12_7"));

        $this->assertEquals(1, $this->attackDamage());
    }
}
