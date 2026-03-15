<?php

declare(strict_types=1);

use Bga\Games\Fate\Operations\Op_resolveHits;
use Bga\Games\Fate\OpCommon\Operation;
use Bga\Games\Fate\Stubs\GameUT;
use PHPUnit\Framework\TestCase;

final class Op_resolveHitsTest extends TestCase {
    private GameUT $game;

    protected function setUp(): void {
        $this->game = new GameUT();
        $this->game->init();
        $this->game->tokens->createAllTokens();
        // Assign hero 1 (Bjorn) to PCOLOR: strength 2, starting equip (+1) = 3 dice
        $this->game->tokens->moveToken("card_hero_1_1", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_ability_1_3", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("card_equip_1_15", "tableau_" . PCOLOR);
        $this->game->tokens->moveToken("hero_1", "hex_11_8");
    }

    private function getDamage(string $monsterId): int {
        return count($this->game->tokens->getTokensOfTypeInLocation("crystal_red", $monsterId));
    }

    // -------------------------------------------------------------------------
    // Armor reduces hits (Draugr has armor=1)
    // -------------------------------------------------------------------------

    public function testDraugrArmorReducesHitsInResolve(): void {
        // Draugr (health=5, armor=1) adjacent to hero
        $this->game->tokens->moveToken("monster_draugr_1", "hex_12_8");

        // Roll 2 dice, both hit (side 5 = hit)
        $this->game->randQueue = [5, 5];
        $this->game->effect_rollAttackDice("hero_1", "monster_draugr_1", 2);

        // Queue resolveHits
        /** @var Op_resolveHits */
        $op = $this->game->machine->instanciateOperation("resolveHits", PCOLOR, [
            "attacker" => "hero_1",
            "target" => "hex_12_8",
        ]);
        $op->resolve();

        // 2 hits - 1 armor = 1 effective damage queued as dealDamage
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $dealDamageOps = array_filter($ops, fn($o) => str_contains($o["type"], "dealDamage"));
        $this->assertNotEmpty($dealDamageOps, "dealDamage should be queued");
        $dealDamage = reset($dealDamageOps);
        $this->assertEquals("1dealDamage", $dealDamage["type"]);
    }

    public function testDraugrArmorAbsorbsAllHits(): void {
        // Draugr (armor=1) adjacent to hero — only 1 hit, armor absorbs it entirely
        $this->game->tokens->moveToken("monster_draugr_1", "hex_12_8");

        // Roll 1 die, hit (side 5)
        $this->game->randQueue = [5];
        $this->game->effect_rollAttackDice("hero_1", "monster_draugr_1", 1);

        /** @var Op_resolveHits */
        $op = $this->game->machine->instanciateOperation("resolveHits", PCOLOR, [
            "attacker" => "hero_1",
            "target" => "hex_12_8",
        ]);
        $op->resolve();

        // 1 hit - 1 armor = 0 → no dealDamage queued, attack missed
        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $dealDamageOps = array_filter($ops, fn($o) => str_contains($o["type"], "dealDamage"));
        $this->assertEmpty($dealDamageOps, "No dealDamage should be queued when armor absorbs all hits");
    }

    public function testNoArmorMonsterTakesFullDamage(): void {
        // Goblin (no armor) adjacent to hero — 2 hits should all go through
        $this->game->tokens->moveToken("monster_goblin_1", "hex_12_8");

        // Roll 2 dice, both hit
        $this->game->randQueue = [5, 5];
        $this->game->effect_rollAttackDice("hero_1", "monster_goblin_1", 2);

        /** @var Op_resolveHits */
        $op = $this->game->machine->instanciateOperation("resolveHits", PCOLOR, [
            "attacker" => "hero_1",
            "target" => "hex_12_8",
        ]);
        $op->resolve();

        $ops = $this->game->machine->getAllOperations(PCOLOR);
        $dealDamageOps = array_filter($ops, fn($o) => str_contains($o["type"], "dealDamage"));
        $this->assertNotEmpty($dealDamageOps);
        $dealDamage = reset($dealDamageOps);
        $this->assertEquals("2dealDamage", $dealDamage["type"]);
    }
}
