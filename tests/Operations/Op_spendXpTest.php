<?php

declare(strict_types=1);

final class Op_spendXpTest extends AbstractOpTestCase {
    public function testSpendXpRemovesCrystal(): void {
        // Give the player some XP first
        $heroId = $this->game->getHeroTokenId(PCOLOR);
        $this->game->effect_moveCrystals($heroId, "yellow", 3, $this->getPlayersTableau(), ["message" => ""]);
        $before = $this->countYellowCrystals($this->getPlayersTableau());

        $op = $this->createOp("spendXp");
        $op->resolve();

        $after = $this->countYellowCrystals($this->getPlayersTableau());
        $this->assertEquals($before - 1, $after);
    }

    public function testSpendXpWithCount(): void {
        $heroId = $this->game->getHeroTokenId(PCOLOR);
        $this->game->effect_moveCrystals($heroId, "yellow", 3, $this->getPlayersTableau(), ["message" => ""]);
        $before = $this->countYellowCrystals($this->getPlayersTableau());

        $op = $this->createOp("2spendXp");
        $op->resolve();

        $after = $this->countYellowCrystals($this->getPlayersTableau());
        $this->assertEquals($before - 2, $after);
    }

    public function testSpendXpThrowsWhenInsufficient(): void {
        // Remove all yellow crystals from tableau first (setup places 2)
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", $this->getPlayersTableau());
        foreach ($crystals as $c) {
            $this->game->tokens->moveToken($c["key"], "supply_crystal_yellow");
        }

        $this->expectException(\Bga\GameFramework\UserException::class);
        $op = $this->createOp("spendXp");
        $op->resolve();
    }

    public function testSpendXpVoidWhenInsufficient(): void {
        // Drain tableau XP — op should self-block via ERR_COST before any user can pick it.
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", $this->getPlayersTableau());
        foreach ($crystals as $c) {
            $this->game->tokens->moveToken($c["key"], "supply_crystal_yellow");
        }

        $op = $this->createOp("2spendXp");
        $this->assertNoValidTargetsAndError(\Bga\Games\Fate\Material::ERR_COST);
        $this->assertTrue($op->isVoid(), "spendXp must be void when the tableau holds fewer XP than the cost");
    }

    public function testSpendXpNotVoidWhenExactCostAvailable(): void {
        // Setup places 2 yellow on tableau; 2spendXp should be allowed (boundary case).
        $op = $this->createOp("2spendXp");
        $this->assertFalse($op->isVoid(), "spendXp should be applicable when XP == cost");
    }
}
