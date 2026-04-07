<?php

declare(strict_types=1);

use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_spendXpTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->initWithHero(1);
    }

    public function testSpendXpRemovesCrystal(): void {
        // Give the player some XP first
        $heroId = $this->game->getHeroTokenId(PCOLOR);
        $this->game->effect_moveCrystals($heroId, "yellow", 3, "tableau_" . PCOLOR, ["message" => ""]);
        $before = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $op = $this->game->machine->instanciateOperation("spendXp", PCOLOR);
        $op->resolve();

        $after = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($before - 1, $after);
    }

    public function testSpendXpWithCount(): void {
        $heroId = $this->game->getHeroTokenId(PCOLOR);
        $this->game->effect_moveCrystals($heroId, "yellow", 3, "tableau_" . PCOLOR, ["message" => ""]);
        $before = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));

        $op = $this->game->machine->instanciateOperation("2spendXp", PCOLOR);
        $op->resolve();

        $after = count($this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR));
        $this->assertEquals($before - 2, $after);
    }

    public function testSpendXpThrowsWhenInsufficient(): void {
        // Remove all yellow crystals from tableau first (setup places 2)
        $crystals = $this->game->tokens->getTokensOfTypeInLocation("crystal_yellow", "tableau_" . PCOLOR);
        foreach ($crystals as $c) {
            $this->game->tokens->moveToken($c["key"], "supply_crystal_yellow");
        }

        $this->expectException(\Bga\GameFramework\UserException::class);
        $op = $this->game->machine->instanciateOperation("spendXp", PCOLOR);
        $op->resolve();
    }
}
