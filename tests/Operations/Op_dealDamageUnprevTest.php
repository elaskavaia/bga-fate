<?php

declare(strict_types=1);

final class Op_dealDamageUnprevTest extends AbstractOpTestCase {
    protected function setUp(): void {
        parent::setUp();
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
        $this->game->tokens->moveToken("monster_draugr_1", "hex_12_8");
    }

    private function dealToDraugr(int $count): void {
        $this->createOp("{$count}dealDamageUnprev");
        $this->call_resolve("hex_12_8");
        $this->dispatchAll();
    }

    public function testArmorDoesNotAbsorbSinglePoint(): void {
        $this->dealToDraugr(1);
        $this->assertEquals(1, $this->countRedCrystals("monster_draugr_1"));
    }

    public function testArmorDoesNotAbsorbAnyOfMultiplePoints(): void {
        $this->dealToDraugr(3);
        $this->assertEquals(3, $this->countRedCrystals("monster_draugr_1"));
    }

    public function testGetEffectiveDamageIsRawCount(): void {
        $op = $this->createOp("2dealDamageUnprev", ["target" => "hex_12_8"]);
        $this->assertEquals(2, $op->getEffectiveDamage());
    }

    public function testKillGrantsXp(): void {
        // Draugr health=5, xp=3
        $xpBefore = $this->countYellowCrystals($this->getPlayersTableau());
        $this->dealToDraugr(5);
        $this->assertEquals("supply_monster", $this->game->tokens->getTokenLocation("monster_draugr_1"));
        $this->assertEquals($xpBefore + 3, $this->countYellowCrystals($this->getPlayersTableau()));
    }
}
