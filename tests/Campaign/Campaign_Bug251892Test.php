<?php

declare(strict_types=1);

require_once __DIR__ . "/CampaignBase.php";

/** BGA #251892: a monster adjacent to two heroes picks its target without asking the players. */
class Campaign_Bug251892Test extends CampaignBaseTest {
    public function testMonsterAdjacentToTwoHeroesAttacksWithoutPrompt(): void {
        $this->setupGame([2, 4]);
        $players = array_values($this->game->loadPlayersBasicInfos());
        $alva = $this->game->getHeroTokenId($players[0]["player_color"]);
        $boldur = $this->game->getHeroTokenId($players[1]["player_color"]);
        $this->clearMonstersFromMap();
        $this->clearHand($players[0]["player_color"]);
        $this->clearHand($players[1]["player_color"]);

        $this->game->tokens->moveToken($alva, "hex_11_8");
        $this->game->tokens->moveToken($boldur, "hex_12_7");
        $brute = "monster_brute_1";
        $this->game->getMonster($brute)->moveTo("hex_12_8", "");
        $this->game->hexMap->invalidateOccupancy();
        $this->assertCount(2, $this->game->machine->instantiateOperation("roll", null, ["attacker" => $brute])->getArgs()["target"]);

        $this->seedRand([5, 5, 5]);
        $this->game->machine->push("monsterAttackAll", null);
        $this->game->gamestate->jumpToState(\Bga\Games\Fate\StateConstants::STATE_GAME_DISPATCH);
        $this->driver->runDispatchLoop();

        // Pins buggy behavior for BGA #251892 - flip the assertion when the fix lands
        $this->assertNotContains($this->getOpArgs()["type"] ?? "", ["monsterAttack", "roll"], "players were not asked which hero is attacked");
        $this->assertEquals(3, $this->countDamage($alva), "first hero (Alva) auto-attacked");
        $this->assertEquals(0, $this->countDamage($boldur));
    }
}
